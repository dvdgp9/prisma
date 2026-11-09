<?php
require_once __DIR__ . '/../includes/work-records.php';
require_once __DIR__ . '/../includes/task-recurrence.php';

function same($expected, $actual, $message) {
    if ($expected !== $actual) throw new RuntimeException($message . ': ' . var_export($actual, true));
    echo "PASS: {$message}\n";
}
function rejects($callback, $message) {
    try { $callback(); } catch (InvalidArgumentException $e) { echo "PASS: {$message}\n"; return; }
    throw new RuntimeException($message);
}

same(['recurrence_unit' => null, 'recurrence_interval' => null], normalize_task_recurrence([]), 'ordinary tasks stay non-recurring');
same(['recurrence_unit' => null, 'recurrence_interval' => null], normalize_task_recurrence(['recurrence_unit' => null], ['recurrence_unit' => 'day', 'recurrence_interval' => 3]), 'recurrence can be disabled');
same(['recurrence_unit' => 'week', 'recurrence_interval' => 2], normalize_task_recurrence(['recurrence_interval' => 2], ['recurrence_unit' => 'week']), 'partial updates retain the unit');
foreach ([0, -1, 366, 1.5, true, null, 'abc', [], '2 days'] as $invalid) {
    rejects(fn() => normalize_task_recurrence(['recurrence_unit' => 'day', 'recurrence_interval' => $invalid]), 'invalid interval rejected: ' . json_encode($invalid));
}
rejects(fn() => normalize_task_recurrence(['recurrence_unit' => 'hour']), 'unknown unit rejected');
rejects(fn() => normalize_task_recurrence(['recurrence_interval' => 2]), 'interval requires a unit');
foreach ([
    ['day', 3, '2026-11-09', '2026-11-12'],
    ['week', 2, '2026-11-09', '2026-11-23'],
    ['month', 1, '2026-01-31', '2026-02-28'],
    ['month', 1, '2028-01-31', '2028-02-29'],
    ['month', 3, '2026-11-30', '2027-02-28'],
    ['year', 1, '2028-02-29', '2029-02-28'],
    ['year', 4, '2028-02-29', '2032-02-29'],
    ['day', 1, '2026-03-28 23:30:00', '2026-03-29'],
    ['day', 1, '2026-10-24 23:30:00', '2026-10-25'],
] as [$unit, $interval, $completed, $due]) {
    same($due, task_recurrence_due_date($unit, $interval, new DateTimeImmutable($completed, new DateTimeZone('Europe/Madrid'))), "{$completed} + {$interval} {$unit}");
}

$db = new PDO(getenv('PRISMA_RECURRENCE_TEST_DSN') ?: 'sqlite::memory:', getenv('PRISMA_RECURRENCE_TEST_USER') ?: null);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
if ($mysql) {
    // Only point the optional DSN at an empty, disposable database.
    $db->exec('CREATE TABLE tasks (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, company_id INT,
        app_id INT, title VARCHAR(500), description TEXT, due_date DATE, is_shared TINYINT DEFAULT 0,
        is_completed TINYINT DEFAULT 0, completed_by INT NULL, completed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB');
    $db->exec(file_get_contents(__DIR__ . '/../migrations/022_task_recurrence.sql'));
} else {
    $db->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, company_id INT,
        app_id INT, title TEXT, description TEXT, due_date TEXT, is_shared INT DEFAULT 0,
        is_completed INT DEFAULT 0, completed_by INT, completed_at TEXT,
        recurrence_unit TEXT, recurrence_interval INT, recurrence_parent_id INT UNIQUE, recurrence_snapshot TEXT)');
}
$db->exec('CREATE TABLE task_shares (task_id INT, user_id INT, seen_at TEXT DEFAULT NULL)');
$db->exec('CREATE TABLE task_attachments (task_id INT)');
$db->exec('CREATE TABLE notifications (task_id INT)');
$owner = ['id' => 7, 'company_id' => 1];
$id = create_task_record($db, $owner, ['title' => 'Revisión', 'description' => 'Ver mejora #12',
    'app_id' => 3, 'due_date' => '2020-01-01', 'recurrence_unit' => 'week', 'recurrence_interval' => 2]);
$db->exec("INSERT INTO task_shares (task_id, user_id, seen_at) VALUES ({$id}, 8, '2026-11-01')");
$task = $db->query("SELECT * FROM tasks WHERE id = {$id}")->fetch();
$completed = new DateTimeImmutable('2026-11-09', new DateTimeZone('Europe/Madrid'));
$db->beginTransaction();
$nextId = create_next_task_occurrence($db, $task, $completed);
$db->commit();
$next = $db->query("SELECT * FROM tasks WHERE id = {$nextId}")->fetch();
same('2026-11-23', $next['due_date'], 'next date is based on completion, not old due date');
foreach (['user_id', 'company_id', 'app_id', 'title', 'description', 'is_shared', 'recurrence_unit', 'recurrence_interval'] as $field) {
    same($task[$field], $next[$field], "next occurrence preserves {$field}");
}
same(0, (int) $next['is_completed'], 'next occurrence is pending');
same(null, $next['completed_at'], 'next occurrence has no completion date');
same(null, $next['completed_by'], 'next occurrence has no completing actor');
same(8, (int) $db->query("SELECT user_id FROM task_shares WHERE task_id = {$nextId}")->fetchColumn(), 'individual assignment preserved');
same(null, $db->query("SELECT seen_at FROM task_shares WHERE task_id = {$nextId}")->fetchColumn(), 'seen mark starts empty');
try {
    create_next_task_occurrence($db, $task, $completed);
    throw new RuntimeException('duplicate accepted');
} catch (PDOException $e) { same(2, (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'database forbids a second successor'); }

$db->exec("UPDATE tasks SET title = 'Editada por otra persona' WHERE id = {$nextId}");
rejects(fn() => undo_next_task_occurrence($db, $id), 'undo preserves modified successor');
$stmt = $db->prepare('UPDATE tasks SET title = ?, is_completed = 1 WHERE id = ?');
$stmt->execute([$task['title'], $nextId]);
rejects(fn() => undo_next_task_occurrence($db, $id), 'undo preserves completed successor');
$db->exec("UPDATE tasks SET is_completed = 0 WHERE id = {$nextId}");
$db->exec("INSERT INTO task_attachments (task_id) VALUES ({$nextId})");
rejects(fn() => undo_next_task_occurrence($db, $id), 'undo preserves successor with new attachments');
$db->exec('DELETE FROM task_attachments');
$db->exec("INSERT INTO notifications (task_id) VALUES ({$nextId})");
$db->beginTransaction();
undo_next_task_occurrence($db, $id);
$db->commit();
same(1, (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'undo removes untouched successor');
same(0, (int) $db->query("SELECT COUNT(*) FROM task_shares WHERE task_id = {$nextId}")->fetchColumn(), 'undo removes successor shares');
same(0, (int) $db->query('SELECT COUNT(*) FROM notifications')->fetchColumn(), 'undo removes successor notices');
same(1, (int) $db->query('SELECT COUNT(*) FROM task_shares')->fetchColumn(), 'undo preserves source assignment');
$db->beginTransaction();
create_next_task_occurrence($db, $task, $completed);
$db->rollBack();
same(1, (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'failed transaction leaves no successor or shares');
same(1, (int) $db->query('SELECT COUNT(*) FROM task_shares')->fetchColumn(), 'rolled-back shares do not survive');
$task['recurrence_unit'] = null;
same(null, create_next_task_occurrence($db, $task, $completed), 'ordinary completion creates no successor');
echo "Task recurrence completed successfully.\n";

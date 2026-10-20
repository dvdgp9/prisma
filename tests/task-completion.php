<?php

// Quién completa cada tarea y aviso "X ha completado una tarea" (20 oct 2026): lógica con SQLite + contratos.
require_once __DIR__ . '/../includes/task-shares.php';

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\n");
        fwrite(STDERR, '  expected: ' . var_export($expected, true) . "\n");
        fwrite(STDERR, '  actual:   ' . var_export($actual, true) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

function read_project_file($relativePath)
{
    $path = __DIR__ . '/../' . $relativePath;
    return is_file($path) ? file_get_contents($path) : '';
}

function assert_file_contains($relativePath, $needles, $message)
{
    $contents = read_project_file($relativePath);

    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            fwrite(STDERR, "FAIL: {$message} ({$relativePath} is missing: {$needle})\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

foreach (['create_task_completion_notifications', 'clear_task_completion_notifications'] as $fn) {
    if (!function_exists($fn)) {
        fwrite(STDERR, "FAIL: includes/task-shares.php is missing {$fn}()\n");
        exit(1);
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, company_id INTEGER NOT NULL, title TEXT, is_shared INTEGER DEFAULT 0, is_completed INTEGER DEFAULT 0)');
$db->exec('CREATE TABLE task_shares (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER NOT NULL, user_id INTEGER NOT NULL, seen_at TEXT NULL)');
$db->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL, request_id INTEGER NULL, note_id INTEGER NULL, task_id INTEGER NULL, triggered_by INTEGER NULL, message TEXT, is_read INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');

$db->exec("INSERT INTO tasks (id, user_id, company_id, title, is_shared) VALUES
    (100, 7, 1, 'Revisar textos', 0),
    (101, 7, 1, 'Tarea del equipo', 1),
    (102, 7, 1, 'Privada de David', 0)");
$db->exec('INSERT INTO task_shares (task_id, user_id) VALUES (100, 8), (100, 9), (101, 8)');

$david = ['id' => 7, 'username' => 'david', 'full_name' => 'David Gutiérrez'];
$rebecca = ['id' => 8, 'username' => 'rebecca', 'full_name' => 'Rebecca Ruiz'];

function task_row($db, $id)
{
    $stmt = $db->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function completion_notices($db, $taskId)
{
    $stmt = $db->prepare("SELECT user_id, triggered_by, message, is_read FROM notifications WHERE type = 'task_completion' AND task_id = ? ORDER BY user_id, id");
    $stmt->execute([$taskId]);
    return array_map(fn($r) => [(int) $r['user_id'], (int) $r['triggered_by'], $r['message'], (int) $r['is_read']], $stmt->fetchAll());
}

// Rebecca completa una tarea de David compartida con ella y con Luis: avisa a David y a Luis, no a ella.
assert_same(2, create_task_completion_notifications($db, task_row($db, 100), $rebecca), 'two people are told');
assert_same([
    [7, 8, 'Rebecca Ruiz ha completado una tarea', 0],
    [9, 8, 'Rebecca Ruiz ha completado una tarea', 0],
], completion_notices($db, 100), 'the creator and the other person get the notice, not who completed it');

// Reabrir quita el aviso; completarla otra vez deja uno solo por persona.
$db->exec("INSERT INTO notifications (user_id, type, task_id, triggered_by, message) VALUES (8, 'task_assignment', 100, 7, 'Asignada')");
clear_task_completion_notifications($db, 100);
assert_same([], completion_notices($db, 100), 'reopening removes the completion notices');
assert_same('1', (string) $db->query("SELECT COUNT(*) FROM notifications WHERE type = 'task_assignment'")->fetchColumn(), 'other notices of the task stay');
create_task_completion_notifications($db, task_row($db, 100), $rebecca);
$db->exec("UPDATE notifications SET is_read = 1 WHERE type = 'task_completion'");
create_task_completion_notifications($db, task_row($db, 100), $rebecca);
assert_same([
    [7, 8, 'Rebecca Ruiz ha completado una tarea', 0],
    [9, 8, 'Rebecca Ruiz ha completado una tarea', 0],
], completion_notices($db, 100), 'completing it again leaves a single, unread notice per person');

// David completa su propia tarea compartida: avisa a Rebecca y a Luis.
clear_task_completion_notifications($db, 100);
assert_same(2, create_task_completion_notifications($db, task_row($db, 100), $david), 'the creator completing it tells the people it was shared with');
assert_same([8, 9], array_map(fn($n) => $n[0], completion_notices($db, 100)), 'Rebecca and Luis are told');

// Tarea de "Todo el equipo": solo a quien la creó (no a toda la empresa).
assert_same(1, create_task_completion_notifications($db, task_row($db, 101), $rebecca), 'team task: only one notice');
assert_same([[7, 8, 'Rebecca Ruiz ha completado una tarea', 0]], completion_notices($db, 101), 'team task: only the creator is told');
assert_same(0, create_task_completion_notifications($db, task_row($db, 101), $david), 'team task completed by its creator: nobody is told');

// Tarea privada completada por quien la creó: nadie.
assert_same(0, create_task_completion_notifications($db, task_row($db, 102), $david), 'private task: no notice');

// ---- Contratos ----
assert_file_contains('api/tasks.php', [
    'LEFT JOIN users cb ON t.completed_by = cb.id',
    'cb.full_name as completed_by_name',
    'completed_by = ?',
    'completed_by = NULL',
    'create_task_completion_notifications($db, $access[\'task\'], $user)',
    'clear_task_completion_notifications($db, $input[\'id\'])',
], 'tasks API stores who completed a task and notifies the others');

assert_file_contains('migrations/019_task_completed_by.sql', [
    'ALTER TABLE tasks',
    'ADD COLUMN completed_by INT NULL;',
    'SET t.completed_by = t.user_id, t.updated_at = t.updated_at',
    'NOT EXISTS (SELECT 1 FROM task_shares s WHERE s.task_id = t.id)',
], 'migration 019 adds completed_by and fills it for private completed tasks');
assert_file_contains('migrations/README.md', ['Migración 019', '019_task_completed_by.sql'], 'migration 019 is documented');

// Campana: icono de completada, entra en "Estado" y abre la tarea (como el resto de avisos de tarea).
assert_file_contains('assets/js/sidebar.js', [
    "task_completion: { iconClass: 'completion', iconName: 'iconoir-check-circle' }",
    "n.type === 'completion' || n.type === 'status_change' || n.type === 'task_completion'",
], 'the bell shows task completions under "Estado"');
assert_file_contains('includes/sidebar.php', ['/assets/js/sidebar.js?v=2.1'], 'sidebar loads the new sidebar.js');

fwrite(STDOUT, "Task completion completed successfully.\n");

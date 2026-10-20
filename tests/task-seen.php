<?php

// "Visto" en tareas asignadas + aviso en la campana (20 oct 2026): lógica con SQLite.
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

function assert_file_not_contains($relativePath, $needles, $message)
{
    $contents = read_project_file($relativePath);

    foreach ($needles as $needle) {
        if (strpos($contents, $needle) !== false) {
            fwrite(STDERR, "FAIL: {$message} ({$relativePath} still has: {$needle})\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

foreach (['replace_task_shares', 'create_task_assignment_notifications', 'mark_tasks_seen', 'task_share_status'] as $fn) {
    if (!function_exists($fn)) {
        fwrite(STDERR, "FAIL: includes/task-shares.php is missing {$fn}()\n");
        exit(1);
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, company_id INTEGER, username TEXT, full_name TEXT, is_active INTEGER DEFAULT 1)');
$db->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, company_id INTEGER NOT NULL, title TEXT, is_shared INTEGER DEFAULT 0)');
$db->exec('CREATE TABLE task_shares (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER NOT NULL, user_id INTEGER NOT NULL, seen_at TEXT NULL)');
$db->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL, request_id INTEGER NULL, note_id INTEGER NULL, task_id INTEGER NULL, triggered_by INTEGER NULL, message TEXT, is_read INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');

$db->exec("INSERT INTO users (id, company_id, username, full_name) VALUES
    (7, 1, 'david', 'David Gutiérrez'),
    (8, 1, 'rebecca', 'Rebecca Ruiz'),
    (9, 1, 'luis', 'Luis Ortega'),
    (50, 2, 'otra', 'De otra empresa')");
$db->exec("INSERT INTO tasks (id, user_id, company_id, title, is_shared) VALUES
    (100, 7, 1, 'Revisar el informe', 0),
    (101, 7, 1, 'Otra tarea', 0)");
$db->exec("INSERT INTO task_shares (task_id, user_id, seen_at) VALUES (100, 8, '2026-10-20 09:00:00'), (101, 8, NULL)");

$david = ['id' => 7, 'company_id' => 1, 'username' => 'david', 'full_name' => 'David Gutiérrez'];

function share_rows($db, $taskId)
{
    $stmt = $db->prepare('SELECT user_id, seen_at FROM task_shares WHERE task_id = ? ORDER BY user_id');
    $stmt->execute([$taskId]);
    return array_map(fn($r) => [(int) $r['user_id'], $r['seen_at']], $stmt->fetchAll());
}

function assignment_notices($db, $taskId)
{
    $stmt = $db->prepare("SELECT user_id, triggered_by, message, is_read FROM notifications WHERE type = 'task_assignment' AND task_id = ? ORDER BY user_id, id");
    $stmt->execute([$taskId]);
    return array_map(fn($r) => [(int) $r['user_id'], (int) $r['triggered_by'], $r['message'], (int) $r['is_read']], $stmt->fetchAll());
}

// ---- Cambiar con quién se comparte: por diferencias, sin perder el visto ----
$added = replace_task_shares($db, $david, 100, false, [8, 9, '9', 50, 7, 0, 'x']);
assert_same([9], $added, 'only the newly added teammate is returned (no repeats, other companies or the owner)');
assert_same([[8, '2026-10-20 09:00:00'], [9, null]], share_rows($db, 100), 'the person who already had it keeps their "seen"; the new one starts unseen');

// Aviso en la campana para quien se acaba de añadir.
assert_same(1, create_task_assignment_notifications($db, 100, [9, 7], $david), 'one notice per new person, never to whoever assigns it');
assert_same([[9, 7, 'David Gutiérrez te ha asignado una tarea', 0]], assignment_notices($db, 100), 'notice says who assigned it and points to the task');

// Repetir el aviso (quitar y volver a poner) no acumula avisos: queda uno, sin leer.
$db->exec("UPDATE notifications SET is_read = 1 WHERE task_id = 100");
create_task_assignment_notifications($db, 100, [9], $david);
assert_same([[9, 7, 'David Gutiérrez te ha asignado una tarea', 0]], assignment_notices($db, 100), 'a repeated notice replaces the previous one');

// Quitar a una persona borra su fila y su aviso; el resto sigue igual.
$db->exec("INSERT INTO notifications (user_id, type, task_id, triggered_by, message) VALUES (8, 'task_assignment', 100, 7, 'Antiguo')");
assert_same([], replace_task_shares($db, $david, 100, false, [9]), 'removing someone adds nobody');
assert_same([[9, null]], share_rows($db, 100), 'removed person no longer has the task');
assert_same([[9, 7, 'David Gutiérrez te ha asignado una tarea', 0]], assignment_notices($db, 100), 'the removed person loses the notice too');

// Todo el equipo: sin filas por persona (y sin avisos de asignación).
assert_same([], replace_task_shares($db, $david, 100, true, [8, 9]), 'whole team adds nobody by name');
assert_same([], share_rows($db, 100), 'whole team clears the individual shares');
assert_same('1', (string) $db->query('SELECT is_shared FROM tasks WHERE id = 100')->fetchColumn(), 'whole team flag is stored');
assert_same([], assignment_notices($db, 100), 'switching to the whole team removes the individual notices');

assert_same([9], replace_task_shares($db, $david, 100, false, [9]), 'back to one person: added again');
assert_same('0', (string) $db->query('SELECT is_shared FROM tasks WHERE id = 100')->fetchColumn(), 'whole team flag is cleared');

// ---- Marcar como vista (solo las filas de quien mira) ----
create_task_assignment_notifications($db, 100, [9], $david);
$db->exec("INSERT INTO notifications (user_id, type, request_id, triggered_by, message) VALUES (9, 'comment', 5, 7, 'Comentario sin leer')");
$result = mark_tasks_seen($db, 9, [100, 101, 'x', -1, 100]);
assert_same(['seen' => 1, 'notifications_read' => 1], $result, 'marks the shares of this person as seen and reads the matching notice');
$rows = share_rows($db, 100);
assert_same(true, $rows[0][1] !== null, 'seen_at is stored');
assert_same([[8, null]], share_rows($db, 101), 'other people\'s shares are untouched');
assert_same('0', (string) $db->query("SELECT is_read FROM notifications WHERE type = 'comment'")->fetchColumn(), 'other notices stay unread');

$db->exec("UPDATE task_shares SET seen_at = '2026-10-20 10:42:00' WHERE task_id = 100 AND user_id = 9");
assert_same(['seen' => 0, 'notifications_read' => 0], mark_tasks_seen($db, 9, [100]), 'seeing it again changes nothing');
assert_same([[9, '2026-10-20 10:42:00']], share_rows($db, 100), 'the first time it was seen is kept');
assert_same(['seen' => 0, 'notifications_read' => 0], mark_tasks_seen($db, 9, []), 'nothing to mark');

// ---- Estado de cada persona, para pintar ✓ / ✓✓ ----
assert_same([
    100 => [['user_id' => 9, 'seen_at' => '2026-10-20 10:42:00']],
    101 => [['user_id' => 8, 'seen_at' => null]],
], task_share_status($db, [100, 101, 999]), 'share status per task, with when each person saw it');
assert_same([], task_share_status($db, []), 'no tasks, no status');

// ---- Contratos: los endpoints usan estas funciones ----
assert_file_contains('api/task-shares.php', [
    "require_once __DIR__ . '/../includes/task-shares.php';",
    'replace_task_shares($db, $user, $taskId, $shareWithAll, $userIds)',
    'create_task_assignment_notifications($db, $taskId, $added, $user)',
    "case 'PUT':",
    "mark_tasks_seen(\$db, \$user['id'], \$input['seen_task_ids'] ?? [])",
    'ts.seen_at',
], 'task shares API: diff-based sharing, notices and "seen"');
assert_file_not_contains('api/task-shares.php', [
    'DELETE FROM task_shares WHERE task_id = ?");',
], 'task shares API no longer wipes every share on each change');

assert_file_contains('api/tasks.php', [
    "require_once __DIR__ . '/../includes/task-shares.php';",
    'task_share_status($db,',
    "['share_status']",
    "DELETE FROM notifications WHERE task_id = ?",
], 'tasks API returns who has seen each task and cleans notices on delete');

assert_file_contains('migrations/017_task_seen.sql', [
    'ALTER TABLE task_shares',
    'ADD COLUMN seen_at DATETIME NULL DEFAULT NULL',
    'UPDATE task_shares SET seen_at = NOW() WHERE seen_at IS NULL',
    'ALTER TABLE notifications',
    'ADD COLUMN task_id INT NULL,',
    'ADD INDEX idx_task (task_id)',
], 'migration 017 adds seen_at and notifications.task_id');
assert_file_contains('migrations/README.md', ['Migración 017', '017_task_seen.sql'], 'migration 017 is documented');

// Producción: `type` era ENUM sin los tipos nuevos y `request_id` NOT NULL (avisos de notas y tareas sin mejora).
assert_file_contains('migrations/018_notifications_types.sql', [
    'ALTER TABLE notifications',
    'MODIFY COLUMN type VARCHAR(64) NOT NULL',
    'MODIFY COLUMN request_id INT(11) NULL',
    "UPDATE notifications SET request_id = NULL WHERE request_id = 0;",
    "SET type = 'note_share'",
    "SET type = 'completion'",
    "SET type = 'status_change'",
], 'migration 018 frees notification types, allows notices without a request and repairs empty types');
assert_file_contains('migrations/README.md', ['Migración 018', '018_notifications_types.sql'], 'migration 018 is documented');
assert_file_contains('api/task-shares.php', [
    "error_log('Failed to create task assignment notifications for task ",
], 'a failed notice does not make the share fail');

fwrite(STDOUT, "Task seen completed successfully.\n");

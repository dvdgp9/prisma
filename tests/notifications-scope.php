<?php

require_once __DIR__ . '/../includes/notifications.php';

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

function assert_file_contains($relativePath, $needles, $message)
{
    $contents = file_get_contents(__DIR__ . '/../' . $relativePath);

    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            fwrite(STDERR, "FAIL: {$message} ({$relativePath} is missing: {$needle})\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

function notification_ids($rows)
{
    $ids = array_map(fn($row) => (int) $row['id'], $rows);
    sort($ids);
    return $ids;
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, full_name TEXT)');
$db->exec('CREATE TABLE requests (id INTEGER PRIMARY KEY, app_id INTEGER, title TEXT)');
$db->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY, company_id INTEGER, user_id INTEGER, title TEXT, is_shared INTEGER DEFAULT 0)');
$db->exec('CREATE TABLE note_shares (id INTEGER PRIMARY KEY AUTOINCREMENT, note_id INTEGER, user_id INTEGER)');
$db->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, company_id INTEGER, user_id INTEGER, title TEXT, is_shared INTEGER DEFAULT 0)');
$db->exec('CREATE TABLE task_shares (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER, user_id INTEGER, seen_at TEXT NULL)');
$db->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, type TEXT NOT NULL, request_id INTEGER NULL, note_id INTEGER NULL, task_id INTEGER NULL, triggered_by INTEGER NULL, message TEXT, is_read INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');

$db->exec("INSERT INTO users VALUES (7, 'yo', 'Yo Mismo'), (8, 'ana', 'Ana Ruiz')");
$db->exec("INSERT INTO requests VALUES (10, 1, 'Mejora de mi app'), (11, 2, 'Mejora de otra app')");
$db->exec("INSERT INTO notes VALUES
    (20, 1, 8, 'Compartida conmigo', 0),
    (21, 1, 8, 'Ya no compartida', 0),
    (22, 1, 8, 'Del equipo', 1),
    (23, 2, 8, 'Del equipo de otra empresa', 1)");
$db->exec('INSERT INTO note_shares (note_id, user_id) VALUES (20, 7)');
$db->exec("INSERT INTO tasks VALUES
    (30, 1, 8, 'Tarea para mí', 0),
    (31, 1, 8, 'Ya no es para mí', 0),
    (32, 2, 8, 'Del equipo de otra empresa', 1)");
$db->exec('INSERT INTO task_shares (task_id, user_id) VALUES (30, 7)');
$db->exec("INSERT INTO notifications (id, user_id, type, request_id, note_id, triggered_by, message, is_read, created_at) VALUES
    (1, 7, 'comment',    10,   NULL, 8, 'Comentario', 0, '2026-10-19 09:00:00'),
    (2, 7, 'comment',    11,   NULL, 8, 'De otra app', 0, '2026-10-19 09:01:00'),
    (3, 7, 'note_share', NULL, 20,   8, 'Ana Ruiz te ha compartido una nota', 0, '2026-10-19 09:02:00'),
    (4, 7, 'note_share', NULL, 21,   8, 'Ana Ruiz te ha compartido una nota', 0, '2026-10-19 09:03:00'),
    (5, 7, 'note_share', NULL, 99,   8, 'Nota borrada', 0, '2026-10-19 09:04:00'),
    (6, 8, 'note_share', NULL, 20,   7, 'De otra persona', 0, '2026-10-19 09:05:00'),
    (7, 7, 'note_share', NULL, 22,   8, 'Nota del equipo', 1, '2026-10-19 09:06:00'),
    (8, 7, 'note_share', NULL, 23,   8, 'Equipo de otra empresa', 0, '2026-10-19 09:07:00'),
    (9, 7, 'comment',    999,  NULL, 8, 'Mejora borrada', 0, '2026-10-19 09:08:00')");
// Avisos de tareas asignadas: solo mientras la tarea sigue siendo visible para quien lo recibe.
$db->exec("INSERT INTO notifications (id, user_id, type, task_id, triggered_by, message, is_read, created_at) VALUES
    (10, 7, 'task_assignment', 30,  8, 'Ana Ruiz te ha asignado una tarea', 0, '2026-10-19 09:09:00'),
    (11, 7, 'task_assignment', 31,  8, 'Ya no compartida', 0, '2026-10-19 09:10:00'),
    (12, 7, 'task_assignment', 999, 8, 'Tarea borrada', 0, '2026-10-19 09:11:00'),
    (13, 7, 'task_assignment', 32,  8, 'Equipo de otra empresa', 0, '2026-10-19 09:12:00'),
    (14, 8, 'task_assignment', 30,  7, 'De otra persona', 0, '2026-10-19 09:13:00')");

$me = ['id' => 7, 'company_id' => 1];

// Usuario con acceso solo a la app 1: avisos de esa app + de notas que todavía puede abrir.
$rows = fetch_user_notifications($db, $me, [1], 50);
assert_same([1, 3, 7, 10], notification_ids($rows), 'inbox shows request notices of my apps, notes and tasks I can still open');
assert_same([10, 7, 3, 1], array_map(fn($r) => (int) $r['id'], $rows), 'newest first');
$byId = array_column($rows, null, 'id');
assert_same('Compartida conmigo', $byId[3]['note_title'], 'note notices carry the note title');
assert_same('Mejora de mi app', $byId[1]['request_title'], 'request notices keep the request title');
assert_same('Ana Ruiz', $byId[3]['triggered_by_name'], 'notices carry who triggered them');
assert_same('Tarea para mí', $byId[10]['task_title'], 'task notices carry the task title');
assert_same(3, count_unread_notifications($db, $me, [1]), 'unread badge uses the same scope (1 request + 1 note + 1 task)');

// Sin apps asignadas: siguen llegando los avisos de notas.
assert_same([3, 7, 10], notification_ids(fetch_user_notifications($db, $me, [], 50)), 'without apps, note and task notices still show');
assert_same(2, count_unread_notifications($db, $me, []), 'without apps, unread counts note and task notices');

// Superadmin (null = todas las apps): todas las mejoras que existen.
assert_same([1, 2, 3, 7, 10], notification_ids(fetch_user_notifications($db, $me, null, 50)), 'superadmin sees every existing request notice');

assert_same([10], notification_ids(fetch_user_notifications($db, $me, [1], 1)), 'limit applies');

// El endpoint usa estas funciones con las apps del usuario.
assert_file_contains('api/notifications.php', [
    "require_once __DIR__ . '/../includes/notifications.php';",
    'get_user_apps()',
    'fetch_user_notifications($db, $user, $allowedAppIds, $limit)',
    'count_unread_notifications($db, $user, $allowedAppIds)',
], 'notifications API scopes notices with the shared helpers');

// Panel de Notificaciones: avisos de nota y de tarea con su icono, título y enlace.
assert_file_contains('assets/js/sidebar.js', [
    "note_share: { iconClass: 'note', iconName: 'iconoir-page' }",
    "task_assignment: { iconClass: 'assignment', iconName: 'iconoir-task-list' }",
    "handleNotificationClick(\${n.id}, \${n.request_id || 'null'}, \${n.note_id || 'null'}, \${n.task_id || 'null'})",
    'n.task_id ? n.task_title : n.note_id ? n.note_title : n.request_title',
    '/notes.php?id=${noteId}',
    '/tasks.php?task=${taskId}',
    "n.type === 'assignment' || n.type === 'task_assignment'",
], 'inbox renders and opens note and task notices');
assert_file_contains('includes/sidebar.php', ['<option value="note_share">Notas</option>', '/assets/js/sidebar.js?v=2.1'], 'inbox type filter has Notas');
assert_file_contains('assets/css/styles.css', ['.inbox-item-icon.note'], 'note notices have their own icon colour');

fwrite(STDOUT, "Notifications scope completed successfully.\n");

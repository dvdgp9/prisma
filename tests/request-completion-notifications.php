<?php

require_once __DIR__ . '/../includes/request-notifications.php';

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . json_encode($expected) . "\nActual: " . json_encode($actual) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE requests (id INTEGER PRIMARY KEY, created_by INTEGER)');
$db->exec('CREATE TABLE request_assignments (id INTEGER PRIMARY KEY, request_id INTEGER, user_id INTEGER)');
$db->exec('CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    request_id INTEGER,
    triggered_by INTEGER,
    message TEXT,
    is_read INTEGER DEFAULT 0
)');

$db->exec('INSERT INTO requests (id, created_by) VALUES (100, 10)');
$db->exec('INSERT INTO request_assignments (id, request_id, user_id) VALUES
    (1, 100, 20),
    (2, 100, 21)');
$db->exec("INSERT INTO notifications (user_id, type, request_id, triggered_by, message) VALUES
    (20, 'assignment', 100, 30, 'Asignación anterior'),
    (20, 'assignment', 100, 31, 'Asignación vigente'),
    (21, 'assignment', 100, 31, 'Mismo asignador'),
    (22, 'assignment', 100, 32, 'Asignación retirada')");

assert_same(
    [10, 31],
    get_request_completion_recipient_ids($db, 100, 40),
    'completion targets the creator and latest assigner without duplicates'
);

assert_same(
    [10],
    get_request_completion_recipient_ids($db, 100, 31),
    'the actor completing the request is excluded'
);

$created = create_request_completion_notifications($db, 100, 40, 'Ada Lovelace');
assert_same(2, $created, 'one completion notification is created per recipient');

$rows = $db->query("SELECT user_id, type, triggered_by, message FROM notifications WHERE type = 'completion' ORDER BY user_id")->fetchAll(PDO::FETCH_ASSOC);
assert_same([
    [
        'user_id' => 10,
        'type' => 'completion',
        'triggered_by' => 40,
        'message' => 'Ada Lovelace ha completado la mejora'
    ],
    [
        'user_id' => 31,
        'type' => 'completion',
        'triggered_by' => 40,
        'message' => 'Ada Lovelace ha completado la mejora'
    ]
], $rows, 'completion notification payload is correct');

$created = create_request_status_change_notifications($db, 100, 40, 'Ada Lovelace', 'in_progress');
assert_same(2, $created, 'status change notifies the creator and latest assigner');

$rows = $db->query("SELECT user_id, type, message FROM notifications WHERE type = 'status_change' ORDER BY user_id")->fetchAll(PDO::FETCH_ASSOC);
assert_same([
    [
        'user_id' => 10,
        'type' => 'status_change',
        'message' => 'Ada Lovelace ha puesto la mejora en curso'
    ],
    [
        'user_id' => 31,
        'type' => 'status_change',
        'message' => 'Ada Lovelace ha puesto la mejora en curso'
    ]
], $rows, 'status change notification payload is correct');

$created = create_request_status_change_notifications($db, 100, 40, 'Ada Lovelace', 'completed');
assert_same(2, $created, 'completed status routes through completion notifications');

assert_same(
    'primer comentario con un texto largo que supera el límite y…',
    get_notification_snippet("  primer   comentario\ncon un texto largo que supera el límite y debería truncarse en algún punto razonable", 60),
    'snippet collapses whitespace and truncates with ellipsis'
);

fwrite(STDOUT, "Request completion notification matrix completed successfully.\n");

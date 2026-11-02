<?php
require_once __DIR__ . '/../includes/work-records.php';

function same($expected, $actual, $message)
{
    if ($expected !== $actual) throw new RuntimeException($message . ': ' . var_export($actual, true));
    echo "PASS: {$message}\n";
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE requests (id INTEGER PRIMARY KEY, app_id INTEGER, title TEXT, description TEXT, priority TEXT, difficulty TEXT, status TEXT, created_by INTEGER, requester_name TEXT, requester_email TEXT, due_date TEXT)');
$db->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER, company_id INTEGER, app_id INTEGER, title TEXT, description TEXT, due_date TEXT, is_shared INTEGER)');
$db->exec('CREATE TABLE request_checklist_items (id INTEGER PRIMARY KEY, request_id INTEGER, title TEXT, is_completed INTEGER, position INTEGER, created_by INTEGER)');
$user = ['id' => 7, 'company_id' => 2];
$requestId = create_request_record($db, $user, ['app_id' => 3, 'title' => 'Exportar', 'description' => 'Detalle', 'due_date' => '2026-11-06']);
$row = $db->query('SELECT * FROM requests')->fetch();
same('2026-11-06', $row['due_date'], 'normal request creation keeps date');
same('pending', $row['status'], 'normal request default status retained');
same('medium', $row['priority'], 'normal request default priority retained');
same(7, $row['created_by'], 'creator comes from authenticated user');
create_request_checklist_record($db, $user, $requestId, '  Paso uno  ');
create_request_checklist_record($db, $user, $requestId, 'Paso dos');
same(['Paso uno', 'Paso dos'], $db->query('SELECT title FROM request_checklist_items ORDER BY position')->fetchAll(PDO::FETCH_COLUMN), 'checklist trimmed and ordered');
same([1, 2], $db->query('SELECT position FROM request_checklist_items ORDER BY position')->fetchAll(PDO::FETCH_COLUMN), 'checklist positions increment');
foreach ([' ', str_repeat('x', 501)] as $invalid) {
    try { create_request_checklist_record($db, $user, $requestId, $invalid); throw new RuntimeException('invalid checklist accepted'); }
    catch (InvalidArgumentException $e) { same(2, (int) $db->query('SELECT COUNT(*) FROM request_checklist_items')->fetchColumn(), 'invalid checklist never inserted'); }
}
$taskId = create_task_record($db, $user, ['title' => '  Llamar  ', 'is_shared' => true, 'app_id' => 3, 'due_date' => '2026-11-04']);
$row = $db->query('SELECT * FROM tasks')->fetch();
same(['user_id' => 7, 'company_id' => 2, 'title' => 'Llamar', 'is_shared' => 1], array_intersect_key($row, array_flip(['user_id', 'company_id', 'title', 'is_shared'])), 'task retains company, owner and sharing');

// Shared primitives do not commit: IA4 can roll back the complete card on failure.
$db->beginTransaction();
try {
    $temporary = create_request_record($db, $user, ['app_id' => 3, 'title' => 'Atomic card']);
    create_request_checklist_record($db, $user, $temporary, 'Valid');
    create_request_checklist_record($db, $user, $temporary, '');
} catch (InvalidArgumentException $e) {
    $db->rollBack();
}
same(1, (int) $db->query('SELECT COUNT(*) FROM requests')->fetchColumn(), 'failed card rolls back request');
same(2, (int) $db->query('SELECT COUNT(*) FROM request_checklist_items')->fetchColumn(), 'failed card rolls back its checklist');
same(1, (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'rollback preserves other work');
echo "Work records completed successfully.\n";

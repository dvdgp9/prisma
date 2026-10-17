<?php

require_once __DIR__ . '/../includes/auth.php';

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

function assert_file_contains($relativePath, $needles)
{
    $contents = file_get_contents(__DIR__ . '/../' . $relativePath);

    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            fwrite(STDERR, "FAIL: {$relativePath} is missing: {$needle}\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "PASS: {$relativePath} uses the task access contract\n");
}

function assert_file_not_contains($relativePath, $needles)
{
    $contents = file_get_contents(__DIR__ . '/../' . $relativePath);

    foreach ($needles as $needle) {
        if (strpos($contents, $needle) !== false) {
            fwrite(STDERR, "FAIL: {$relativePath} still contains: {$needle}\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "PASS: {$relativePath} has no legacy task access checks\n");
}

$ownerCaps = ['view' => true, 'edit' => true, 'complete' => true, 'attach' => true, 'delete' => true, 'share' => true];
$sharedCaps = ['view' => true, 'edit' => true, 'complete' => true, 'attach' => true, 'delete' => false, 'share' => false];
$noCaps = ['view' => false, 'edit' => false, 'complete' => false, 'attach' => false, 'delete' => false, 'share' => false];

assert_same($ownerCaps, task_capabilities_for_access(true, false), 'owner can do everything');
assert_same($ownerCaps, task_capabilities_for_access(true, true), 'owner keeps full control when the task is also shared');
assert_same($sharedCaps, task_capabilities_for_access(false, true), 'shared users can work on the task but not delete or reshare it');
assert_same($noCaps, task_capabilities_for_access(false, false), 'unrelated users get nothing');

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, company_id INTEGER NOT NULL, title TEXT, is_shared INTEGER DEFAULT 0, is_completed INTEGER DEFAULT 0)');
$db->exec('CREATE TABLE task_shares (task_id INTEGER NOT NULL, user_id INTEGER NOT NULL)');
$db->exec("INSERT INTO tasks (id, user_id, company_id, title, is_shared, is_completed) VALUES
    (100, 7, 1, 'Own completed task', 0, 1),
    (101, 8, 1, 'Team task', 1, 0),
    (102, 8, 1, 'Shared with user 7 only', 0, 0),
    (103, 8, 1, 'Private task of user 8', 0, 0),
    (104, 9, 2, 'Team task in another company', 1, 0)");
$db->exec('INSERT INTO task_shares (task_id, user_id) VALUES (102, 7), (103, 5)');

$_SESSION['user_id'] = 7;
$_SESSION['username'] = 'tenant-a-user';
$_SESSION['role'] = 'user';
$_SESSION['company_id'] = 1;
$_SESSION['company_name'] = 'Tenant A';
$_SESSION['full_name'] = 'Tenant A User';

$own = get_task_access(100, $db);
assert_same($ownerCaps, $own['capabilities'], 'owner gets full access to an own completed task');
assert_same(100, (int) $own['task']['id'], 'access result includes the task row');

assert_same($sharedCaps, get_task_access(101, $db)['capabilities'], 'team-shared task in the same company is accessible');
assert_same($sharedCaps, get_task_access(102, $db)['capabilities'], 'task shared individually with the user is accessible');
assert_same(false, get_task_access(103, $db), 'private task of a colleague is hidden');
assert_same(false, get_task_access(104, $db), 'team task from another company is hidden');
assert_same(false, get_task_access(999, $db), 'missing task returns false');
assert_same(false, get_task_access(0, $db), 'invalid task ID returns false');
assert_same(false, get_task_access('1 OR 1=1', $db), 'non-numeric task ID returns false');

assert_same('complete', task_capability_for_update(['id' => 1, 'is_completed' => true]), 'toggling completion needs complete');
assert_same('edit', task_capability_for_update(['id' => 1, 'title' => 'x', 'due_date' => null]), 'editing fields needs edit');
assert_same('edit', task_capability_for_update(['id' => 1, 'title' => 'x', 'is_completed' => false]), 'editing plus completion needs edit');
assert_same('share', task_capability_for_update(['id' => 1, 'title' => 'x', 'is_shared' => true]), 'changing team sharing needs share');

assert_file_contains('api/tasks.php', [
    "require_task_capability(\$_GET['id'], 'view')",
    "require_task_capability(\$input['id'], task_capability_for_update(\$input))",
    "require_task_capability(\$input['id'], 'delete')",
]);
assert_file_contains('api/task-attachments.php', [
    "require_task_capability(\$_GET['task_id'], 'view')",
    "require_task_capability(\$_POST['task_id'], 'attach')",
    "require_task_capability(\$attachment['task_id'], 'delete')",
]);
assert_file_contains('api/task-shares.php', [
    "require_task_capability(\$taskId, 'view')",
    "require_task_capability(\$taskId, 'share')",
    "require_task_capability(\$input['task_id'], 'share')",
]);
assert_file_contains('api/file.php', ["get_task_access(\$row['task_id'])"]);
assert_file_contains('assets/js/tasks.js', ['/api/tasks.php?id=${taskId}']);

// UI contract: the list carries capabilities and the screen hides what the user cannot do.
assert_file_contains('api/tasks.php', ["\$task['capabilities'] = task_capabilities_for_access("]);
assert_file_contains('assets/js/tasks.js', [
    'function showErrorToast(',
    'caps.delete !== false',
    'currentTaskCaps.share',
    'currentTaskCaps.delete',
    "showErrorToast(data.error || 'No se pudo actualizar la tarea')",
    "showErrorToast(data.error || 'No se pudo eliminar la tarea')",
    "showErrorToast(data.error || 'No se pudo eliminar el archivo')",
]);
assert_file_contains('tasks.php', ['id="task-danger-zone"', 'id="share-readonly-hint"']);
assert_file_contains('assets/css/tasks.css', ['.share-selector.is-readonly', '.share-readonly-hint']);

assert_file_not_contains('api/tasks.php', ['is_shared = 1 AND company_id = ?']);
assert_file_not_contains('api/task-attachments.php', ['is_shared = 1 AND company_id = ?', 'SELECT * FROM task_attachments']);
assert_file_not_contains('api/task-shares.php', ['SELECT * FROM tasks WHERE id = ? AND user_id = ?']);

fwrite(STDOUT, "Task access matrix completed successfully.\n");

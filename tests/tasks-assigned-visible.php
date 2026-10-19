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

if (!function_exists('task_list_visibility_sql')) {
    fwrite(STDERR, "FAIL: includes/auth.php is missing task_list_visibility_sql()\n");
    exit(1);
}

$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, company_id INTEGER NOT NULL, title TEXT, is_shared INTEGER DEFAULT 0)');
$db->exec('CREATE TABLE task_shares (task_id INTEGER NOT NULL, user_id INTEGER NOT NULL)');
$db->exec("INSERT INTO tasks (id, user_id, company_id, title, is_shared) VALUES
    (100, 7, 1, 'Own task', 0),
    (101, 8, 1, 'Assigned to user 7 by user 8', 0),
    (102, 8, 1, 'Shared with the whole team', 1),
    (103, 8, 1, 'Private task of user 8', 0),
    (104, 8, 1, 'Assigned to someone else', 0)");
$db->exec('INSERT INTO task_shares (task_id, user_id) VALUES (101, 7), (104, 5)');

// Same shape as the list query in api/tasks.php, for user 7 in company 1.
function visible_task_ids($db, $includeTeam)
{
    $stmt = $db->prepare('SELECT t.id FROM tasks t WHERE t.company_id = :company_id AND ' . task_list_visibility_sql($includeTeam) . ' ORDER BY t.id');
    $stmt->execute([':company_id' => 1, ':user_id' => 7, ':user_id2' => 7]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

assert_same([100, 101], visible_task_ids($db, false), 'default list shows own tasks and the ones assigned to the user by name');
assert_same([100, 101, 102], visible_task_ids($db, true), '"Del equipo" also adds whole-team tasks');

assert_file_contains('api/tasks.php', [
    'task_list_visibility_sql($showShared)',
    "\$params[':user_id2'] = \$user['id'];",
], 'task list API uses the shared visibility filter');

fwrite(STDOUT, "Assigned tasks visibility completed successfully.\n");

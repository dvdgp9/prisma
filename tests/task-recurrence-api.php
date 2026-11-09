<?php
// Optional integration test: use a NEW disposable MariaDB database, never Prisma's database.
$dsn = getenv('PRISMA_RECURRENCE_TEST_DSN');
if (!$dsn) { echo "SKIP: set PRISMA_RECURRENCE_TEST_DSN to an empty disposable MariaDB database.\n"; exit; }
$db = new PDO($dsn, getenv('PRISMA_RECURRENCE_TEST_USER') ?: 'root', null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}
$db->exec('CREATE TABLE tasks (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, company_id INT, app_id INT,
    title VARCHAR(500), description TEXT, due_date DATE, is_shared TINYINT DEFAULT 0, is_completed TINYINT DEFAULT 0,
    completed_at DATETIME NULL, completed_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB');
$db->exec(file_get_contents(__DIR__ . '/../migrations/022_task_recurrence.sql'));
$db->exec('CREATE TABLE users (id INT PRIMARY KEY, company_id INT, username VARCHAR(80), full_name VARCHAR(80))');
$db->exec("INSERT INTO users VALUES (7,1,'owner','Autora'), (8,1,'assignee','Persona asignada'), (9,2,'outsider','Otra empresa')");
$db->exec('CREATE TABLE apps (id INT PRIMARY KEY, name VARCHAR(80))');
$db->exec('CREATE TABLE task_shares (id INT AUTO_INCREMENT PRIMARY KEY, task_id INT, user_id INT, seen_at DATETIME NULL)');
$db->exec('CREATE TABLE task_attachments (id INT AUTO_INCREMENT PRIMARY KEY, task_id INT, file_path VARCHAR(500))');
$db->exec('CREATE TABLE notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, type VARCHAR(64),
    task_id INT, triggered_by INT, message TEXT, is_read TINYINT DEFAULT 0)');

// Run the real endpoint and authorization in separate PHP processes, replacing only
// database configuration and the CLI input transport. No production configuration is used.
$dir = sys_get_temp_dir() . '/prisma-recurrence-api-' . bin2hex(random_bytes(6));
mkdir($dir);
$project = realpath(__DIR__ . '/..');
file_put_contents($dir . '/database.php', '<?php function getDB() { static $db; return $db ?? ($db = new PDO(getenv("PRISMA_RECURRENCE_TEST_DSN"), getenv("PRISMA_RECURRENCE_TEST_USER") ?: "root", null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false])); }');
$auth = file_get_contents($project . '/includes/auth.php');
$auth = str_replace("__DIR__ . '/../config/database.php'", var_export($dir . '/database.php', true), $auth);
$auth = str_replace("file_get_contents('php://input')", "file_get_contents('php://stdin')", $auth);
file_put_contents($dir . '/auth.php', $auth);
$endpoint = file_get_contents($project . '/api/tasks.php');
foreach (['auth.php', 'work-records.php', 'task-shares.php', 'task-recurrence.php'] as $file) {
    $endpoint = str_replace("__DIR__ . '/../includes/{$file}'", var_export($file === 'auth.php' ? $dir . '/auth.php' : $project . '/includes/' . $file, true), $endpoint);
}
file_put_contents($dir . '/endpoint.php', $endpoint);
file_put_contents($dir . '/run.php', <<<'PHP'
<?php
session_start();
$id = (int) $argv[2];
$_SESSION = ['user_id' => $id, 'username' => 'user' . $id, 'full_name' => 'User ' . $id,
    'company_id' => $id === 9 ? 2 : 1, 'company_name' => 'Test', 'role' => 'user'];
$_SERVER['REQUEST_METHOD'] = $argv[1];
$_GET = json_decode($argv[3], true);
require __DIR__ . '/endpoint.php';
PHP
);
function start_request($method, $input, $actor = 7, $query = []) {
    global $dir;
    $process = proc_open([PHP_BINARY, $dir . '/run.php', $method, (string) $actor, json_encode($query)],
        [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes);
    fwrite($pipes[0], json_encode($input)); fclose($pipes[0]);
    return [$process, $pipes];
}
function finish_request($request) {
    [$process, $pipes] = $request;
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $code = proc_close($process);
    if ($code || !is_array($result = json_decode($out, true))) throw new RuntimeException("Endpoint failed: {$out} {$err}");
    return $result;
}
function request($method, $input, $actor = 7, $query = []) { return finish_request(start_request($method, $input, $actor, $query)); }
try {
    $result = request('POST', ['title' => 'Revisar', 'description' => 'Detalles', 'recurrence_unit' => 'week', 'recurrence_interval' => 2, 'shared_user_ids' => [8]]);
    check(!empty($result['success']), 'API creates a recurring assigned task');
    $id = (int) $result['data']['id'];
    $before = (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
    $result = request('PUT', ['id' => $id, 'is_completed' => true], 9);
    check(isset($result['error']) && $before === (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'outsider cannot complete or generate occurrences');

    // Different PHP sessions/connections complete the same task concurrently.
    $a = start_request('PUT', ['id' => $id, 'is_completed' => true], 7);
    $b = start_request('PUT', ['id' => $id, 'is_completed' => true], 8);
    check(!empty(finish_request($a)['success']) && !empty(finish_request($b)['success']), 'concurrent completion requests succeed');
    check(2 === (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'concurrent requests produce exactly one successor');
    $source = $db->query("SELECT * FROM tasks WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
    $next = $db->query("SELECT * FROM tasks WHERE recurrence_parent_id = {$id}")->fetch(PDO::FETCH_ASSOC);
    $nextId = (int) $next['id'];
    check($next['due_date'] === (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->modify('+14 days')->format('Y-m-d'), 'API calculates next date from completion in Madrid');
    check(1 === (int) $db->query("SELECT COUNT(*) FROM task_shares WHERE task_id = {$nextId} AND user_id = 8 AND seen_at IS NULL")->fetchColumn(), 'API copies assignment with fresh seen state');
    request('PUT', ['id' => $id, 'is_completed' => true], 8);
    $again = $db->query("SELECT * FROM tasks WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
    check($source['completed_at'] === $again['completed_at'] && $source['completed_by'] === $again['completed_by'], 'retry retains original completion actor and timestamp');
    check(2 === (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'retry creates no duplicates');
    $result = request('GET', [], 8, ['id' => $nextId]);
    check(!empty($result['success']) && $result['data']['recurrence_unit'] === 'week', 'assignee can open generated occurrence');

    request('PUT', ['id' => $nextId, 'title' => 'Trabajo posterior'], 8);
    $result = request('PUT', ['id' => $id, 'is_completed' => false]);
    check(isset($result['error']) && 1 === (int) $db->query("SELECT is_completed FROM tasks WHERE id = {$id}")->fetchColumn(), 'undo rejects modified successor and rolls back original completion');
    request('PUT', ['id' => $nextId, 'title' => 'Revisar'], 8);
    $result = request('PUT', ['id' => $id, 'is_completed' => false]);
    check(!empty($result['success']) && 1 === (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'undo removes untouched successor and reopens source');
    check(0 === (int) $db->query("SELECT COUNT(*) FROM notifications WHERE task_id = {$nextId} OR type = 'task_completion'")->fetchColumn(), 'undo cleans generated and completion notices');
    $result = request('PUT', ['id' => $id, 'is_completed' => true], 8);
    check(!empty($result['success']) && 2 === (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'completing again after undo creates one successor');
    $nextId = (int) $result['data']['next_task_id'];
    request('PUT', ['id' => $nextId, 'recurrence_unit' => null], 8);
    request('PUT', ['id' => $nextId, 'is_completed' => true], 8);
    check(2 === (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'disabling recurrence stops the chain');
    $result = request('POST', ['title' => 'Invalid', 'recurrence_unit' => 'day', 'recurrence_interval' => 0]);
    check(isset($result['error']) && 2 === (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn(), 'invalid creation inserts nothing');

    // A failure creating the successor must roll back completion and its actor/date.
    $result = request('POST', ['title' => 'Rollback', 'recurrence_unit' => 'day', 'recurrence_interval' => 1]);
    $rollbackId = (int) $result['data']['id'];
    $db->exec("CREATE TRIGGER reject_successor BEFORE INSERT ON tasks FOR EACH ROW BEGIN IF NEW.recurrence_parent_id IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test failure'; END IF; END");
    $result = request('PUT', ['id' => $rollbackId, 'is_completed' => true]);
    $row = $db->query("SELECT * FROM tasks WHERE id = {$rollbackId}")->fetch(PDO::FETCH_ASSOC);
    check(isset($result['error']) && !(int) $row['is_completed'] && $row['completed_at'] === null && $row['completed_by'] === null, 'failed successor insert atomically rolls back completion');
    echo "Task recurrence API integration completed successfully.\n";
} finally {
    foreach (glob($dir . '/*') as $file) unlink($file);
    rmdir($dir);
}

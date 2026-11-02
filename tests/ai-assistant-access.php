<?php
require_once __DIR__ . '/../includes/ai-assistant-http.php';
function same($expected, $actual, $label) { if ($expected !== $actual) throw new RuntimeException($label . ': ' . var_export($actual, true)); echo "PASS: {$label}\n"; }
function denied($callback, $status, $label) { try { $callback(); } catch (PrismaAssistantException $e) { same($status, $e->status, $label); return; } throw new RuntimeException('Expected denial: ' . $label); }
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT, company_id INTEGER, is_active INTEGER)');
$db->exec("INSERT INTO users VALUES (7,'user',1,1),(8,'admin',1,1)");
$_SESSION = ['user_id' => 7, 'role' => 'superadmin', 'company_id' => 1, 'username' => 'pruebas', 'company_name' => 'Equipo', 'full_name' => 'Persona de pruebas'];
$actor = assistant_actor($db);
same('user', $actor['role'], 'account role reloaded from DB instead of trusting stale session');
same('user', $_SESSION['role'], 'existing capability helpers receive fresh role');
$resolver = assistant_access_resolver($db, $actor);
same(null, $resolver('scope_team', 7), 'normal user cannot select team context');
same(null, $resolver('scope_mine', 8), 'personal scope cannot impersonate another user');
same('Mi trabajo', $resolver('scope_mine', 7)['label'], 'personal scope is available to the current user');
$db->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER, company_id INTEGER, title TEXT, is_shared INTEGER)');
$db->exec('CREATE TABLE task_shares (task_id INTEGER, user_id INTEGER)');
$db->exec("INSERT INTO tasks VALUES (21,8,1,'Privada',0),(22,8,1,'Equipo',1),(23,8,2,'Otra empresa',1)");
same(null, $resolver('task', 21), 'private task of another person never becomes a context');
same(null, $resolver('task', 23), 'team task in another company remains private');
same('Tarea · Equipo', $resolver('task', 22)['label'], 'shared task context uses real authorized title');
$db->exec('INSERT INTO task_shares VALUES (21,7)');
same('Tarea · Privada', $resolver('task', 21)['label'], 'named sharing grants task context');
$db->exec('DELETE FROM task_shares');
same(null, $resolver('task', 21), 'revoked task sharing is checked again');
$db->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY, user_id INTEGER, company_id INTEGER, title TEXT, is_shared INTEGER)');
$db->exec('CREATE TABLE note_shares (note_id INTEGER, user_id INTEGER)');
$db->exec("INSERT INTO notes VALUES (12,8,1,'Acta privada',0)");
same(null, $resolver('note', 12), 'private note is never resolved');
$db->exec('INSERT INTO note_shares VALUES (12,7)');
same('Nota · Acta privada', $resolver('note', 12)['label'], 'named note sharing grants context');
$db->exec('DELETE FROM note_shares');
same(null, $resolver('note', 12), 'revoked note context unavailable');
$db->exec("UPDATE users SET role='admin' WHERE id=7");
$admin = assistant_actor($db);
$adminResolver = assistant_access_resolver($db, $admin);
same(false, $resolver('scope_mine', 7)['access_fingerprint'] === $adminResolver('scope_mine', 7)['access_fingerprint'], 'capability changes invalidate derived history fingerprint');
$db->exec('UPDATE users SET is_active=0 WHERE id=7');
denied(fn() => assistant_actor($db), 401, 'inactive account cannot continue a slow turn');
$_SESSION = [];
denied(fn() => assistant_actor($db), 401, 'missing session rejected with JSON-compatible status');
// Reject mutations before connecting to a DB. This also covers a stale token after logout.
$_SESSION = ['user_id' => 7, 'assistant_csrf' => 'expected'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_PRISMA_CSRF'] = 'wrong';
// Run in a child process to test headers before any test output.
$probe = 'require ' . var_export(__DIR__ . '/../includes/ai-assistant-http.php', true) . '; $_SESSION=["user_id"=>7,"assistant_csrf"=>"expected"]; $_SERVER["REQUEST_METHOD"]="POST"; $_SERVER["HTTP_X_PRISMA_CSRF"]="wrong"; try { assistant_http_start(); } catch (PrismaAssistantException $e) { echo $e->status; }';
same('403', trim(shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($probe))), 'CSRF failure stops mutation before DB access');
echo "Assistant access checks completed successfully.\n";

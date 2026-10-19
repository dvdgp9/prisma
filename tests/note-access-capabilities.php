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

foreach (['note_capabilities_for_access', 'get_note_access', 'note_capability_for_update', 'require_note_capability'] as $fn) {
    if (!function_exists($fn)) {
        fwrite(STDERR, "FAIL: includes/auth.php is missing {$fn}()\n");
        exit(1);
    }
}

// Decisión del usuario (19 Oct 2026): quien recibe la nota la lee y la edita;
// solo el autor la borra y cambia con quién se comparte.
$ownerCaps = ['view' => true, 'edit' => true, 'delete' => true, 'share' => true];
$sharedCaps = ['view' => true, 'edit' => true, 'delete' => false, 'share' => false];
$noCaps = ['view' => false, 'edit' => false, 'delete' => false, 'share' => false];

assert_same($ownerCaps, note_capabilities_for_access(true, false), 'author can do everything');
assert_same($ownerCaps, note_capabilities_for_access(true, true), 'author keeps full control when the note is also shared');
assert_same($sharedCaps, note_capabilities_for_access(false, true), 'people the note is shared with can read and edit it, not delete or reshare it');
assert_same($noCaps, note_capabilities_for_access(false, false), 'unrelated users get nothing');

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY, company_id INTEGER NOT NULL, user_id INTEGER NOT NULL, app_id INTEGER NULL, title TEXT NOT NULL, content TEXT, is_shared INTEGER NOT NULL DEFAULT 0, is_pinned INTEGER NOT NULL DEFAULT 0, updated_by INTEGER NULL)');
$db->exec('CREATE TABLE note_shares (id INTEGER PRIMARY KEY, note_id INTEGER NOT NULL, user_id INTEGER NOT NULL)');
$db->exec("INSERT INTO notes (id, company_id, user_id, title, is_shared) VALUES
    (200, 1, 7, 'Own note', 0),
    (201, 1, 8, 'Team note', 1),
    (202, 1, 8, 'Shared with user 7 only', 0),
    (203, 1, 8, 'Private note of user 8', 0),
    (204, 2, 9, 'Team note in another company', 1),
    (205, 1, 8, 'Shared with someone else', 0),
    (206, 2, 7, 'Own note created in another company', 0)");
$db->exec('INSERT INTO note_shares (note_id, user_id) VALUES (202, 7), (205, 5)');

$_SESSION['user_id'] = 7;
$_SESSION['username'] = 'tenant-a-user';
$_SESSION['role'] = 'user';
$_SESSION['company_id'] = 1;
$_SESSION['company_name'] = 'Tenant A';
$_SESSION['full_name'] = 'Tenant A User';

$own = get_note_access(200, $db);
assert_same($ownerCaps, $own['capabilities'], 'author gets full access to an own note');
assert_same(200, (int) $own['note']['id'], 'access result includes the note row');
assert_same(false, array_key_exists('shared_with_user', $own['note']), 'helper column is not leaked in the note row');

assert_same($sharedCaps, get_note_access(201, $db)['capabilities'], 'team note in the same company is readable and editable');
assert_same($sharedCaps, get_note_access(202, $db)['capabilities'], 'note shared individually with the user is readable and editable');
assert_same(false, get_note_access(203, $db), 'private note of a colleague is hidden');
assert_same(false, get_note_access(204, $db), 'team note from another company is hidden');
assert_same(false, get_note_access(205, $db), 'note shared with someone else is hidden');
assert_same($ownerCaps, get_note_access(206, $db)['capabilities'], 'author keeps access to an own note from another company');
assert_same(false, get_note_access(999, $db), 'missing note returns false');
assert_same(false, get_note_access(0, $db), 'invalid note ID returns false');
assert_same(false, get_note_access('1 OR 1=1', $db), 'non-numeric note ID returns false');

// Qué permiso exige cada cambio: el contenido y el fijado son "edit"; con quién se comparte es "share".
assert_same('edit', note_capability_for_update(['id' => 1, 'title' => 'x', 'content' => 'y']), 'editing title/content needs edit');
assert_same('edit', note_capability_for_update(['id' => 1, 'is_pinned' => true]), 'pinning needs edit');
assert_same('edit', note_capability_for_update(['id' => 1, 'app_id' => 3]), 'changing the app needs edit');
assert_same('share', note_capability_for_update(['id' => 1, 'is_shared' => true]), 'sharing with the whole team needs share');
assert_same('share', note_capability_for_update(['id' => 1, 'title' => 'x', 'shared_user_ids' => [8]]), 'sharing with people needs share, even alongside edits');

fwrite(STDOUT, "Note access matrix completed successfully.\n");

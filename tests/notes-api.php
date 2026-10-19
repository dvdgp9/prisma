<?php

require_once __DIR__ . '/../includes/notes.php';

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

function ids($rows)
{
    return array_map(fn($row) => (int) $row['id'], $rows);
}

function share_ids($db, $noteId)
{
    $stmt = $db->prepare('SELECT user_id FROM note_shares WHERE note_id = ? ORDER BY user_id');
    $stmt->execute([$noteId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, full_name TEXT, company_id INTEGER, is_active INTEGER DEFAULT 1)');
$db->exec('CREATE TABLE apps (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec('CREATE TABLE notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER NOT NULL, user_id INTEGER NOT NULL, app_id INTEGER NULL,
    title TEXT NOT NULL, content TEXT, is_shared INTEGER NOT NULL DEFAULT 0, is_pinned INTEGER NOT NULL DEFAULT 0,
    updated_by INTEGER NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE note_shares (id INTEGER PRIMARY KEY AUTOINCREMENT, note_id INTEGER NOT NULL, user_id INTEGER NOT NULL, UNIQUE (note_id, user_id))');
$db->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL, request_id INTEGER NULL, note_id INTEGER NULL, triggered_by INTEGER NULL, message TEXT, is_read INTEGER DEFAULT 0)');

$db->exec("INSERT INTO users (id, username, full_name, company_id, is_active) VALUES
    (5, 'baja', 'Persona de baja', 1, 0),
    (6, 'luis', 'Luis', 1, 1),
    (7, 'yo', 'Yo Mismo', 1, 1),
    (8, 'ana', 'Ana', 1, 1),
    (9, 'otra', 'Otra Empresa', 2, 1)");
$db->exec("INSERT INTO apps (id, name) VALUES (1, 'App Demo')");
$db->exec("INSERT INTO notes (id, company_id, user_id, app_id, title, content, is_shared, is_pinned, updated_by, updated_at) VALUES
    (300, 1, 7, 1, 'Despliegue', 'Pasos del DESPLIEGUE de la API', 0, 0, 7, '2026-10-01 10:00:00'),
    (301, 1, 8, NULL, 'Acta lunes', 'Acuerdos', 1, 0, 8, '2026-10-03 10:00:00'),
    (302, 1, 8, NULL, 'Para ti', 'Datos del cliente', 0, 0, 8, '2026-10-02 10:00:00'),
    (303, 1, 8, NULL, 'Privada de Ana', 'Secreto', 0, 0, 8, '2026-10-04 10:00:00'),
    (304, 2, 9, NULL, 'Equipo de otra empresa', 'x', 1, 0, 9, '2026-10-05 10:00:00'),
    (305, 1, 7, NULL, 'Fijada', 'Importante', 0, 1, 7, '2026-09-01 10:00:00')");
$db->exec('INSERT INTO note_shares (note_id, user_id) VALUES (302, 7), (302, 6)');

$me = ['id' => 7, 'company_id' => 1, 'full_name' => 'Yo Mismo'];
$ana = ['id' => 8, 'company_id' => 1, 'full_name' => 'Ana'];

// --- Listado: lo propio + lo compartido conmigo (equipo o por nombre), solo de mi empresa; fijadas primero.
assert_same([305, 301, 302, 300], ids(list_notes($db, $me, [])), 'list shows own and shared notes of the company, pinned first then most recently edited');
assert_same([305, 300], ids(list_notes($db, $me, ['filter' => 'mine'])), '"mine" shows only my notes');
assert_same([301, 302], ids(list_notes($db, $me, ['filter' => 'shared'])), '"shared" shows only notes others shared with me');
assert_same([300], ids(list_notes($db, $me, ['app_id' => 1])), 'app filter');
assert_same([300], ids(list_notes($db, $me, ['q' => 'despliegue de la'])), 'search matches content, case-insensitive');
assert_same([301], ids(list_notes($db, $me, ['q' => 'acta'])), 'search matches title');
assert_same([305, 301], ids(list_notes($db, $me, ['limit' => 2])), 'limit keeps the first rows');

$rows = list_notes($db, $me, []);
$byId = array_column($rows, null, 'id');
assert_same(['view' => true, 'edit' => true, 'delete' => true, 'share' => true], $byId[300]['capabilities'], 'own note row carries owner capabilities');
assert_same(['view' => true, 'edit' => true, 'delete' => false, 'share' => false], $byId[302]['capabilities'], 'shared note row carries shared capabilities');
assert_same('Ana', $byId[302]['author_name'], 'row has the author name');
assert_same('App Demo', $byId[300]['app_name'], 'row has the app name');
assert_same(2, (int) $byId[302]['share_count'], 'row has the number of people it is shared with');
assert_same(false, array_key_exists('content', $byId[300]), 'list does not send the full content');
// "Hace X" se calcula contra la hora de la base de datos, no contra el reloj del navegador.
assert_same(1, preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $byId[300]['server_now']), 'list rows carry the database clock (server_now)');
assert_same('Pasos del DESPLIEGUE de la API', $byId[300]['excerpt'], 'list sends a short excerpt');

// --- Detalle
$detail = get_note_detail($db, 302);
assert_same('Datos del cliente', $detail['content'], 'detail has the full content');
assert_same('Ana', $detail['updated_by_name'], 'detail says who edited it last');
assert_same([6, 7], array_map(fn($u) => (int) $u['id'], $detail['shared_users']), 'detail lists the people it is shared with');
assert_same(1, preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $detail['server_now']), 'detail carries the database clock (server_now)');

// --- Validación de campos
assert_same('El título es obligatorio', normalize_note_fields(['title' => '   '], true)['error'], 'title is required on create');
assert_same('El título no puede superar 255 caracteres', normalize_note_fields(['title' => str_repeat('á', 256)], true)['error'], 'title max length counts characters');
assert_same(null, normalize_note_fields(['title' => str_repeat('á', 255)], true)['error'], '255 multibyte characters are fine');
assert_same('La nota es demasiado larga', normalize_note_fields(['title' => 'x', 'content' => str_repeat('a', NOTE_MAX_CONTENT_LENGTH + 1)], true)['error'], 'content has a size limit');
assert_same(['title' => 'Acta', 'content' => null, 'app_id' => null, 'is_pinned' => 1], normalize_note_fields(['title' => '  Acta ', 'content' => '', 'app_id' => '', 'is_pinned' => true, 'user_id' => 99], true)['fields'], 'fields are cleaned and unknown keys ignored');
assert_same(['content' => 'Nuevo'], normalize_note_fields(['id' => 1, 'content' => 'Nuevo', 'expected_revision' => 'x'], false)['fields'], 'update only touches the fields sent');
assert_same('El título es obligatorio', normalize_note_fields(['title' => ''], false)['error'], 'title cannot be emptied on update');

// --- Crear
$newId = create_note($db, $me, normalize_note_fields(['title' => 'Nueva', 'content' => 'Hola', 'app_id' => 1], true)['fields']);
$created = get_note_detail($db, $newId);
assert_same([7, 1, 7, 1, 'Nueva'], [(int) $created['user_id'], (int) $created['company_id'], (int) $created['updated_by'], (int) $created['app_id'], $created['title']], 'new note belongs to me and my company');

// --- Compartir con personas: solo compañeros activos de la empresa, sin el autor ni duplicados; devuelve los nuevos.
assert_same([6, 8], replace_note_shares($db, $me, $newId, false, [8, 9, 5, 7, 6, 8, 'x']), 'sharing returns the people newly added');
assert_same([6, 8], share_ids($db, $newId), 'only active teammates of the company are stored');
assert_same([], replace_note_shares($db, $me, $newId, false, [8]), 'people already shared with are not "new"');
assert_same([8], share_ids($db, $newId), 'sharing replaces the previous list');
assert_same([], replace_note_shares($db, $me, $newId, true, [6]), 'sharing with the whole team adds no individual people');
assert_same([[], 1], [share_ids($db, $newId), (int) get_note_detail($db, $newId)['is_shared']], 'whole-team sharing clears individual shares');

// --- Editar: el contenido marca quién editó; fijar no cuenta como edición.
$db->exec("UPDATE notes SET updated_at = '2026-10-10 09:00:00' WHERE id = 302");
update_note_fields($db, $me, 302, ['is_pinned' => 1]);
$pinned = get_note_detail($db, 302);
assert_same([1, 8, '2026-10-10 09:00:00'], [(int) $pinned['is_pinned'], (int) $pinned['updated_by'], $pinned['updated_at']], 'pinning keeps who edited and when');
update_note_fields($db, $me, 302, ['content' => 'Datos actualizados']);
$edited = get_note_detail($db, 302);
assert_same(['Datos actualizados', 7], [$edited['content'], (int) $edited['updated_by']], 'content edit records the editor');
assert_same(true, $edited['updated_at'] !== '2026-10-10 09:00:00', 'content edit moves updated_at');

// --- Conflicto: solo si cambio título/contenido partiendo de una versión que ya no es la actual.
// La revisión incluye título y contenido: detecta también cambios dentro del mismo segundo (TIMESTAMP).
$old = ['updated_at' => '2026-10-10 09:00:00', 'title' => 'T', 'content' => 'Antes'];
$sameSecond = ['updated_at' => '2026-10-10 09:00:00', 'title' => 'T', 'content' => 'Ana lo cambió'];
$later = ['updated_at' => '2026-10-10 09:05:00', 'title' => 'T', 'content' => 'Antes'];
assert_same(true, is_string(note_revision($old)) && strlen(note_revision($old)) === 16, 'revision is a short hash');
assert_same(false, note_edit_conflict($old, ['content' => 'x', 'expected_revision' => note_revision($old)]), 'edit on the current version is fine');
assert_same(true, note_edit_conflict($sameSecond, ['content' => 'x', 'expected_revision' => note_revision($old)]), 'change in the same second is a conflict');
assert_same(true, note_edit_conflict($later, ['title' => 'x', 'expected_revision' => note_revision($old)]), 'later save is a conflict');
assert_same(false, note_edit_conflict($later, ['is_pinned' => true, 'expected_revision' => note_revision($old)]), 'pinning never conflicts');
assert_same(false, note_edit_conflict($later, ['content' => 'x']), 'without expected_revision there is no check');
assert_same(note_revision(get_note_detail($db, 302)), get_note_detail($db, 302)['revision'], 'detail sends the revision the client must send back');

// --- Borrar: se van la nota, sus comparticiones y sus avisos.
$db->exec("INSERT INTO notifications (user_id, type, note_id, message) VALUES (6, 'note_share', 302, 'x'), (6, 'comment', NULL, 'y')");
delete_note($db, 302);
assert_same(false, get_note_detail($db, 302), 'deleted note is gone');
assert_same([], share_ids($db, 302), 'its shares are gone');
assert_same(1, (int) $db->query('SELECT COUNT(*) FROM notifications')->fetchColumn(), 'its notifications are gone, others stay');

// --- Endpoint: guardas de permisos y comprobaciones de entrada.
assert_file_contains('api/notes.php', [
    "require_once __DIR__ . '/../includes/notes.php';",
    'require_login();',
    "require_note_capability(\$_GET['id'], 'view')",
    "require_note_capability(\$input['id'], note_capability_for_update(\$input))",
    "require_note_capability(\$input['id'], 'delete')",
    'can_access_app($fields[\'app_id\'])',
    'note_edit_conflict($access[\'note\'], $input)',
    "'conflict' => true",
    'list_notes($db, $user, $_GET)',
], 'notes API guards every action');

fwrite(STDOUT, "Notes API completed successfully.\n");

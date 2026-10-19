<?php

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
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: {$relativePath} does not exist\n");
        exit(1);
    }
    return file_get_contents($path);
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

// --- Helpers puros (assets/js/notes-helpers.js), ejecutados con Node.
$node = <<<'JS'
global.window = global;
require(process.argv[1]);
const now = new Date(2026, 9, 19, 12, 0, 0); // 19 oct 2026, 12:00 (hora local)
console.log(JSON.stringify({
    excerpt: {
        markdown: noteExcerpt('# Despliegue\n\n1. Entra en **cPanel**\n- Sube `api/` y [la guía](https://x.test)\n> Ojo con ![captura](a.png) la caché'),
        empty: noteExcerpt(null),
        cut: noteExcerpt('uno dos tres cuatro cinco seis', 15),
        code: noteExcerpt('```\nphp -l x.php\n```\nListo'),
        checklist: noteExcerpt('- [ ] Llamar a Ana\n- [x] Enviar acta')
    },
    time: {
        justNow: formatNoteTime('2026-10-19 11:59:40', now),
        minutes: formatNoteTime('2026-10-19 11:35:00', now),
        hours: formatNoteTime('2026-10-19 08:10:00', now),
        yesterday: formatNoteTime('2026-10-18 23:50:00', now),
        days: formatNoteTime('2026-10-15 09:00:00', now),
        sameYear: formatNoteTime('2026-03-02 09:00:00', now),
        otherYear: formatNoteTime('2025-12-24 09:00:00', now),
        invalid: formatNoteTime('', now)
    },
    share: {
        team: noteShareSummary({ is_shared: 1, share_count: 0 }, true),
        people: noteShareSummary({ is_shared: 0, share_count: 2 }, true),
        one: noteShareSummary({ is_shared: '0', share_count: '1' }, true),
        privateMine: noteShareSummary({ is_shared: 0, share_count: 0 }, true),
        sharedWithMe: noteShareSummary({ is_shared: 0, share_count: 1 }, false),
        teamNotMine: noteShareSummary({ is_shared: 1, share_count: 0 }, false)
    }
}));
JS;

$output = shell_exec('node -e ' . escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/../assets/js/notes-helpers.js') . ' 2>&1');
$result = json_decode((string) $output, true);
if (!is_array($result)) {
    fwrite(STDERR, "FAIL: could not run notes-helpers.js with node:\n{$output}\n");
    exit(1);
}

assert_same('Despliegue Entra en cPanel Sube api/ y la guía Ojo con captura la caché', $result['excerpt']['markdown'], 'excerpt strips Markdown syntax');
assert_same('', $result['excerpt']['empty'], 'excerpt of an empty note is empty');
assert_same('uno dos tres…', $result['excerpt']['cut'], 'excerpt cuts on a word boundary with an ellipsis');
assert_same('php -l x.php Listo', $result['excerpt']['code'], 'excerpt keeps code text without fences');
assert_same('Llamar a Ana Enviar acta', $result['excerpt']['checklist'], 'excerpt drops checkbox markers');

assert_same('Ahora mismo', $result['time']['justNow'], 'time: under a minute');
assert_same('Hace 25 min', $result['time']['minutes'], 'time: minutes');
assert_same('Hace 3 h', $result['time']['hours'], 'time: hours today');
assert_same('Ayer', $result['time']['yesterday'], 'time: yesterday');
assert_same('Hace 4 días', $result['time']['days'], 'time: days this week');
assert_same('2 mar', $result['time']['sameYear'], 'time: date this year');
assert_same('24 dic 2025', $result['time']['otherYear'], 'time: date in another year');
assert_same('', $result['time']['invalid'], 'time: empty input');

assert_same(['icon' => 'iconoir-group', 'label' => 'Todo el equipo'], $result['share']['team'], 'share: whole team');
assert_same(['icon' => 'iconoir-user', 'label' => '2 personas'], $result['share']['people'], 'share: several people');
assert_same(['icon' => 'iconoir-user', 'label' => '1 persona'], $result['share']['one'], 'share: one person (string values from MySQL)');
assert_same(['icon' => 'iconoir-lock', 'label' => 'Solo yo'], $result['share']['privateMine'], 'share: private');
assert_same(['icon' => 'iconoir-user', 'label' => 'Compartida contigo'], $result['share']['sharedWithMe'], 'share: shared with me by name');
assert_same(['icon' => 'iconoir-group', 'label' => 'Todo el equipo'], $result['share']['teamNotMine'], 'share: team note from someone else');

// --- Página
assert_file_contains('notes.php', [
    'require_login();',
    "\$current_page = 'notes'; include __DIR__ . '/includes/sidebar.php';",
    '/assets/css/notes.css?v=1',
    '/assets/js/notes-helpers.js?v=1',
    '/assets/js/notes.js?v=1',
    'marked.min.js',
    'purify.min.js',
    'id="notes-search"',
    'data-filter="all"',
    'data-filter="mine"',
    'data-filter="shared"',
    'id="notes-app-filter"',
    'id="notes-new-btn"',
    'id="notes-list"',
    'id="note-pane"',
    'id="notes-apps-data" type="application/json"',
    'id="toast-container"',
], 'notes page has its structure and assets');

$page = read_project_file('notes.php');
assert_same(false, strpos($page, 'style="') !== false, 'notes page has no inline styles');

assert_file_contains('assets/js/notes.js', [
    "fetch(`/api/notes.php?",
    'expected_revision',
    'data.conflict',
    'DOMPurify.sanitize(',
    'history.replaceState(',
    "'/api/task-shares.php'",
    'function renderNoteList(',
    'function selectNote(',
    'function saveNote(',
    'function resolveConflict(',
    'function openNoteShareMenu(',
    'function confirmDiscardChanges(',
    'serverClock(note)',
], 'notes.js loads, saves with conflict check and renders safe Markdown');

assert_file_contains('assets/css/notes.css', [
    '@media (max-width: 900px)',
    '@media (prefers-reduced-motion: reduce)',
    ':focus-visible',
    '.notes-page .visually-hidden',
], 'notes.css covers mobile, reduced motion and keyboard focus');

foreach (['notes.php', 'assets/js/notes.js', 'assets/js/notes-helpers.js', 'assets/css/notes.css'] as $file) {
    $contents = read_project_file($file);
    assert_same(false, strpos($contents, "\u{2014}") !== false || strpos($contents, "\u{2013}") !== false, "{$file} has no long dashes");
}

// --- Barra lateral: "Notas" justo después de "Mejoras".
$sidebar = read_project_file('includes/sidebar.php');
assert_file_contains('includes/sidebar.php', ['href="/notes.php"', 'data-nav="notes"', '<span>Notas</span>'], 'sidebar links to Notas');
$mejoras = strpos($sidebar, '<span>Mejoras</span>');
$notas = strpos($sidebar, '<span>Notas</span>');
$quick = strpos($sidebar, '<span>Nota rápida</span>');
assert_same(true, $mejoras !== false && $notas !== false && $quick !== false && $mejoras < $notas && $notas < $quick, 'Notas sits between Mejoras and Nota rápida');

fwrite(STDOUT, "Notes page contract completed successfully.\n");

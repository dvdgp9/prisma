<?php

// Plan "Para mí | Todo", PT3: selector "Para mí | De todos" en la Vista global, filtro por persona
// y para quién es cada fila. Solo programador, admin y superadmin ven el selector.

function assert_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

function read_project_file($relativePath)
{
    $path = __DIR__ . '/../' . $relativePath;
    return is_file($path) ? file_get_contents($path) : '';
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

// --- index.php: el selector y el filtro por persona solo se pintan para quien puede verlos.
assert_file_contains('index.php', [
    "\$canSeeTeamWork = in_array(\$user['role'], ['programador', 'admin', 'superadmin'], true);",
    '<?php if ($canSeeTeamWork): ?>',
    'id="work-scope"',
    'data-work-scope="mine"',
    'data-work-scope="all"',
    'aria-pressed="true"',
    'Para mí',
    'De todos',
    'id="work-person"',
    'id="my-work-sub"',
    'class="my-work-toolbar"',
], 'index has the scope switch and the person filter for the team roles');

$index = read_project_file('index.php');
$scopeAt = strpos($index, 'id="work-scope"');
$guardAt = strrpos(substr($index, 0, (int) $scopeAt), '<?php if ($canSeeTeamWork): ?>');
assert_true($scopeAt !== false && $guardAt !== false && $scopeAt - $guardAt < 300, 'the scope switch is inside the role check');
$personAt = strpos($index, 'id="work-person"');
$guardPersonAt = strrpos(substr($index, 0, (int) $personAt), '<?php if ($canSeeTeamWork): ?>');
assert_true($personAt !== false && $guardPersonAt !== false && $personAt - $guardPersonAt < 400, 'the person filter is inside the role check');

// --- my-work.js
assert_file_contains('assets/js/my-work.js', [
    "const MY_WORK_SCOPE_ROLES = ['programador', 'admin', 'superadmin'];",
    "localStorage.getItem('prisma_mywork_scope')",
    "localStorage.setItem('prisma_mywork_scope'",
    'function setMyWorkScope(',
    'function canSeeTeamWork(',
    '/api/tasks.php?completed=0&shared=${scope === \'all\' ? 1 : 0}',
    "fetch('/api/task-shares.php')",
    'buildWorkItems(list.data, myWork.requests, viewer, scope)',
    'workPeople(',
    'filterWorkItemsByPerson(',
    'function renderMyWorkPeople(',
    'my-work-whom',
    'De ${escapeHtml(item.from)}',
    'Trabajo del equipo',
    'function myWorkTaskRecords(',
], 'my-work.js switches scope, loads the team tasks and filters by person');

$myWorkJs = read_project_file('assets/js/my-work.js');
assert_true(substr_count($myWorkJs, 'try {') >= 4, 'filter and scope reads and writes in localStorage are guarded');
assert_true(strpos($myWorkJs, "viewer.role === 'superadmin'") === false, 'superadmin no longer has its own rule in the rows (scope decides)');

// --- work.css
assert_file_contains('assets/css/work.css', [
    '.work-scope',
    '.work-scope-btn[aria-pressed="true"]',
    '.work-scope-btn:focus-visible',
    '.my-work-toolbar',
    '.work-person',
    '.work-person select:focus-visible',
    '.my-work-whom',
], 'work.css styles the scope switch, the toolbar, the person filter and the "for whom" label');

// --- Versiones: la PWA cachea los assets.
assert_file_contains('index.php', [
    '/assets/css/work.css?v=4',
    '/assets/js/work-helpers.js?v=4',
    '/assets/js/my-work.js?v=3',
    '/assets/js/task-parser.js?v=7',
], 'index loads the new versions of the work assets');
assert_file_contains('sw.js', ["const CACHE_NAME = 'prisma-v43';"], 'service worker cache is bumped');

foreach (['assets/js/my-work.js', 'assets/css/work.css', 'assets/js/work-helpers.js'] as $file) {
    $contents = read_project_file($file);
    assert_true(strpos($contents, "\u{2014}") === false && strpos($contents, "\u{2013}") === false, "{$file} has no long dashes");
}

fwrite(STDOUT, "My work scope UI completed successfully.\n");

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

// --- El filtro único se muestra solo a quienes pueden consultar el equipo.
assert_file_contains('index.php', [
    "\$canSeeTeamWork = in_array(\$user['role'], ['programador', 'admin', 'superadmin'], true);",
    'id="work-team"',
    'id="work-team-cards"',
    'id="work-person"',
    'Todo el equipo',
    'class="my-work-toolbar"',
], 'index has one people filter for the team roles');

$index = read_project_file('index.php');
$teamAt = strpos($index, 'id="work-team"');
$guardAt = strrpos(substr($index, 0, (int) $teamAt), '<?php if ($canSeeTeamWork): ?>');
assert_true($teamAt !== false && $guardAt !== false && $teamAt - $guardAt < 130, 'the people filter is inside the role check');
assert_true(strpos($index, 'id="work-scope"') === false, 'the separate scope switch was removed');

assert_file_contains('assets/js/my-work.js', [
    "const MY_WORK_SCOPE_ROLES = ['programador', 'admin', 'superadmin'];",
    "localStorage.getItem('prisma_mywork_scope')",
    'localStorage.setItem(myWorkPersonStorageKey(), person)',
    'function setMyWorkPerson(',
    "return this.person === document.body.dataset.userId ? 'mine' : 'all';",
    "'/api/tasks.php?completed=0&shared=0'",
    "'/api/tasks.php?completed=1&completed_days=30&shared=1'",
    "fetch('/api/task-shares.php')",
    'buildWorkItems(list.data, myWork.requests, viewer, scope)',
    'filterWorkItemsByPerson(',
    'my-work-whom',
    'De ${escapeHtml(item.from)}',
    'Trabajo del equipo',
    'function myWorkTaskRecords(',
], 'my-work.js remembers the person and reuses the personal and team scopes');

assert_file_contains('assets/css/work.css', [
    '.my-work-toolbar',
    '.work-person',
    '.work-person select:focus-visible',
    '.my-work-whom',
], 'work.css styles the toolbar, the mobile person filter and the assignee label');

// --- Versiones: la PWA cachea los assets.
assert_file_contains('index.php', [
    '/assets/css/work.css?v=9',
    '/assets/js/work-helpers.js?v=5',
    '/assets/js/my-work.js?v=7',
    '/assets/js/task-parser.js?v=9',
], 'index loads the new versions of the work assets');
assert_file_contains('sw.js', ["const CACHE_NAME = 'prisma-v60';"], 'service worker cache is bumped');

foreach (['assets/js/my-work.js', 'assets/css/work.css', 'assets/js/work-helpers.js'] as $file) {
    $contents = read_project_file($file);
    assert_true(strpos($contents, "\u{2014}") === false && strpos($contents, "\u{2013}") === false, "{$file} has no long dashes");
}

fwrite(STDOUT, "My work scope UI completed successfully.\n");

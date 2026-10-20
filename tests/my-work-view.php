<?php

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

// --- Vista global = "Mi trabajo" (plan MT5): sustituye a los bloques "Qué toca" y "Mejoras".
assert_file_contains('index.php', [
    'id="my-work"',
    'id="my-work-body"',
    'id="my-work-title">Mi trabajo</h2>',
    'data-work-filter="all"',
    'data-work-filter="task"',
    'data-work-filter="request"',
    'id="work-count-all"',
    'id="work-count-task"',
    'id="work-count-request"',
    'Tus tareas y las mejoras que tienes asignadas', // "Para mí"; "De todos" cambia el texto desde my-work.js (plan PT3)
    '/assets/js/my-work.js?v=4',
], 'overview has the unified work list with its filter');

$index = read_project_file('index.php');
foreach (['id="tasks-widget"', 'id="requests-widget"', 'goToMejoras('] as $gone) {
    assert_true(strpos($index, $gone) === false, "index.php no longer has {$gone}");
}
$helpers = strpos($index, '/assets/js/work-helpers.js');
$main = strpos($index, '/assets/js/main.js');
$myWork = strpos($index, '/assets/js/my-work.js');
assert_true($helpers !== false && $main !== false && $myWork !== false && $helpers < $main && $main < $myWork, 'scripts load in order: work-helpers, main, my-work');
assert_true(strpos($index, 'id="my-work"') < strpos($index, 'id="notes-widget"'), '"Notas recientes" stays below the work list');

assert_file_contains('assets/js/my-work.js', [
    'function loadMyWork(',
    'function renderMyWork(',
    'function setMyWorkFilter(',
    '/api/tasks.php?completed=0&shared=${scope === \'all\' ? 1 : 0}', // "Para mí" shared=0, "De todos" shared=1
    'Todas las tareas que ves y todas las mejoras activas',
    'buildWorkItems(',
    'groupWorkItems(',
    'countWorkItems(',
    'requestDueInfo(',
    'openRequestDetailModal(',
    'unreadRequestIds',
    "localStorage.getItem('prisma_mywork_filter')",
    'Sin asignar',
    'Todo al día',
    'No se pudo cargar tu trabajo',
    'aria-busy',
], 'my-work.js loads, filters, groups and renders every state');

$myWorkJs = read_project_file('assets/js/my-work.js');
assert_true(substr_count($myWorkJs, 'try {') >= 2, 'localStorage reads and writes are guarded');

// main.js ya no pinta los bloques antiguos: avisa a "Mi trabajo".
$mainJs = read_project_file('assets/js/main.js');
foreach (['function renderRequestsWidget(', 'function renderTasksWidget(', 'function loadTasksWidget(', 'function goToMejoras(', 'tasksWidgetData'] as $gone) {
    assert_true(strpos($mainJs, $gone) === false, "main.js no longer has {$gone}");
}
assert_file_contains('assets/js/main.js', [
    "if (typeof loadMyWork === 'function') loadMyWork();",
    "renderMyWork({ requestsLoaded: true })",
    "renderMyWork({ requestsError: true })",
    "if (typeof loadMyWork === 'function') loadMyWork(true);",
], 'main.js hands requests, tasks and notifications over to "Mi trabajo"');

assert_file_contains('assets/css/work.css', [
    '.my-work',
    '.work-filter',
    '.my-work-row',
    '.my-work-group-head',
    ':focus-visible',
    '@media (max-width: 600px)',
    '@media (prefers-reduced-motion: reduce)',
], 'work.css styles the list for desktop, mobile, keyboard and reduced motion');

foreach (['assets/js/my-work.js', 'assets/css/work.css'] as $file) {
    $contents = read_project_file($file);
    assert_true(strpos($contents, "\u{2014}") === false && strpos($contents, "\u{2013}") === false, "{$file} has no long dashes");
}

fwrite(STDOUT, "My work view contract completed successfully.\n");

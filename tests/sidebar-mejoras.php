<?php

function read_project_file($relativePath)
{
    return file_get_contents(__DIR__ . '/../' . $relativePath);
}

function assert_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
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

$sidebar = read_project_file('includes/sidebar.php');

// Barra lateral: Vista global, Tareas, Mejoras, Notas y Crear con IA (antes "Nota rápida").
assert_file_contains('includes/sidebar.php', [
    '<span>Tareas</span>',
    '<span>Mejoras</span>',
    'data-nav="mejoras"',
    "loadView('home', null, event)",
    "loadView('mejoras', null, event)",
    'href="/index.php?view=mejoras"',
], 'sidebar has Vista global, Tareas and Mejoras entries');
assert_true(strpos($sidebar, '<span>Mis tareas</span>') === false, 'sidebar no longer says "Mis tareas"');

$positions = array_map(fn($label) => strpos($sidebar, "<span>{$label}</span>"), ['Vista global', 'Tareas', 'Mejoras', 'Notas', 'Crear con IA']);
$inOrder = !in_array(false, $positions, true);
for ($i = 1; $inOrder && $i < count($positions); $i++) {
    $inOrder = $positions[$i - 1] < $positions[$i];
}
assert_true($inOrder, 'sidebar order is Vista global, Tareas, Mejoras, Notas, Crear con IA');

// Vista global = "Mi trabajo" (tareas + mejoras, plan MT5); Mejoras = listado con filtros.
assert_file_contains('index.php', [
    'id="home-overview"',
    'id="my-work"',
    'id="requests-area"',
    'id="export-btn"',
    '/assets/js/main.js?v=5.5',
], 'index has the overview and the requests list as separate areas');
assert_file_contains('assets/js/main.js', [
    "let currentView = 'home';",
    "urlParams.get('view') === 'mejoras'",
    'function updateViewLayout()',
    'function computeRequestsSummary(',
    "history.pushState({view: 'mejoras'}, '', '/index.php?view=mejoras')",
], 'main.js switches between the overview and the Mejoras list');

// Tareas: mismo nombre en la página y en los accesos.
assert_file_contains('tasks.php', ['<title>Tareas - Prisma</title>', '<h1 class="page-title">Tareas</h1>'], 'tasks page is titled Tareas');
assert_file_contains('assets/js/my-work.js', ['isTask ? `/tasks.php?task='], 'tasks in "Mi trabajo" open that task in Tareas');

// Caché PWA: assets versionados en todas las páginas.
foreach (['index.php', 'tasks.php', 'notes.php', 'ai-inbox.php', 'changelog.php', 'admin.php', 'manage-apps.php', 'login.php', 'solicitud.php', 'releases.php'] as $page) {
    assert_file_contains($page, ['/assets/css/styles.css?v=5.4'], "{$page} loads the new styles.css");
}
assert_file_contains('sw.js', ["const CACHE_NAME = 'prisma-v46';"], 'service worker cache is bumped');

fwrite(STDOUT, "Sidebar Mejoras contract completed successfully.\n");

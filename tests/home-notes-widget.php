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

// --- "Nota rápida" pasa a llamarse "Crear con IA" (decisión del usuario, 19 oct 2026).
assert_file_contains('includes/sidebar.php', ['<span>Crear con IA</span>'], 'sidebar says "Crear con IA"');
assert_file_contains('ai-inbox.php', ['<title>Crear con IA - Prisma</title>', '<h1 class="page-title">Crear con IA</h1>'], 'AI page is titled "Crear con IA"');
foreach (['includes/sidebar.php', 'ai-inbox.php'] as $file) {
    assert_true(stripos(read_project_file($file), 'Nota rápida') === false, "{$file} no longer says \"Nota rápida\"");
}

// --- Vista global: bloque "Notas recientes" con las últimas notas compartidas conmigo.
assert_file_contains('index.php', [
    'id="notes-widget"',
    'id="notes-widget-list"',
    'Notas recientes',
    'href="/notes.php"',
    '/assets/js/notes-helpers.js?v=1',
], 'overview has the recent notes block');

$index = read_project_file('index.php');
assert_true(strpos($index, 'notes-helpers.js') < strpos($index, '/assets/js/main.js'), 'notes-helpers.js loads before main.js');
assert_true(strpos($index, 'id="notes-widget"') > strpos($index, 'id="my-work"') && strpos($index, 'id="notes-widget"') < strpos($index, 'id="app-info-section"'), 'notes block sits after "Mi trabajo" inside the overview');

assert_file_contains('assets/js/main.js', [
    'function loadNotesWidget()',
    'function renderNotesWidget()',
    "fetch('/api/notes.php?filter=shared&order=recent&limit=4')",
    'noteExcerpt(',
    'formatNoteTime(',
    '/notes.php?id=',
    'Nadie te ha compartido notas todavía',
], 'main.js loads and renders the latest notes shared with me');

assert_file_contains('assets/css/styles.css', ['.notes-widget-list', '.notes-widget-item'], 'styles.css styles the recent notes block');

assert_file_contains('includes/notes.php', ["(\$filters['order'] ?? '') === 'recent'"], 'notes API supports order=recent');

fwrite(STDOUT, "Home notes widget contract completed successfully.\n");

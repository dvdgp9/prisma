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

function read_file($relativePath)
{
    return file_get_contents(__DIR__ . '/../' . $relativePath);
}

function assert_contains_all($relativePath, $needles, $message)
{
    $contents = read_file($relativePath);
    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            fwrite(STDERR, "FAIL: {$message} ({$relativePath} is missing: {$needle})\n");
            exit(1);
        }
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

function assert_contains_none($haystack, $needles, $message)
{
    foreach ($needles as $needle) {
        if (strpos($haystack, $needle) !== false) {
            fwrite(STDERR, "FAIL: {$message} (still contains: {$needle})\n");
            exit(1);
        }
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

// ---- Pure helpers (task-parser.js) executed with Node ----
$node = <<<'JS'
global.window = global;
require(process.argv[1]);
const today = new Date(2026, 9, 17);
console.log(JSON.stringify({
    source: [
        taskSourceRequestId('Creada desde mejora #142: Add: exportar a PDF\n\nmás texto'),
        taskSourceRequestId('Sin origen'),
        taskSourceRequestId(null)
    ],
    relative: [
        taskRelativeDay('2026-10-17 09:00:00', today),
        taskRelativeDay('2026-10-16 23:59:00', today),
        taskRelativeDay('2026-10-10 09:12:00', today),
        taskRelativeDay(null, today)
    ]
}));
JS;
$output = shell_exec('node -e ' . escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/../assets/js/task-parser.js') . ' 2>&1');
$result = json_decode((string) $output, true);
if (!is_array($result)) {
    fwrite(STDERR, "FAIL: could not run task-parser.js helpers with node:\n{$output}\n");
    exit(1);
}
assert_same([142, null, null], $result['source'], 'detects the source request in the description');
assert_same(['hoy', 'ayer', 'hace 7 días', ''], $result['relative'], 'relative day labels for the meta line');

// ---- M1.2: CSS custom properties used by tasks screens are defined ----
$tokens = read_file('assets/css/tokens.css');
assert_contains_all('assets/css/tokens.css', ['--sp-10: 10px;', '--primary-rgb:   66, 160, 209;'], 'tokens define --sp-10 and --primary-rgb');

$defined = [];
foreach (glob(__DIR__ . '/../assets/css/*.css') as $file) {
    preg_match_all('/(--[\w-]+)\s*:/', file_get_contents($file), $m);
    $defined = array_merge($defined, $m[1]);
}
preg_match_all('/var\(\s*(--[\w-]+)\s*\)/', read_file('assets/css/tasks.css'), $used);
$missing = array_values(array_diff(array_unique($used[1]), $defined));
assert_same([], $missing, 'tasks.css only uses defined CSS variables');
preg_match_all('/var\(\s*(--sp-10)\s*\)/', read_file('assets/css/styles.css'), $sp10);
assert_same(true, count($sp10[1]) > 0 && in_array('--sp-10', $defined, true), 'styles.css uses of --sp-10 now resolve');

// ---- M1.1: the mobile one-column rule comes after the desktop 2-column rule ----
$styles = read_file('assets/css/styles.css');
$desktopRule = strrpos($styles, 'grid-template-columns: minmax(0, 1fr) 280px;');
$mobileFix = strpos($styles, 'Modales de 2 columnas en móvil');
assert_same(true, $mobileFix !== false && $mobileFix > $desktopRule, 'mobile modal grid fix is declared after the desktop rule');

// ---- Modal markup (M1.3, M1.4, M2) ----
$tasksPage = read_file('tasks.php');
$start = strpos($tasksPage, '<div class="modal" id="task-modal"');
$end = strpos($tasksPage, '<!-- Toast Container -->');
$modal = substr($tasksPage, $start, $end - $start);
assert_same(true, $start !== false && $end > $start, 'task modal markup found');
foreach (['role="dialog"', 'aria-modal="true"', 'aria-labelledby="task-modal-heading"', 'id="task-meta-bar"', 'id="task-source-link"', 'file-upload-area--compact', 'id="task-delete-btn"', 'Guardar cambios', 'id="task-unsaved-hint"'] as $needle) {
    if (strpos($modal, $needle) === false) {
        fwrite(STDERR, "FAIL: task modal is missing {$needle}\n");
        exit(1);
    }
}
fwrite(STDOUT, "PASS: task modal has dialog semantics, property bar, compact upload and footer delete\n");
assert_contains_none($modal, ['style="', 'Zona peligrosa', 'modal-body-grid', 'Guardar Cambios'], 'task modal has no inline styles, danger zone or two-column grid');

// ---- Behaviour (M2, M3) ----
assert_contains_all('assets/js/tasks.js', [
    'function renderTaskMetaBar(',
    'function requestCloseTaskModal(',
    'function isTaskModalDirty(',
    'function handleTaskModalPaste(',
    'function formatFileSize(',
    '/index.php?open_request=',
    "title: document.getElementById('task-title').value.trim(),",
], 'tasks.js renders the bar, guards unsaved changes, handles paste and links the source request');
assert_contains_none(read_file('assets/js/tasks.js'), [
    "app_id: document.getElementById('task-app')",
    "due_date: document.getElementById('task-due-date')",
    'function toggleShareDropdown(',
], 'saving the modal only sends title/description (properties save instantly) and the old share dropdown is gone');
assert_contains_all('assets/css/tasks.css', ['.task-modal-content', '.task-title-field', '.task-attachment-thumb'], 'tasks.css styles the new modal');

fwrite(STDOUT, "Task modal v2 contract completed successfully.\n");

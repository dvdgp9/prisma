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

function assert_file_contains($relativePath, $needles, $message)
{
    $path = __DIR__ . '/../' . $relativePath;
    $contents = is_file($path) ? file_get_contents($path) : '';

    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            fwrite(STDERR, "FAIL: {$message} ({$relativePath} is missing: {$needle})\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

// --- Texto y tono de la fecha de una mejora (assets/js/work-helpers.js), con Node.
$node = <<<'JS'
global.window = global;
require(process.argv[1]);
const today = new Date(2026, 9, 19); // lunes 19 oct 2026
const cases = {};
for (const value of [null, '', 'mañana', '2026-10-17', '2026-10-18', '2026-10-19', '2026-10-20', '2026-10-23', '2026-10-26', '2026-11-03', '2027-01-05']) {
    cases[String(value)] = requestDueInfo(value, today);
}
console.log(JSON.stringify(cases));
JS;

$helpers = __DIR__ . '/../assets/js/work-helpers.js';
$output = is_file($helpers) ? shell_exec('node -e ' . escapeshellarg($node) . ' ' . escapeshellarg($helpers) . ' 2>&1') : '';
$result = json_decode((string) $output, true);
if (!is_array($result)) {
    fwrite(STDERR, "FAIL: could not run work-helpers.js with node:\n{$output}\n");
    exit(1);
}

assert_same(['tone' => 'none', 'label' => 'Sin fecha', 'days' => null], $result['null'], 'no date');
assert_same(['tone' => 'none', 'label' => 'Sin fecha', 'days' => null], $result[''], 'empty date (just cleared)');
assert_same(['tone' => 'none', 'label' => 'Sin fecha', 'days' => null], $result['mañana'], 'invalid value counts as no date');
assert_same(['tone' => 'overdue', 'label' => 'Venció el 17 oct', 'days' => -2], $result['2026-10-17'], 'overdue');
assert_same(['tone' => 'overdue', 'label' => 'Venció ayer', 'days' => -1], $result['2026-10-18'], 'overdue since yesterday');
assert_same(['tone' => 'today', 'label' => 'Para hoy', 'days' => 0], $result['2026-10-19'], 'due today');
assert_same(['tone' => 'soon', 'label' => 'Para mañana', 'days' => 1], $result['2026-10-20'], 'due tomorrow');
assert_same(['tone' => 'soon', 'label' => 'Para el viernes', 'days' => 4], $result['2026-10-23'], 'this week shows the weekday');
assert_same(['tone' => 'soon', 'label' => 'Para el 26 oct', 'days' => 7], $result['2026-10-26'], 'a week away shows the date');
assert_same(['tone' => 'later', 'label' => 'Para el 3 nov', 'days' => 15], $result['2026-11-03'], 'later this year');
assert_same(['tone' => 'later', 'label' => 'Para el 5 ene 2027', 'days' => 78], $result['2027-01-05'], 'another year shows the year');

// --- Mejoras: fecha en el detalle, en tarjetas y tabla, y orden por fecha.
assert_file_contains('index.php', [
    '/assets/js/work-helpers.js?v=4',
    '/assets/css/work.css?v=4',
    '/assets/js/main.js?v=5.4',
    'data-sort-field="due_date"',
], 'index loads the date helpers, styles and table column');
$index = file_get_contents(__DIR__ . '/../index.php');
assert_same(3, substr_count($index, '<option value="due_date">Fecha objetivo</option>'), 'all three sort selects offer "Fecha objetivo"');
assert_same(true, strpos($index, 'work-helpers.js') < strpos($index, '/assets/js/main.js'), 'work-helpers.js loads before main.js');

assert_file_contains('assets/js/main.js', [
    'function getRequestDueControlHtml(',
    "quickUpdateRequest(\${id}, 'due_date'",
    'showPicker',
    'requestDueInfo(request.due_date)',
    "case 'due_date':",
    'colspan="11"',
    "field === 'due_date'",
], 'main.js shows, edits and sorts the due date');

assert_file_contains('api/requests.php', ["case 'due_date':", 'r.due_date IS NULL'], 'API sorts by due date with undated last');
assert_file_contains('assets/css/work.css', ['.request-due', '.is-overdue', '.is-today', '.is-soon'], 'work.css styles the date chips by urgency');
assert_file_contains('sw.js', ["const CACHE_NAME = 'prisma-v43';"], 'service worker cache is bumped');

fwrite(STDOUT, "Request due date UI completed successfully.\n");

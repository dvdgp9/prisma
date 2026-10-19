<?php

// Plan "Para mí | Todo", PT4: la urgencia de cada mejora se puede cambiar desde la Vista global.

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

// --- Opciones del menú de urgencia (work-helpers.js), con Node.
$node = <<<'JS'
global.window = global;
require(process.argv[1]);
require(process.argv[2]);
const pick = options => options.map(o => [o.key, o.label, !!o.active]);
const today = new Date(2026, 9, 19);
const request = priority => ({ id: 5, title: 'Exportar', status: 'pending', priority, due_date: null, assignments: [{ id: 7 }] });
const groupOf = priority => groupWorkItems(buildWorkItems([], [request(priority)], { id: 7, role: 'admin' }), today, 'all').map(g => g.key);
console.log(JSON.stringify({
    high: pick(workPriorityMenuOptions('high')),
    unknown: pick(workPriorityMenuOptions('urgentísima')),
    groups: { critical: groupOf('critical'), medium: groupOf('medium') }
}));
JS;

$output = shell_exec('node -e ' . escapeshellarg($node) . ' '
    . escapeshellarg(__DIR__ . '/../assets/js/task-parser.js') . ' '
    . escapeshellarg(__DIR__ . '/../assets/js/work-helpers.js') . ' 2>&1');
$r = json_decode((string) $output, true);
if (!is_array($r)) {
    fwrite(STDERR, "FAIL: could not run workPriorityMenuOptions with node:\n{$output}\n");
    exit(1);
}

assert_same([
    ['critical', 'Crítica', false],
    ['high', 'Alta', true],
    ['medium', 'Media', false],
    ['low', 'Baja', false],
], $r['high'], 'priority menu lists the four levels, most urgent first, and marks the current one');
assert_same([
    ['critical', 'Crítica', false],
    ['high', 'Alta', false],
    ['medium', 'Media', false],
    ['low', 'Baja', false],
], $r['unknown'], 'an unknown priority marks nothing');
// Al pasar a crítica sin fecha, la mejora sube a "Urgente sin fecha" (por eso se repinta la lista).
assert_same(['urgent'], $r['groups']['critical'], 'a critical request without date goes to "Urgente sin fecha"');
assert_same(['nodate'], $r['groups']['medium'], 'a medium request without date stays in "Sin fecha"');

// --- Fila: chip de urgencia en todas las mejoras; pulsable solo con permiso de editar la mejora.
assert_file_contains('assets/js/work-helpers.js', ['global.workPriorityMenuOptions = workPriorityMenuOptions;'], 'work-helpers exports the priority menu options');
assert_file_contains('assets/js/my-work.js', [
    'function openMyWorkPriorityMenu(',
    'function saveMyWorkPriority(',
    'function mountMyWorkMenu(',
    'workPriorityMenuOptions(',
    'data-action="priority"',
    "case 'priority':",
    'capabilities?.edit === true',
    'JSON.stringify({ id: item.id, priority })',
    'my-work-priority',
], 'my-work.js shows the priority chip on every request and changes it from a menu');

$myWork = read_project_file('assets/js/my-work.js');
assert_same(false, strpos($myWork, "item.priority === 'critical' || item.priority === 'high'") !== false, 'the chip is no longer limited to critical and high');

// La API ya exige "edit" para cambiar la prioridad (no solo update_status).
assert_file_contains('includes/auth.php', ["['id', 'status', 'due_date']"], 'changing priority needs edit capability');

assert_file_contains('assets/css/work.css', [
    '.my-work-priority.is-quiet',
    'button.my-work-priority',
    'button.my-work-priority:focus-visible',
    '.work-menu-dot',
], 'work.css styles the quiet chips, the clickable chip and the menu dots');

fwrite(STDOUT, "My work priority completed successfully.\n");

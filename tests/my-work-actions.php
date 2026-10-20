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

// --- Opciones del menú de fecha (work-helpers.js + taskDateShortcuts de task-parser.js), con Node.
$node = <<<'JS'
global.window = global;
require(process.argv[1]);
require(process.argv[2]);
const pick = options => options.map(o => [o.key, o.label, o.value, o.hint || null, !!o.active]);
console.log(JSON.stringify({
    monday: pick(workDateMenuOptions(new Date(2026, 9, 19), '2026-10-20')),
    thursday: pick(workDateMenuOptions(new Date(2026, 9, 15), null))
}));
JS;

$output = shell_exec('node -e ' . escapeshellarg($node) . ' '
    . escapeshellarg(__DIR__ . '/../assets/js/task-parser.js') . ' '
    . escapeshellarg(__DIR__ . '/../assets/js/work-helpers.js') . ' 2>&1');
$r = json_decode((string) $output, true);
if (!is_array($r)) {
    fwrite(STDERR, "FAIL: could not run workDateMenuOptions with node:\n{$output}\n");
    exit(1);
}

// Lunes 19 oct con fecha el martes: "Próximo lunes" y "En una semana" coinciden, se queda uno; se puede quitar.
assert_same([
    ['today', 'Hoy', '2026-10-19', 'lun 19 oct', false],
    ['tomorrow', 'Mañana', '2026-10-20', 'mar 20 oct', true],
    ['nextMonday', 'Próximo lunes', '2026-10-26', 'lun 26 oct', false],
    ['pick', 'Elegir fecha…', null, null, false],
    ['clear', 'Quitar fecha', '', null, false],
], $r['monday'], 'date menu on a Monday marks the current date and offers to clear it');

// Jueves 15 oct sin fecha: los cuatro atajos, sin "Quitar fecha".
assert_same([
    ['today', 'Hoy', '2026-10-15', 'jue 15 oct', false],
    ['tomorrow', 'Mañana', '2026-10-16', 'vie 16 oct', false],
    ['nextMonday', 'Próximo lunes', '2026-10-19', 'lun 19 oct', false],
    ['nextWeek', 'En una semana', '2026-10-22', 'jue 22 oct', false],
    ['pick', 'Elegir fecha…', null, null, false],
], $r['thursday'], 'date menu without a date offers the four shortcuts');

// --- Acciones en "Mi trabajo"
assert_file_contains('assets/js/my-work.js', [
    'function completeMyWorkTask(',
    'function undoCompleteMyWorkTask(',
    'function openMyWorkDateMenu(',
    'function saveMyWorkDate(',
    'workDateMenuOptions(',
    'showPicker',
    "label: 'Deshacer'",
    '/tasks.php?task=${item.id}',
    "fetch('/api/tasks.php'",
    "fetch(isTask ? '/api/tasks.php' : '/api/requests.php'",
    'capabilities?.update_status === true',
    'class="my-work-link"',
    'data-action="complete"',
    'data-action="date"',
    "menu.setAttribute('role', 'menu')",
    'loadTasksCount',
], 'my-work.js completes tasks with undo and changes dates from each row');

// Dentro de un enlace no puede haber botones: el enlace es solo título + meta.
$myWork = read_project_file('assets/js/my-work.js');
assert_same(false, (bool) preg_match('/<a class="my-work-row/', $myWork), 'rows are no longer one big link');

// main.js: el aviso admite un botón de acción (Deshacer) y duración.
assert_file_contains('assets/js/main.js', ['config.action', 'toast-action', 'config.duration'], 'main.js toasts support an action button');

// Tareas: enlace directo tasks.php?task=ID.
assert_file_contains('assets/js/tasks.js', ["new URLSearchParams(window.location.search).get('task')", 'openTaskModal(linkedTaskId)'], 'tasks.php opens a task from ?task=ID');
assert_file_contains('tasks.php', ['/assets/js/tasks.js?v=16'], 'tasks page loads the new tasks.js');

assert_file_contains('assets/css/work.css', ['.my-work-check', '.my-work-date-btn', '.work-menu', '.work-menu-item'], 'work.css styles the check, the date button and its menu');
assert_file_contains('index.php', ['/assets/js/my-work.js?v=4', '/assets/js/task-parser.js'], 'index loads the new my-work.js and the date shortcuts');
assert_file_contains('sw.js', ["const CACHE_NAME = 'prisma-v45';"], 'service worker cache is bumped');

fwrite(STDOUT, "My work actions completed successfully.\n");

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
    $contents = file_get_contents(__DIR__ . '/../' . $relativePath);

    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            fwrite(STDERR, "FAIL: {$message} ({$relativePath} is missing: {$needle})\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

// Pure helpers from task-parser.js, executed with Node (no browser needed).
$node = <<<'JS'
global.window = global;
require(process.argv[1]);
const shortcuts = (y, m, d) => taskDateShortcuts(new Date(y, m - 1, d));
const task = { title: 'Revisar facturación', description: 'Mirar el IVA', app_name: 'Puri' };
console.log(JSON.stringify({
    thursday: shortcuts(2026, 10, 15),
    monday: shortcuts(2026, 10, 19),
    sunday: shortcuts(2026, 10, 18),
    monthEnd: shortcuts(2026, 10, 31),
    search: {
        empty: matchesTaskSearch(task, '   '),
        accents: matchesTaskSearch(task, 'facturacion'),
        app: matchesTaskSearch(task, 'PURI'),
        description: matchesTaskSearch(task, 'iva'),
        allWords: matchesTaskSearch(task, 'revisar puri'),
        missingWord: matchesTaskSearch(task, 'revisar reservas'),
        nullFields: matchesTaskSearch({ title: 'Llamar', description: null, app_name: null }, 'llam')
    }
}));
JS;

$output = shell_exec('node -e ' . escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/../assets/js/task-parser.js') . ' 2>&1');
$result = json_decode((string) $output, true);
if (!is_array($result)) {
    fwrite(STDERR, "FAIL: could not run task-parser.js helpers with node:\n{$output}\n");
    exit(1);
}

assert_same(['today' => '2026-10-15', 'tomorrow' => '2026-10-16', 'nextMonday' => '2026-10-19', 'nextWeek' => '2026-10-22'], $result['thursday'], 'postpone shortcuts from a Thursday');
assert_same('2026-10-26', $result['monday']['nextMonday'], 'on a Monday, "next Monday" is a week later');
assert_same('2026-10-19', $result['sunday']['nextMonday'], 'on a Sunday, "next Monday" is the following day');
assert_same(['today' => '2026-10-31', 'tomorrow' => '2026-11-01', 'nextMonday' => '2026-11-02', 'nextWeek' => '2026-11-07'], $result['monthEnd'], 'shortcuts cross the month boundary');
assert_same(
    ['empty' => true, 'accents' => true, 'app' => true, 'description' => true, 'allWords' => true, 'missingWord' => false, 'nullFields' => true],
    $result['search'],
    'search ignores accents and case, looks at title/description/app and needs every word'
);

// Contracts for each T2 piece.
assert_file_contains('assets/js/task-parser.js', ['global.taskDateShortcuts = taskDateShortcuts;', 'global.matchesTaskSearch = matchesTaskSearch;'], 'parser exports the new helpers');
assert_file_contains('api/tasks.php', [
    "\$_GET['completed_days']",
    'DATE_SUB(NOW(), INTERVAL',
    "isset(\$_GET['summary'])",
    "'completed_today'",
], 'API limits completed tasks and returns a daily summary');
assert_file_contains('assets/js/tasks.js', [
    'function completeTaskWithUndo(',
    "label: 'Deshacer'",
    'completed_days=7',
    'function loadTasksSummary(',
    'matchesTaskSearch(',
    'function showAllCompleted(',
], 'tasks.js: complete with undo, recent completed, summary and search');
assert_file_contains('assets/js/tasks-interactions.js', [
    'function openTaskMenu(',
    'function openPostponeMenu(',
    'function moveOverdueToToday(',
    'function startInlineTitleEdit(',
    'function openAppMenu(',
    'function handleTasksShortcut(',
    'function setupTaskSwipe(',
], 'tasks-interactions.js: menus, postpone, inline edit, shortcuts and swipe');
assert_file_contains('tasks.php', [
    'id="tasks-search"',
    'id="done-today"',
    '/assets/js/tasks-interactions.js?v=4',
    'class="tasks-shortcuts-hint"',
], 'tasks page has search, daily summary, shortcuts hint and the new script');
assert_file_contains('assets/css/tasks.css', [
    '.task-menu',
    '.task-item.is-completing',
    '.task-item.is-focused',
    '.toast-action',
    '.task-title-input',
    '.tasks-search',
], 'tasks.css styles the new pieces');
assert_file_contains('includes/sidebar.php', ['class="sidebar-mobile-toggle"'], 'sidebar has a mobile menu toggle');
assert_file_contains('assets/css/styles.css', ['.sidebar.is-mobile-open'], 'mobile sidebar collapses until opened');
assert_file_contains('assets/js/sidebar.js', ['function toggleMobileSidebar('], 'sidebar.js toggles the mobile menu');

// Ajustes tras la revisión del usuario: compartir desde la tarjeta y menú de posponer con título.
assert_file_contains('assets/js/tasks-interactions.js', [
    'function openShareMenu(',
    "item.type === 'toggle'",
    'task-menu-title',
    "task.due_date ? 'Posponer' : 'Poner fecha'",
    "case 's':",
], 'share menu, toggle items and titled menus');
assert_file_contains('assets/js/tasks.js', [
    'openShareMenu(${task.id}, this, event)',
    'iconoir-alarm',
    'iconoir-user-plus',
], 'card opens the share menu from the badge or a hover action, and postpone uses the alarm icon');
assert_file_contains('assets/css/tasks.css', ['.task-menu-title', '.task-badge-btn'], 'styles for menu titles and clickable share badges');
assert_file_contains('tasks.php', ['<kbd>s</kbd> compartir'], 'shortcut hint lists the share key');

fwrite(STDOUT, "Tasks T2 contract completed successfully.\n");

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

// --- Con quién se comparte una tarea, en texto (task-parser.js), con Node.
$node = <<<'JS'
global.window = global;
require(process.argv[1]);
const members = [
    { id: 6, full_name: 'Luis Ortega', username: 'luis' },
    { id: 8, full_name: 'Ana Ruiz', username: 'ana' },
    { id: 9, full_name: '', username: 'marta' }
];
const me = 7;
const s = ids => taskShareSummary(ids, members, me);
console.log(JSON.stringify({
    parse: [parseShareIds('6,8'), parseShareIds('8'), parseShareIds(null), parseShareIds(''), parseShareIds('6, x,8,6'), parseShareIds(undefined)],
    one: s([8]),
    two: s([8, 6]),
    three: s([6, 8, 9]),
    meOnly: s([7]),
    meAndOther: s([6, 7]),
    unknown: s([99]),
    unknownTwo: s([98, 99]),
    knownAndUnknown: s([6, 99]),
    username: s([9]),
    none: s([])
}));
JS;

$output = shell_exec('node -e ' . escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/../assets/js/task-parser.js') . ' 2>&1');
$r = json_decode((string) $output, true);
if (!is_array($r)) {
    fwrite(STDERR, "FAIL: could not run task share helpers with node:\n{$output}\n");
    exit(1);
}

assert_same([[6, 8], [8], [], [], [6, 8], []], $r['parse'], 'ids from GROUP_CONCAT are parsed safely (no duplicates, no junk)');
assert_same(['label' => 'Ana Ruiz', 'title' => 'Ana Ruiz', 'phrase' => 'con Ana Ruiz'], $r['one'], 'one person: their name');
assert_same(['label' => 'Ana Ruiz y Luis Ortega', 'title' => 'Ana Ruiz y Luis Ortega', 'phrase' => 'con Ana Ruiz y Luis Ortega'], $r['two'], 'two people: both names, sorted');
assert_same(['label' => 'Ana Ruiz y 2 más', 'title' => 'Ana Ruiz, Luis Ortega y marta', 'phrase' => 'con Ana Ruiz, Luis Ortega y marta'], $r['three'], 'three or more: first name and how many more');
assert_same(['label' => 'Tú', 'title' => 'Tú', 'phrase' => 'contigo'], $r['meOnly'], 'shared with me: "Tú" ("contigo" in sentences)');
assert_same(['label' => 'Tú y Luis Ortega', 'title' => 'Tú y Luis Ortega', 'phrase' => 'contigo y con Luis Ortega'], $r['meAndOther'], '"Tú" goes first');
assert_same(['label' => '1 persona', 'title' => '1 persona', 'phrase' => 'con 1 persona'], $r['unknown'], 'unknown person (no longer in the team): count');
assert_same(['label' => '2 personas', 'title' => '2 personas', 'phrase' => 'con 2 personas'], $r['unknownTwo'], 'several unknown people: count');
assert_same(['label' => 'Luis Ortega y 1 más', 'title' => 'Luis Ortega y 1 más', 'phrase' => 'con Luis Ortega y 1 más'], $r['knownAndUnknown'], 'known plus unknown');
assert_same(['label' => 'marta', 'title' => 'marta', 'phrase' => 'con marta'], $r['username'], 'falls back to the username');
assert_same(['label' => '', 'title' => '', 'phrase' => ''], $r['none'], 'nobody');

// --- La API de la lista devuelve quiénes (no solo cuántos).
assert_file_contains('api/tasks.php', ['GROUP_CONCAT(ts_ids.user_id)', 'as share_user_ids'], 'task list returns the ids it is shared with');

// --- Tarjeta: nombres en ordenador, número en móvil.
assert_file_contains('assets/js/tasks.js', [
    'taskShareSummary(',
    'parseShareIds(',
    'class="task-badge-names"',
    'class="task-badge-count"',
    'document.body.dataset.userId',
], 'task card shows who the task is shared with');
assert_file_contains('assets/js/task-parser.js', ['Compartida ${summary.phrase ||'], 'badge title reads naturally ("Compartida contigo")');
assert_file_contains('assets/css/tasks.css', ['.task-badge-names', '.task-badge-count', '.task-menu-fixed'], 'tasks.css styles names, count and the fixed owner row');

// --- "Compartir con": "Solo para mí" y la persona creadora fija arriba.
assert_file_contains('assets/js/tasks-interactions.js', ["label: 'Solo para mí'", "label: 'Tú (es tuya)'", "type: 'fixed'", "item.type === 'fixed'"], 'share menu shows "Solo para mí" and the owner as "Tú (es tuya)"');
foreach (['assets/js/tasks.js', 'assets/js/tasks-interactions.js'] as $file) {
    assert_same(false, strpos(read_project_file($file), "'Solo yo'") !== false, "{$file} no longer says \"Solo yo\"");
}

assert_file_contains('tasks.php', [
    'data-user-id="<?php echo (int) $user[\'id\']; ?>"',
    'data-user-name=',
    '/assets/js/task-parser.js?v=9',
    '/assets/js/tasks.js?v=17',
    '/assets/js/tasks-interactions.js?v=5',
    '/assets/css/tasks.css?v=3.3',
], 'tasks page exposes the current user and loads the new assets');
assert_file_contains('index.php', ['/assets/js/task-parser.js?v=9'], 'index loads the new task-parser.js');
assert_file_contains('sw.js', ["const CACHE_NAME = 'prisma-v47';"], 'service worker cache is bumped');

fwrite(STDOUT, "Task share names completed successfully.\n");

<?php

// Creación rápida: asignar con "para Rebecca" o "+rebe" (20 oct 2026). Parser con Node + contratos.

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

$node = <<<'JS'
global.window = global;
require(process.argv[1]);
const members = [
    { id: 8, username: 'rebecca', full_name: 'Rebecca Soler' },
    { id: 9, username: 'luis', full_name: 'Luis Ortega' },
    { id: 10, username: 'laura', full_name: 'Laura Pérez' },
    { id: 11, username: 'lucia', full_name: 'Lucía Gómez' },
    { id: 12, username: 'mar', full_name: 'Mar Ruiz' },
    { id: 13, username: 'angela', full_name: '' }
];
const apps = [{ id: 1, name: 'Prisma' }];
const person = (text, list = members) => {
    const p = parseQuickTask(text, apps, list).person;
    return p && [p.id, p.name, p.match];
};
const title = (text) => quickTaskTitle(text, parseQuickTask(text, apps, members), {});
const marTuesday = parseQuickTask('Comprar flores +mar martes', apps, members);
console.log(JSON.stringify({
    paraName: person('Revisar textos de la web para Rebecca viernes'),
    paraLower: person('revisar textos para rebecca'),
    paraFullName: person('Revisar textos para Rebecca Soler mañana'),
    plusPrefix: person('Llamar al proveedor +rebe mañana'),
    plusSurname: person('+soler revisar el contrato'),
    plusUsername: person('Hablar con +angel'),
    paraClient: person('Preparar informe para el cliente'),
    paraPartialName: person('Preparar informe para la reunión'),
    paraPrefixOnly: person('Preparar informe para Rebe'),
    ambiguous: person('Hablar con +lu'),
    unique: person('Hablar con +lui'),
    plusInsideWord: person('Revisar C+rebe'),
    firstWins: person('Llamar para Luis +rebe'),
    noMembers: person('Revisar textos para Rebecca', []),
    noMembersArg: parseQuickTask('Revisar textos para Rebecca', apps).person,
    marPerson: marTuesday.person && marTuesday.person.id,
    marDate: marTuesday.date && marTuesday.date.match,
    appStillWorks: parseQuickTask('Revisar @prisma +rebe', apps, members).app.name,
    titleClean: title('Revisar textos de la web para Rebecca viernes'),
    titlePlus: title('Llamar al proveedor +rebe mañana @prisma'),
    titleFull: title('Revisar textos para Rebecca Soler'),
    titleIgnored: quickTaskTitle('Llamar +rebe mañana', parseQuickTask('Llamar +rebe mañana', apps, members), { person: true }),
    titleOnlyTokens: title('+rebe mañana')
}));
JS;

$output = shell_exec('node -e ' . escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/../assets/js/task-parser.js') . ' 2>&1');
$r = json_decode((string) $output, true);
if (!is_array($r)) {
    fwrite(STDERR, "FAIL: could not run the quick task parser with node:\n{$output}\n");
    exit(1);
}

assert_same([8, 'Rebecca Soler', 'para Rebecca'], $r['paraName'], '"para Rebecca" assigns it to Rebecca');
assert_same([8, 'Rebecca Soler', 'para rebecca'], $r['paraLower'], 'case does not matter');
assert_same([8, 'Rebecca Soler', 'para Rebecca Soler'], $r['paraFullName'], 'the surname after the name is taken too');
assert_same([8, 'Rebecca Soler', '+rebe'], $r['plusPrefix'], '"+rebe": the start of the name is enough');
assert_same([8, 'Rebecca Soler', '+soler'], $r['plusSurname'], '"+soler": the surname also works');
assert_same([13, 'angela', '+angel'], $r['plusUsername'], 'someone without a full name is found by username');
assert_same(null, $r['paraClient'], '"para el cliente" is not a person');
assert_same(null, $r['paraPartialName'], '"para la reunión" does not pick Laura (with "para" the name must be whole)');
assert_same(null, $r['paraPrefixOnly'], '"para Rebe" needs the whole name');
assert_same(null, $r['ambiguous'], '"+lu" matches Luis and Lucía: nobody is picked');
assert_same([9, 'Luis Ortega', '+lui'], $r['unique'], '"+lui" is only Luis');
assert_same(null, $r['plusInsideWord'], '"+" inside a word is not a person');
assert_same([9, 'Luis Ortega', 'para Luis'], $r['firstWins'], 'only one person: the first one written');
assert_same(null, $r['noMembers'], 'without a team nothing is detected');
assert_same(null, $r['noMembersArg'], 'callers that do not pass the team keep working');
assert_same(12, $r['marPerson'], '"+mar" is Mar…');
assert_same('martes', $r['marDate'], '…and does not turn into Tuesday; "martes" still does');
assert_same('Prisma', $r['appStillWorks'], 'apps with @ still work alongside a person');

assert_same('Revisar textos de la web', $r['titleClean'], 'the title drops the person and the date');
assert_same('Llamar al proveedor', $r['titlePlus'], 'the title drops "+rebe", the date and the app');
assert_same('Revisar textos', $r['titleFull'], 'the title drops the name and surname');
assert_same('Llamar +rebe', $r['titleIgnored'], 'a dismissed person stays as text in the title');
assert_same('+rebe mañana', $r['titleOnlyTokens'], 'if nothing is left, the text is kept as written');

// Servidor: al crear la tarea se asigna con el mismo flujo que "Compartir con" (visto y aviso).
assert_file_contains('api/tasks.php', [
    "\$input['shared_user_ids']",
    "replace_task_shares(\$db, \$user, \$taskId, false, \$input['shared_user_ids'])",
    'create_task_assignment_notifications($db, $taskId, $added, $user)',
], 'creating a task can assign it to someone');

// Tareas: chip de la persona con su "x" y envío al crear.
assert_file_contains('assets/js/tasks.js', [
    "parseQuickTask(input.value, typeof QUICK_APPS !== 'undefined' ? QUICK_APPS : [], teamMembers)",
    "dismissQuickChip('person')",
    'quickTaskTitle(raw, parsed, quickIgnore)',
    'taskData.shared_user_ids = [parsed.person.id]',
    'person: false',
], 'tasks quick add detects and sends the person');

// Vista global: el "+" flotante entiende lo mismo.
assert_file_contains('assets/js/main.js', [
    'function loadQuickTeamMembers()',
    'parseQuickTask(raw, apps, quickTeamMembers)',
    'quickTaskTitle(raw, parsed, {})',
    'payload.shared_user_ids = [parsed.person.id]',
], 'floating quick task detects and sends the person');

assert_file_contains('tasks.php', ['+nombre)', '/assets/js/task-parser.js?v=9', '/assets/js/tasks.js?v=16'], 'tasks page hints the syntax and loads the new scripts');
assert_file_contains('index.php', ['+nombre)', '/assets/js/task-parser.js?v=9', '/assets/js/main.js?v=5.5'], 'index hints the syntax and loads the new scripts');
assert_file_contains('sw.js', ["const CACHE_NAME = 'prisma-v45';"], 'service worker cache is bumped');

fwrite(STDOUT, "Quick task person completed successfully.\n");

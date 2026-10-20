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

// PT1 (plan "Para mí | Todo"): cada tarea dice para quién es (task-parser.js), con Node.
$node = <<<'JS'
global.window = global;
require(process.argv[1]);
const members = [
    { id: 6, full_name: 'Luis Ortega' },
    { id: 8, full_name: 'Rebecca Ruiz' },
    { id: 7, full_name: 'David Gutiérrez' }
];
const me = 7;
const pick = r => [r.kind, r.label, r.count, r.title, r.icon];
const t = (extra) => Object.assign({ id: 1, user_id: 7, is_shared: 0, share_count: 0, shared_user_ids: [], creator_name: 'David Gutiérrez' }, extra);
console.log(JSON.stringify({
    mine: pick(taskForWhom(t({}), members, me)),
    delegated: pick(taskForWhom(t({ share_count: 1, shared_user_ids: [8] }), members, me)),
    delegatedTwo: pick(taskForWhom(t({ share_count: 2, shared_user_ids: [8, 6] }), members, me)),
    team: pick(taskForWhom(t({ is_shared: '1' }), members, me)),
    sharedWithMe: pick(taskForWhom(t({ user_id: 8, share_count: 1, shared_user_ids: [7], creator_name: 'Rebecca Ruiz' }), members, me)),
    idsNotLoaded: pick(taskForWhom(t({ share_count: 2, shared_user_ids: undefined }), members, me)),
    othersPrivate: pick(taskForWhom(t({ user_id: 8, creator_name: 'Rebecca Ruiz' }), members, me))
}));
JS;

$output = shell_exec('node -e ' . escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/../assets/js/task-parser.js') . ' 2>&1');
$r = json_decode((string) $output, true);
if (!is_array($r)) {
    fwrite(STDERR, "FAIL: could not run taskForWhom with node:\n{$output}\n");
    exit(1);
}

assert_same(['me', 'Tú', 'Tú', 'Solo para ti', 'iconoir-user'], $r['mine'], 'own private task: "Tú"');
assert_same(['people', 'Rebecca Ruiz', '1', 'Compartida con Rebecca Ruiz', 'iconoir-user'], $r['delegated'], 'delegated task: who has it');
assert_same(['people', 'Luis Ortega y Rebecca Ruiz', '2', 'Compartida con Luis Ortega y Rebecca Ruiz', 'iconoir-user'], $r['delegatedTwo'], 'delegated to two people');
assert_same(['team', 'Equipo', 'Equipo', 'Compartida con todo el equipo', 'iconoir-group'], $r['team'], 'team task');
assert_same(['people', 'Tú', '1', 'Compartida contigo', 'iconoir-user'], $r['sharedWithMe'], 'task someone shared with me');
assert_same(['people', '2 personas', '2', 'Compartida con 2 personas', 'iconoir-user'], $r['idsNotLoaded'], 'falls back to the count when ids are unknown');
assert_same(['owner', 'Rebecca Ruiz', 'Rebecca Ruiz', 'De Rebecca Ruiz', 'iconoir-user'], $r['othersPrivate'], 'someone else\'s private task: their name');

// Tarjeta: siempre lleva la etiqueta; en las tuyas, al pulsarla se abre "Compartir con" y sustituye al botón.
assert_file_contains('assets/js/tasks.js', ['taskForWhom(task, teamMembers, CURRENT_USER_ID)'], 'task card uses taskForWhom for every task');
$tasksJs = read_project_file('assets/js/tasks.js');
assert_same(false, strpos($tasksJs, 'title="Compartir (s)"') !== false, 'the separate share button is gone (the chip opens "Compartir con")');
assert_file_contains('assets/js/tasks-interactions.js', ["focusedCard.querySelector('.task-badge-btn')"], 'the "s" shortcut still anchors the menu on the chip');
assert_file_contains('tasks.php', ['/assets/js/task-parser.js?v=9', '/assets/js/tasks.js?v=16'], 'tasks page loads the new scripts');

fwrite(STDOUT, "Tasks for whom completed successfully.\n");

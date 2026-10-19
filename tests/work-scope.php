<?php

// Plan "Para mí | Todo", PT2: alcance de la Vista global (assets/js/work-helpers.js), con Node.
// Decisiones del usuario (19 oct 2026):
//   "Para mí": tus tareas privadas y las que te han pasado por nombre; mejoras asignadas a ti
//              (también para superadmin). Ni delegadas ni de equipo.
//   "Todo":    todas las tareas que ves (también delegadas y de equipo) y todas las mejoras activas,
//              con para quién es cada una y filtro por persona.

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

$node = <<<'JS'
global.window = global;
require(process.argv[1]);
require(process.argv[2]);

const members = [{ id: 8, full_name: 'Ana Ruiz' }, { id: 9, full_name: 'Rebecca Soler' }];
const admin = { id: 7, role: 'admin', members };
const superadmin = { id: 7, role: 'superadmin', members };

const task = (id, fields) => Object.assign({
    id, title: `Tarea ${id}`, user_id: 7, is_shared: 0, is_completed: 0, share_count: 0, share_user_ids: null,
    due_date: null, created_at: '2026-10-01 09:00:00', creator_name: 'David Gutiérrez'
}, fields);
const tasks = [
    task(1, {}),                                                                          // mía, privada
    task(2, { share_user_ids: '9', share_count: 1 }),                                     // delegada a Rebecca
    task(3, { is_shared: 1 }),                                                            // mía, de equipo
    task(4, { user_id: 9, share_user_ids: '7', share_count: 1, creator_name: 'Rebecca Soler' }),       // me la pasa Rebecca
    task(5, { user_id: 9, share_user_ids: '7,8', share_count: 2, creator_name: 'Rebecca Soler' }),     // a mí y a Ana
    task(6, { user_id: 8, is_shared: 1, creator_name: 'Ana Ruiz' }),                      // de equipo, de Ana
    task(7, { share_user_ids: '8,9', share_count: 2 }),                                   // delegada a Ana y Rebecca
    (() => { const t = task(8, { share_count: 1 }); delete t.share_user_ids; return t; })(), // delegada, sin ids (API antigua)
    task(9, { is_completed: 1 })                                                          // completada
];

const ana = { id: 8, full_name: 'Ana Ruiz' };
const me = { id: 7, full_name: 'David Gutiérrez' };
const req = (id, status, assignments) => ({
    id, title: `Mejora ${id}`, priority: 'medium', status, due_date: null, assignments,
    created_at: '2026-10-02 10:00:00', app_name: 'Puri'
});
const requests = [
    req(1, 'pending', [me]),
    req(2, 'in_progress', [ana]),
    req(3, 'pending', []),
    req(4, 'pending', [me, ana]),
    req(5, 'completed', [me])
];

const keys = items => items.map(i => i.key).sort();
const mine = buildWorkItems(tasks, requests, admin, 'mine');
const all = buildWorkItems(tasks, requests, admin, 'all');
const whom = key => { const i = all.find(x => x.key === key); return [i.forWhom.kind, i.forWhom.label]; };

console.log(JSON.stringify({
    mine: keys(mine),
    defaultScope: keys(buildWorkItems(tasks, requests, admin)),
    superMine: keys(buildWorkItems(tasks, requests, superadmin, 'mine')),
    all: keys(all),
    superAll: keys(buildWorkItems(tasks, requests, superadmin, 'all')),
    forWhom: {
        t1: whom('task-1'), t2: whom('task-2'), t3: whom('task-3'), t4: whom('task-4'),
        t5: whom('task-5'), t7: whom('task-7'), t8: whom('task-8'),
        r1: whom('request-1'), r2: whom('request-2'), r3: whom('request-3'), r4: whom('request-4')
    },
    from: {
        t1: all.find(i => i.key === 'task-1').from,
        t4: mine.find(i => i.key === 'task-4').from,
        t6: all.find(i => i.key === 'task-6').from,
        r1: all.find(i => i.key === 'request-1').from
    },
    people: workPeople(all, admin),
    peopleMine: workPeople(mine, admin),
    byPerson: {
        all: filterWorkItemsByPerson(all, 'all').length,
        me: keys(filterWorkItemsByPerson(all, '7')),
        ana: keys(filterWorkItemsByPerson(all, '8')),
        rebecca: keys(filterWorkItemsByPerson(all, '9')),
        none: keys(filterWorkItemsByPerson(all, 'none')),
        gone: keys(filterWorkItemsByPerson(all, '42'))
    }
}));
JS;

$output = shell_exec('node -e ' . escapeshellarg($node) . ' '
    . escapeshellarg(__DIR__ . '/../assets/js/task-parser.js') . ' '
    . escapeshellarg(__DIR__ . '/../assets/js/work-helpers.js') . ' 2>&1');
$r = json_decode((string) $output, true);
if (!is_array($r)) {
    fwrite(STDERR, "FAIL: could not run the work scope helpers with node:\n{$output}\n");
    exit(1);
}

// --- Para mí
assert_same(['request-1', 'request-4', 'task-1', 'task-4', 'task-5'], $r['mine'], '"Para mí": my private tasks, tasks passed to me and requests assigned to me (no delegated, no team)');
assert_same($r['mine'], $r['defaultScope'], 'without scope it is "Para mí"');
assert_same($r['mine'], $r['superMine'], 'superadmin also sees only their own requests in "Para mí"');

// --- Todo
assert_same(
    ['request-1', 'request-2', 'request-3', 'request-4', 'task-1', 'task-2', 'task-3', 'task-4', 'task-5', 'task-6', 'task-7', 'task-8'],
    $r['all'],
    '"Todo": every visible open task (delegated and team too) and every active request'
);
assert_same($r['all'], $r['superAll'], '"Todo" is the same for admin and superadmin');

assert_same([
    't1' => ['me', 'Tú'],
    't2' => ['people', 'Rebecca Soler'],
    't3' => ['team', 'Equipo'],
    't4' => ['people', 'Tú'],
    't5' => ['people', 'Tú y Ana Ruiz'],
    't7' => ['people', 'Ana Ruiz y Rebecca Soler'],
    't8' => ['people', '1 persona'],
    'r1' => ['people', 'Tú'],
    'r2' => ['people', 'Ana Ruiz'],
    'r3' => ['unassigned', 'Sin asignar'],
    'r4' => ['people', 'Tú y Ana Ruiz'],
], $r['forWhom'], 'each item says who it is for');

assert_same(['t1' => null, 't4' => 'Rebecca Soler', 't6' => 'Ana Ruiz', 'r1' => null], $r['from'], 'tasks from someone else say who passed them ("De X")');

// --- Filtro por persona
assert_same([
    'people' => [
        ['key' => '7', 'label' => 'Tú', 'count' => 5],
        ['key' => '8', 'label' => 'Ana Ruiz', 'count' => 4],
        ['key' => '9', 'label' => 'Rebecca Soler', 'count' => 2],
    ],
    'unassigned' => 1,
], $r['people'], 'people filter: you first, then by name, with counts and unassigned requests');
// (El filtro solo se enseña en "Todo"; en "Para mí" los demás salen solo si comparten el item contigo.)
assert_same(['people' => [['key' => '7', 'label' => 'Tú', 'count' => 5], ['key' => '8', 'label' => 'Ana Ruiz', 'count' => 2]], 'unassigned' => 0], $r['peopleMine'], 'in "Para mí" everything includes you');

assert_same(12, $r['byPerson']['all'], 'person "all" keeps everything');
assert_same(['request-1', 'request-4', 'task-1', 'task-4', 'task-5'], $r['byPerson']['me'], 'filter by me');
assert_same(['request-2', 'request-4', 'task-5', 'task-7'], $r['byPerson']['ana'], 'filter by Ana: assigned requests and tasks shared with her');
assert_same(['task-2', 'task-7'], $r['byPerson']['rebecca'], 'filter by Rebecca: tasks delegated to her');
assert_same(['request-3'], $r['byPerson']['none'], '"Sin asignar" = requests nobody has');
assert_same([], $r['byPerson']['gone'], 'someone who is no longer in the list shows nothing');

fwrite(STDOUT, "Work scope completed successfully.\n");

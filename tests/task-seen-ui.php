<?php

// "Visto" en tareas asignadas (20 oct 2026): helpers de task-parser.js con Node + contratos de pantalla.

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
    { id: 6, full_name: 'Luis Ortega' },
    { id: 8, full_name: 'Rebecca Ruiz' }
];
const me = 7;
const now = new Date(2026, 9, 20, 16, 0, 0); // 20 oct 2026, 16:00 (hora local)
const t = (extra) => Object.assign({ id: 1, user_id: 7, is_shared: 0, is_completed: 0, share_status: [] }, extra);
const pick = r => r && [r.state, r.icon, r.label, r.title];
console.log(JSON.stringify({
    // Quien la recibe: "Nueva" mientras no la haya visto.
    newForMe: taskIsNewForMe(t({ user_id: 8, share_status: [{ user_id: '7', seen_at: null }] }), me),
    seenByMe: taskIsNewForMe(t({ user_id: 8, share_status: [{ user_id: 7, seen_at: '2026-10-20 10:42:00' }] }), me),
    notMine: taskIsNewForMe(t({ user_id: 8, share_status: [{ user_id: 6, seen_at: null }] }), me),
    ownTask: taskIsNewForMe(t({ share_status: [{ user_id: 7, seen_at: null }] }), me),
    oldApi: taskIsNewForMe({ id: 1, user_id: 8 }, me),

    // Quien la asigna: ✓ / ✓✓ con cuándo la vio cada persona.
    unseen: pick(taskSeenReceipt(t({ share_status: [{ user_id: 8, seen_at: null }] }), members, me, now)),
    seenToday: pick(taskSeenReceipt(t({ share_status: [{ user_id: '8', seen_at: '2026-10-20 10:42:00' }] }), members, me, now)),
    seenYesterday: pick(taskSeenReceipt(t({ share_status: [{ user_id: 8, seen_at: '2026-10-19 09:05:00' }] }), members, me, now)),
    seenEarlier: pick(taskSeenReceipt(t({ share_status: [{ user_id: 8, seen_at: '2026-10-14 18:30:00' }] }), members, me, now)),
    partial: pick(taskSeenReceipt(t({ share_status: [{ user_id: 8, seen_at: '2026-10-20 10:42:00' }, { user_id: 6, seen_at: null }] }), members, me, now)),
    unknownPerson: pick(taskSeenReceipt(t({ share_status: [{ user_id: 99, seen_at: null }] }), members, me, now)),
    // Sin check: tarea de otra persona, de todo el equipo, completada, sin personas o API antigua.
    othersTask: taskSeenReceipt(t({ user_id: 8, share_status: [{ user_id: 7, seen_at: null }] }), members, me, now),
    team: taskSeenReceipt(t({ is_shared: '1', share_status: [{ user_id: 8, seen_at: null }] }), members, me, now),
    done: taskSeenReceipt(t({ is_completed: 1, share_status: [{ user_id: 8, seen_at: null }] }), members, me, now),
    noPeople: taskSeenReceipt(t({}), members, me, now),
    oldApiReceipt: taskSeenReceipt({ id: 1, user_id: 7, share_count: 1 }, members, me, now)
}));
JS;

$output = shell_exec('node -e ' . escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/../assets/js/task-parser.js') . ' 2>&1');
$r = json_decode((string) $output, true);
if (!is_array($r)) {
    fwrite(STDERR, "FAIL: could not run the seen helpers with node:\n{$output}\n");
    exit(1);
}

assert_same(true, $r['newForMe'], 'a task assigned to me that I have not seen is new');
assert_same(false, $r['seenByMe'], 'once seen it is no longer new');
assert_same(false, $r['notMine'], 'a task assigned to someone else is not new for me');
assert_same(false, $r['ownTask'], 'my own tasks are never new');
assert_same(false, $r['oldApi'], 'without share_status (old API) nothing is new');

assert_same(['unseen', 'iconoir-check', 'Sin ver', 'Rebecca Ruiz aún no la ha visto'], $r['unseen'], 'not seen yet: single check');
assert_same(['seen', 'iconoir-double-check', 'Vista', 'Rebecca Ruiz la vio hoy a las 10:42'], $r['seenToday'], 'seen today: double check with the time');
assert_same(['seen', 'iconoir-double-check', 'Vista', 'Rebecca Ruiz la vio ayer a las 09:05'], $r['seenYesterday'], 'seen yesterday');
assert_same(['seen', 'iconoir-double-check', 'Vista', 'Rebecca Ruiz la vio el 14 oct a las 18:30'], $r['seenEarlier'], 'seen on an earlier day');
assert_same(['partial', 'iconoir-check', 'Vista por 1 de 2', 'Luis Ortega aún no la ha visto · Rebecca Ruiz la vio hoy a las 10:42'], $r['partial'], 'two people, one has seen it');
assert_same(['unseen', 'iconoir-check', 'Sin ver', 'Otra persona aún no la ha visto'], $r['unknownPerson'], 'someone no longer in the team');
assert_same(null, $r['othersTask'], 'no receipt on tasks someone else assigned');
assert_same(null, $r['team'], 'no receipt on whole-team tasks');
assert_same(null, $r['done'], 'no receipt on completed tasks');
assert_same(null, $r['noPeople'], 'no receipt without people');
assert_same(null, $r['oldApiReceipt'], 'no receipt without share_status');

// Marcar como vista: una vez por carga y solo lo que se ha pintado.
assert_file_contains('assets/js/sidebar.js', [
    'function markTasksSeen(taskIds)',
    "method: 'PUT'",
    'seen_task_ids: ids',
    'tasksSeenSent',
], 'sidebar.js marks tasks as seen once per page load');

// Tareas: check en la etiqueta "Para quién", "Nueva" y visto al pintar o abrir.
assert_file_contains('assets/js/tasks.js', [
    'taskSeenReceipt(task, teamMembers, CURRENT_USER_ID, new Date())',
    'task-seen task-seen--${receipt.state}',
    'taskIsNewForMe(task, CURRENT_USER_ID)',
    'task-new-pill',
    'markTasksSeen(visible.filter(t => taskIsNewForMe(t, CURRENT_USER_ID)).map(t => t.id))',
    'markTasksSeen([task.id])',
], 'tasks page shows the receipt, the "Nueva" pill and marks rendered tasks as seen');

// Vista global: "Nueva" en "Para mí", check en "De todos" y visto al pintar.
assert_file_contains('assets/js/my-work.js', [
    'taskSeenReceipt(item.raw, myWork.members || [], viewerId, new Date())',
    'my-work-new',
    'markTasksSeen(',
], 'my work shows "Nueva", the receipt and marks rendered tasks as seen');

assert_file_contains('assets/css/tasks.css', ['.task-seen', '.task-seen--seen', '.task-new-pill', '.task-seen-facts'], 'tasks styles for the receipt and the pill');
assert_file_contains('assets/css/work.css', ['.my-work-new', '.my-work-whom .task-seen'], 'my work styles for the pill and the receipt');

// Versiones (la PWA cachea los assets).
assert_file_contains('tasks.php', ['/assets/js/task-parser.js?v=9', '/assets/js/tasks.js?v=16', '/assets/css/tasks.css?v=3.3'], 'tasks page loads the new assets');
assert_file_contains('index.php', ['/assets/js/task-parser.js?v=9', '/assets/js/my-work.js?v=4', '/assets/css/work.css?v=5'], 'index loads the new assets');
assert_file_contains('sw.js', ["const CACHE_NAME = 'prisma-v45';"], 'service worker cache is bumped');

fwrite(STDOUT, "Task seen UI completed successfully.\n");

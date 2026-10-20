<?php

// Panel de avance del equipo en "De todos" (20 oct 2026): lógica de work-helpers.js con Node + contratos.

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
require(process.argv[2]);
const members = [{ id: 8, full_name: 'Rebecca Soler' }, { id: 9, full_name: 'Luis Ortega' }];
const viewer = { id: 7, role: 'superadmin', members };
const now = new Date(2026, 9, 20, 16, 0, 0); // martes 20 oct 2026, 16:00
const task = (extra) => Object.assign({ user_id: 7, is_shared: 0, is_completed: 0, share_count: 0, share_user_ids: '', creator_name: 'David Gutiérrez' }, extra);
const tasks = [
    task({ id: 1, title: 'Atrasada de Rebecca', share_count: 1, share_user_ids: '8', due_date: '2026-10-19' }),
    task({ id: 2, title: 'Hecha hoy por Rebecca', share_count: 1, share_user_ids: '8', is_completed: 1, completed_at: '2026-10-20 12:40:00', completed_by: 8, completed_by_name: 'Rebecca Soler' }),
    task({ id: 3, title: 'Hecha antes de la migración', share_count: 1, share_user_ids: '9', is_completed: 1, completed_at: '2026-10-16 09:00:00', completed_by: null }),
    task({ id: 4, title: 'Mía de hace 19 días', is_completed: 1, completed_at: '2026-10-01 10:00:00', completed_by: 7 }),
    task({ id: 5, title: 'Mía pendiente' }),
    task({ id: 6, title: 'Del equipo', is_shared: 1 }),
    task({ id: 7, title: 'De Rebecca para mí, hecha ayer', user_id: 8, creator_name: 'Rebecca Soler', share_count: 1, share_user_ids: '7', is_completed: '1', completed_at: '2026-10-19 18:05:00', completed_by: '7' })
];
const requests = [
    { id: 10, title: 'Mejora cerrada', status: 'completed', completed_at: '2026-10-20 09:15:16', priority: 'high', assignments: [{ id: 9, full_name: 'Luis Ortega' }] },
    { id: 11, title: 'Descartada', status: 'discarded', completed_at: '2026-10-20 10:00:00', assignments: [{ id: 8, full_name: 'Rebecca Soler' }] },
    { id: 12, title: 'En curso de Rebecca', status: 'in_progress', priority: 'medium', due_date: '2026-10-30', assignments: [{ id: 8, full_name: 'Rebecca Soler' }] },
    { id: 13, title: 'Cerrada hace mucho', status: 'completed', completed_at: '2026-09-01 10:00:00', assignments: [{ id: 9, full_name: 'Luis Ortega' }] },
    { id: 14, title: 'Sin asignar', status: 'pending', priority: 'low', assignments: [] }
];
const keys = list => list.map(i => i.key);
const done7 = buildDoneItems(tasks, requests, viewer, doneSince('7', now));
const open = buildWorkItems(tasks, requests, viewer, 'all');
const byKey = Object.fromEntries(done7.map(i => [i.key, i]));
const fmt = d => `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()} ${d.getHours()}:${d.getMinutes()}`;
console.log(JSON.stringify({
    sinceToday: fmt(doneSince('today', now)),
    since7: fmt(doneSince('7', now)),
    since30: fmt(doneSince('30', now)),
    sinceUnknown: fmt(doneSince('raro', now)),
    doneToday: keys(buildDoneItems(tasks, requests, viewer, doneSince('today', now))),
    done7: keys(done7),
    done30: keys(buildDoneItems(tasks, requests, viewer, doneSince('30', now))),
    who: done7.map(i => [i.key, i.doneBy, i.whoLabel, i.whoTitle, i.personIds]),
    doneRow: [byKey['task-2'].type, byKey['task-2'].id, byKey['task-2'].title, byKey['task-2'].doneAt, byKey['request-10'].type],
    openStillOpen: keys(open).sort(),
    filterLuis: keys(filterWorkItemsByPerson(done7, '9')),
    summary7: teamSummary(open, done7, viewer, now),
    summaryToday: teamSummary(open, buildDoneItems(tasks, requests, viewer, doneSince('today', now)), viewer, now).map(p => [p.label, p.done]),
    when: ['2026-10-20 12:40:00', '2026-10-19 18:05:00', '2026-10-16 09:00:00', '2026-10-01 10:00:00', '2025-12-30 10:00:00', 'roto'].map(v => doneWhenLabel(v, now))
}));
JS;

$output = shell_exec('node -e ' . escapeshellarg($node) . ' '
    . escapeshellarg(__DIR__ . '/../assets/js/task-parser.js') . ' '
    . escapeshellarg(__DIR__ . '/../assets/js/work-helpers.js') . ' 2>&1');
$r = json_decode((string) $output, true);
if (!is_array($r)) {
    fwrite(STDERR, "FAIL: could not run work-helpers.js with node:\n{$output}\n");
    exit(1);
}

assert_same('2026-10-20 0:0', $r['sinceToday'], '"Hoy" starts at midnight');
assert_same('2026-10-14 0:0', $r['since7'], '"7 días" = today and the 6 days before');
assert_same('2026-9-21 0:0', $r['since30'], '"30 días" = today and the 29 days before');
assert_same('2026-10-14 0:0', $r['sinceUnknown'], 'an unknown period falls back to 7 days');

assert_same(['task-2', 'request-10'], $r['doneToday'], 'done today: task and request, newest first');
assert_same(['task-2', 'request-10', 'task-7', 'task-3'], $r['done7'], 'done in 7 days, newest first (discarded requests do not count)');
assert_same(['task-2', 'request-10', 'task-7', 'task-3', 'task-4'], $r['done30'], 'done in 30 days');

assert_same([
    ['task-2', 'Rebecca Soler', 'Rebecca Soler', 'Completada por Rebecca Soler', [8]],
    ['request-10', null, 'Luis Ortega', 'Asignada a Luis Ortega', [9]],
    ['task-7', 'Tú', 'Tú', 'Completada por ti', [7]],
    ['task-3', null, 'Luis Ortega', 'Compartida con Luis Ortega', [9]],
], $r['who'], 'who did it: whoever completed it, or (if unknown) whoever had it');
assert_same(['task', 2, 'Hecha hoy por Rebecca', '2026-10-20 12:40:00', 'request'], $r['doneRow'], 'done items keep type, id, title and when');
assert_same(['request-12', 'request-14', 'task-1', 'task-5', 'task-6'], $r['openStillOpen'], 'pending work is unchanged by completed tasks in the data');
assert_same(['request-10', 'task-3'], $r['filterLuis'], 'the person filter also applies to what is done');

assert_same([
    ['key' => '7', 'label' => 'Tú', 'pending' => 1, 'overdue' => 0, 'done' => 1],
    ['key' => '9', 'label' => 'Luis Ortega', 'pending' => 0, 'overdue' => 0, 'done' => 2],
    ['key' => '8', 'label' => 'Rebecca Soler', 'pending' => 2, 'overdue' => 1, 'done' => 1],
], $r['summary7'], 'one card per person: you first, then by name; team tasks and unassigned requests count for nobody');
assert_same([['Tú', 0], ['Luis Ortega', 1], ['Rebecca Soler', 1]], $r['summaryToday'], 'cards count what is done in the chosen period');

assert_same(['hoy 12:40', 'ayer 18:05', 'vie 16 oct', '1 oct', '30 dic 2025', ''], $r['when'], 'when it was done, short');

// ---- Pantalla ----
assert_file_contains('assets/js/my-work.js', [
    "/api/tasks.php?completed=1&completed_days=30&shared=1",
    "'prisma_mywork_done_period'",
    'buildDoneItems(list.data, myWork.requests, viewer, doneSince(myWork.donePeriod, now))',
    'teamSummary(',
    'function renderMyWorkTeam(',
    'function myWorkDoneGroupHtml(',
    'my-work-row--done',
    'task.completed_by = viewerId',
], 'my work loads recent completions and renders the team cards and the done group');

assert_file_contains('index.php', [
    'id="work-team"',
    'id="work-team-cards"',
    'data-done-period="today"',
    'data-done-period="7"',
    'data-done-period="30"',
    '/assets/css/team.css?v=1',
    '/assets/js/work-helpers.js?v=5',
    '/assets/js/my-work.js?v=5',
], 'index has the team panel and loads the new files');

assert_file_contains('assets/css/team.css', [
    '.work-team',
    '.work-team-card',
    '.work-team-card[aria-pressed="true"]',
    '.work-period-btn',
    '.my-work-row--done',
], 'team panel styles');

fwrite(STDOUT, "Team progress completed successfully.\n");

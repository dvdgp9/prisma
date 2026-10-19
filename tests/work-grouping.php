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

// Agrupación de "Mi trabajo" (assets/js/work-helpers.js), ejecutada con Node.
// Reglas del plan v2 (decisiones del usuario, 19 oct 2026):
//   Atrasado / Hoy / Urgente sin fecha (solo críticas) / Próximos 7 días / Más adelante / Sin fecha.
//   Dentro de cada grupo: fecha, prioridad (las tareas cuentan como media) y antigüedad.
//   Superadmin ve todas las mejoras activas; el resto, las que tiene asignadas.
$node = <<<'JS'
global.window = global;
require(process.argv[1]);
const today = new Date(2026, 9, 19); // lunes 19 oct 2026

const tasks = [
    { id: 1, title: 'Llamar al proveedor', due_date: '2026-10-18', is_completed: 0, created_at: '2026-10-01 09:00:00', app_name: null },
    { id: 2, title: 'Revisar facturas', due_date: '2026-10-19', is_completed: '0', created_at: '2026-10-10 09:00:00', app_name: 'Puri' },
    { id: 3, title: 'Preparar demo', due_date: '2026-10-22', is_completed: 0, created_at: '2026-10-11 09:00:00' },
    { id: 4, title: 'Ordenar carpeta', due_date: null, is_completed: 0, created_at: '2026-10-12 09:00:00' },
    { id: 5, title: 'Ya hecha', due_date: '2026-10-19', is_completed: 1, created_at: '2026-10-12 09:00:00' }
];
const ana = { id: 8, full_name: 'Ana Ruiz' };
const david = { id: 7, full_name: 'David Gutiérrez' };
const req = (id, title, priority, status, due, assignments, created) => ({
    id, title, priority, status, due_date: due, assignments, created_at: created, app_name: 'Puri', comment_count: 0
});
const requests = [
    req(1, 'Error al subir fotos', 'critical', 'pending', null, [ana], '2026-10-05 10:00:00'),
    req(2, 'Revisar textos del email', 'low', 'pending', '2026-10-26', [ana], '2026-10-02 10:00:00'),
    req(3, 'Exportar a Excel', 'high', 'in_progress', null, [ana], '2026-10-03 10:00:00'),
    req(4, 'Filtro por fecha', 'medium', 'pending', '2026-10-19', [ana], '2026-10-01 10:00:00'),
    req(5, 'Migrar servidor', 'critical', 'pending', '2026-11-10', [ana, david], '2026-10-04 10:00:00'),
    req(6, 'Caída del login', 'critical', 'pending', null, [], '2026-10-06 10:00:00'),
    req(7, 'Login con Google', 'medium', 'completed', '2026-10-19', [ana], '2026-09-01 10:00:00'),
    req(8, 'Modo oscuro', 'low', 'pending', null, [david], '2026-10-07 10:00:00'),
    req(9, 'Pagos rotos', 'critical', 'in_progress', '2026-10-19', [ana], '2026-10-15 10:00:00'),
    req(10, 'Descartada', 'high', 'discarded', null, [ana], '2026-10-01 10:00:00')
];

const shape = groups => groups.map(g => ({ key: g.key, label: g.label, items: g.items.map(i => i.key) }));
const anaItems = buildWorkItems(tasks, requests, { id: 8, role: 'programador' });
const davidItems = buildWorkItems(tasks, requests, { id: 7, role: 'superadmin' });
const urgent = groupWorkItems(anaItems, today).find(g => g.key === 'urgent');
const sample = anaItems.find(i => i.key === 'request-5');

console.log(JSON.stringify({
    ana: shape(groupWorkItems(anaItems, today)),
    anaTasks: shape(groupWorkItems(anaItems, today, 'task')),
    anaRequests: shape(groupWorkItems(anaItems, today, 'request')),
    anaCounts: countWorkItems(anaItems),
    david: shape(groupWorkItems(davidItems, today, 'request')),
    davidCounts: countWorkItems(davidItems),
    urgentHint: urgent.hint,
    sample: { type: sample.type, id: sample.id, title: sample.title, dueDate: sample.dueDate, priority: sample.priority, status: sample.status, appName: sample.appName, assignees: sample.assignees },
    task: (({ type, id, priority, status, assignees }) => ({ type, id, priority, status, assignees }))(anaItems.find(i => i.key === 'task-2')),
    unassigned: davidItems.find(i => i.key === 'request-6').assignees,
    empty: shape(groupWorkItems([], today))
}));
JS;

$helpers = __DIR__ . '/../assets/js/work-helpers.js';
$output = shell_exec('node -e ' . escapeshellarg($node) . ' ' . escapeshellarg($helpers) . ' 2>&1');
$r = json_decode((string) $output, true);
if (!is_array($r)) {
    fwrite(STDERR, "FAIL: could not run work-helpers.js grouping with node:\n{$output}\n");
    exit(1);
}

assert_same([
    ['key' => 'overdue', 'label' => 'Atrasado', 'items' => ['task-1']],
    ['key' => 'today', 'label' => 'Hoy', 'items' => ['request-9', 'request-4', 'task-2']],
    ['key' => 'urgent', 'label' => 'Urgente sin fecha', 'items' => ['request-1']],
    ['key' => 'week', 'label' => 'Próximos 7 días', 'items' => ['task-3', 'request-2']],
    ['key' => 'later', 'label' => 'Más adelante', 'items' => ['request-5']],
    ['key' => 'nodate', 'label' => 'Sin fecha', 'items' => ['request-3', 'task-4']],
], $r['ana'], 'Ana sees her tasks and assigned requests grouped by when, then priority, then age');

// El ejemplo del usuario: crítica sin fecha (urgent) va antes que baja con fecha la semana que viene (week).
$keys = array_column($r['ana'], 'key');
assert_same(true, array_search('urgent', $keys) < array_search('week', $keys), 'critical without date shows before a low one dated next week');
assert_same('Ponles fecha para planificarlas', $r['urgentHint'], 'urgent group nudges to plan');

assert_same([
    ['key' => 'overdue', 'label' => 'Atrasado', 'items' => ['task-1']],
    ['key' => 'today', 'label' => 'Hoy', 'items' => ['task-2']],
    ['key' => 'week', 'label' => 'Próximos 7 días', 'items' => ['task-3']],
    ['key' => 'nodate', 'label' => 'Sin fecha', 'items' => ['task-4']],
], $r['anaTasks'], 'filter "task" keeps only tasks and drops empty groups');
assert_same([
    ['key' => 'today', 'label' => 'Hoy', 'items' => ['request-9', 'request-4']],
    ['key' => 'urgent', 'label' => 'Urgente sin fecha', 'items' => ['request-1']],
    ['key' => 'week', 'label' => 'Próximos 7 días', 'items' => ['request-2']],
    ['key' => 'later', 'label' => 'Más adelante', 'items' => ['request-5']],
    ['key' => 'nodate', 'label' => 'Sin fecha', 'items' => ['request-3']],
], $r['anaRequests'], 'filter "request" keeps only requests');
assert_same(['all' => 10, 'task' => 4, 'request' => 6], $r['anaCounts'], 'counts per filter (completed and discarded excluded)');

// Superadmin: todas las mejoras activas, también sin asignar o de otras personas.
assert_same([
    ['key' => 'today', 'label' => 'Hoy', 'items' => ['request-9', 'request-4']],
    ['key' => 'urgent', 'label' => 'Urgente sin fecha', 'items' => ['request-1', 'request-6']],
    ['key' => 'week', 'label' => 'Próximos 7 días', 'items' => ['request-2']],
    ['key' => 'later', 'label' => 'Más adelante', 'items' => ['request-5']],
    ['key' => 'nodate', 'label' => 'Sin fecha', 'items' => ['request-3', 'request-8']],
], $r['david'], 'superadmin sees every active request');
assert_same(['all' => 12, 'task' => 4, 'request' => 8], $r['davidCounts'], 'superadmin counts');
assert_same([], $r['unassigned'], 'unassigned request has no assignees');

assert_same(['type' => 'request', 'id' => 5, 'title' => 'Migrar servidor', 'dueDate' => '2026-11-10', 'priority' => 'critical', 'status' => 'pending', 'appName' => 'Puri', 'assignees' => ['Ana Ruiz', 'David Gutiérrez']], $r['sample'], 'request item shape');
assert_same(['type' => 'task', 'id' => 2, 'priority' => null, 'status' => null, 'assignees' => []], $r['task'], 'task item shape');
assert_same([], $r['empty'], 'no work, no groups');

fwrite(STDOUT, "Work grouping completed successfully.\n");

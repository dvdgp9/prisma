// node --test tests/request-quick-add.test.js
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const context = vm.createContext({ window: null, document: { addEventListener() {} }, FormData, console });
context.window = context;
for (const file of ['task-parser', 'request-create-helpers', 'request-parser', 'request-create']) {
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../assets/js', file + '.js'), 'utf8'), context);
}
const apps = [
    { id: 1, name: 'Prisma' }, { id: 2, name: 'Portal Cliente' },
    { id: 3, name: 'Prisma Ventas' }, { id: 4, name: 'Mañana' }
];
const people = [
    { id: 7, full_name: 'Mar Soler' }, { id: 8, full_name: 'Lucía Ruiz' },
    { id: 9, full_name: 'Luis Cano' }
];
const parse = (raw, options) => JSON.parse(JSON.stringify(context.parseQuickRequest(raw, apps, people, options)));

test('una mejora completa llega al payload y a sus extras sin perder la descripción', () => {
    const result = parse('Exportar informes @prisma +mar +lucía mañana prioridad alta dificultad baja\nConservar el filtro de hoy y avisar a +luis en usuario@empresa.es.\n\nSolicitante: Marta Gil\nEmail: marta@empresa.es\n- [ ] Añadir botón\n- [ ] Revisar permisos');
    assert.equal(result.title, 'Exportar informes');
    assert.equal(result.app.id, 1);
    assert.equal(result.priority, 'high');
    assert.equal(result.difficulty, 'low');
    assert.deepEqual(result.people.map(p => p.id), [7, 8]);
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const iso = `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, '0')}-${String(tomorrow.getDate()).padStart(2, '0')}`;
    assert.equal(result.date.value, iso);
    assert.equal(result.description, 'Conservar el filtro de hoy y avisar a +luis en usuario@empresa.es.');
    assert.deepEqual(result.checklist, ['Añadir botón', 'Revisar permisos']);
    assert.deepEqual(result.errors, []);
    const { draft, fields } = context.quickRequestData(result, 2);
    const payload = JSON.parse(JSON.stringify(context.newRequestPayload(draft, fields)));
    assert.equal(payload.app_id, 1);
    assert.equal(payload.requester_name, 'Marta Gil');
    assert.equal(payload.requester_email, 'marta@empresa.es');
    assert.equal(payload.due_date, iso);
    assert.deepEqual(Array.from(draft.assignees, p => p.id), [7, 8]);
});

test('título simple, descripción en línea, nombres de app con espacios y fecha explícita', () => {
    assert.equal(parse('Mejorar búsqueda').title, 'Mejorar búsqueda');
    const result = parse('Mejorar búsqueda @"Portal Cliente" para Mar Soler 15/07/2027 !crítica :: Mantener mañana y @prisma literalmente.');
    assert.equal(result.title, 'Mejorar búsqueda');
    assert.equal(result.app.id, 2);
    assert.equal(result.date.value, '2027-07-15');
    assert.equal(result.priority, 'critical');
    assert.equal(result.people[0].id, 7);
    assert.equal(result.description, 'Mantener mañana y @prisma literalmente.');
    assert.equal(parse('Error -- Cómo reproducirlo').description, 'Cómo reproducirlo');
    assert.equal(parse('Revisar para mañana').title, 'Revisar');
});

test('descartar detecciones conserva el texto original y no genera campos', () => {
    const raw = 'Revisar @prisma +mar mañana prioridad alta dificultad baja';
    const result = parse(raw, { ignore: { app: true, person: true, date: true, priority: true, difficulty: true, requester: true, checklist: true } });
    assert.equal(result.title, raw);
    assert.equal(result.app, null);
    assert.equal(result.date, null);
    assert.deepEqual(result.people, []);
    const lines = parse('Título\nSolicitante: Marta\n- [ ] Paso', { ignore: { requester: true, checklist: true } });
    assert.equal(lines.description, 'Solicitante: Marta\n- [ ] Paso');
});

test('ambigüedad, metadatos vacíos e información inválida se detectan antes de guardar', () => {
    assert.equal(parse('Título @pri').app, null);
    assert.equal(parse('Título @pri').errors.length, 1);
    assert.equal(parse('Título @desconocida').errors.length, 1);
    assert.equal(parse('Título +lu').people.length, 0);
    assert.equal(parse('Título +lu').errors.length, 1);
    assert.equal(parse('Título @prisma @"Portal Cliente"').errors.length, 1);
    assert.equal(parse('Título\nEmail: incorrecto').errors.length, 1);
    assert.equal(parse('Título\n- [ ] ' + 'a'.repeat(501)).errors.length, 1);
    assert.equal(parse('@prisma mañana').title, '');
    assert.equal(parse('Revisar para la reunión').people.length, 0);
});

test('los nombres de personas y aplicaciones no se interpretan como fechas', () => {
    assert.equal(parse('Revisar @mañana').date, null);
    assert.equal(parse('Revisar @mañana', { ignore: { app: true } }).date, null);
    assert.equal(parse('Revisar +mar').date, null);
    assert.equal(parse('Revisar +mar', { ignore: { person: true } }).date, null);
});

test('el rol usuario conserva fecha y personas como texto y puede crear checklist', () => {
    const result = parse('Revisar +mar mañana\n- [ ] Comprobar', { canPlan: false });
    assert.equal(result.title, 'Revisar +mar mañana');
    assert.equal(result.date, null);
    assert.deepEqual(result.people, []);
    assert.deepEqual(result.checklist, ['Comprobar']);
    const { draft } = context.quickRequestData(result, 2);
    assert.equal(draft.appId, 2);
});

test('el guardado compartido envía cada extra y devuelve fallos parciales sin recrear', async () => {
    const calls = [];
    context.fetch = async (url, options) => {
        calls.push({ url, body: JSON.parse(options.body) });
        return { ok: true, status: 200, json: async () => url === '/api/requests.php'
            ? { success: true, data: { id: 42 } }
            : url === '/api/assignments.php' ? { success: false, error: 'Sin acceso' } : { success: true } };
    };
    const { draft, fields } = context.quickRequestData(parse('Título @prisma +mar\n- [ ] Paso'), null);
    const created = await context.persistNewRequest(draft, fields);
    assert.equal(created.requestId, 42);
    assert.equal(created.problems.length, 1);
    assert.deepEqual(calls.map(c => c.url), ['/api/requests.php', '/api/assignments.php', '/api/request-checklist.php']);
    assert.deepEqual(calls[1].body, { request_id: 42, user_ids: [7] });
    assert.deepEqual(calls[2].body, { request_id: 42, title: 'Paso' });
});

test('un error al crear no envía asignaciones ni pasos', async () => {
    const calls = [];
    context.fetch = async url => {
        calls.push(url);
        return { ok: false, json: async () => ({ success: false, error: 'No tienes acceso' }) };
    };
    const { draft, fields } = context.quickRequestData(parse('Título @prisma +mar\n- [ ] Paso'), null);
    await assert.rejects(context.persistNewRequest(draft, fields), /No tienes acceso/);
    assert.deepEqual(calls, ['/api/requests.php']);
});

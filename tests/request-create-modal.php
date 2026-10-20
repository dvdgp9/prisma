<?php

// Modal "Nueva mejora" con el lenguaje del modal de tareas v2 (20 oct 2026).
// Helpers puros con Node + contratos de markup, JS, CSS y versiones.

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

function assert_contains_all($haystack, $needles, $message)
{
    foreach ($needles as $needle) {
        if (strpos($haystack, $needle) === false) {
            fwrite(STDERR, "FAIL: {$message} (missing: {$needle})\n");
            exit(1);
        }
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

function assert_contains_none($haystack, $needles, $message)
{
    foreach ($needles as $needle) {
        if (strpos($haystack, $needle) !== false) {
            fwrite(STDERR, "FAIL: {$message} (still contains: {$needle})\n");
            exit(1);
        }
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

// ---- Helpers puros (request-create-helpers.js) con Node ----
$helpersPath = __DIR__ . '/../assets/js/request-create-helpers.js';
if (!is_file($helpersPath)) {
    fwrite(STDERR, "FAIL: assets/js/request-create-helpers.js does not exist\n");
    exit(1);
}

$node = <<<'JS'
global.window = global;
require(process.argv[1]);
const empty = { title: '', description: '', requesterName: '', requesterEmail: '' };
const withApp = (appId, changes) => Object.assign(newRequestDraft({ appId }), changes || {});
const users = [
    { id: 1, username: 'rebecca', full_name: 'Rebecca Soler' },
    { id: 2, username: 'lucia', full_name: 'Lucía Gómez' },
    { id: 3, username: 'angela', full_name: '' }
];
const apps = [
    { id: '1', name: 'Web', company_name: 'Acme' },
    { id: '2', name: 'App', company_name: 'Beta' },
    { id: '3', name: 'CRM', company_name: 'Acme' }
];
const now = new Date(2026, 9, 20, 9, 5);
const ids = (list) => list.map(u => u.id);
let assignees = toggleNewRequestAssignee([], users[1]);
assignees = toggleNewRequestAssignee(assignees, users[0]);
const twoAssignees = assignees.slice();
assignees = toggleNewRequestAssignee(assignees, { id: '2' });
const split = splitNewRequestFiles([{ name: 'a.png', size: 100 }, { name: 'b.zip', size: 16 * 1024 * 1024 }]);
let checklist = addNewRequestChecklistItem([], '  Paso 1  ');
checklist = addNewRequestChecklistItem(checklist, '   ');
checklist = addNewRequestChecklistItem(checklist, 'Paso 2');

console.log(JSON.stringify({
    draft: newRequestDraft({ appId: 4 }),
    payloadMinimal: newRequestPayload(withApp(3), { title: '  Exportar a PDF  ', description: '\n Detalle \n', requesterName: '', requesterEmail: '' }),
    payloadFull: newRequestPayload(withApp(5, { priority: 'high', difficulty: 'low', dueDate: '2026-10-24' }), { title: 'X', description: '', requesterName: ' Ana ', requesterEmail: 'ana@x.es ' }),
    missingAll: newRequestMissing(newRequestDraft({}), Object.assign({}, empty, { title: '   ' })),
    missingNone: newRequestMissing(withApp(2), Object.assign({}, empty, { title: 'Algo' })),
    dirty: [
        newRequestIsDirty(withApp(2), empty),
        newRequestIsDirty(withApp(2, { priority: 'critical', difficulty: 'high' }), empty),
        newRequestIsDirty(withApp(2), Object.assign({}, empty, { title: 'a' })),
        newRequestIsDirty(withApp(2), Object.assign({}, empty, { description: 'a' })),
        newRequestIsDirty(withApp(2, { files: [{ name: 'x' }] }), empty),
        newRequestIsDirty(withApp(2, { checklist: ['x'] }), empty),
        newRequestIsDirty(withApp(2), Object.assign({}, empty, { requesterEmail: 'a@b.c' }))
    ],
    priority: ['critical', 'high', 'medium', 'low', 'rara'].map(newRequestPriorityLabel),
    difficulty: ['', 'low', 'medium', 'high'].map(newRequestDifficultyLabel),
    difficultyOptions: newRequestDifficultyOptions('medium'),
    assigneesLabel: [
        newRequestAssigneesLabel([]),
        newRequestAssigneesLabel([users[0]]),
        newRequestAssigneesLabel([users[0], users[1], users[2]])
    ],
    toggled: [ids(twoAssignees), ids(assignees)],
    filtered: [
        ids(filterNewRequestPeople(users, 'luc')),
        ids(filterNewRequestPeople(users, 'LUCIA')),
        ids(filterNewRequestPeople(users, 'gom')),
        ids(filterNewRequestPeople(users, '  ')),
        ids(filterNewRequestPeople(users, 'ang'))
    ],
    groups: groupNewRequestApps(apps).map(g => [g.company, g.apps.map(a => a.name)]),
    defaultApp: [
        newRequestDefaultApp(apps, 'app', 3),
        newRequestDefaultApp(apps, 'app', 99),
        newRequestDefaultApp(apps, 'home', null),
        newRequestDefaultApp([apps[1]], 'home', null),
        newRequestDefaultApp([], 'home', null)
    ],
    pasted: [
        pastedFileName('image.png', 'image/png', now, 0, 1),
        pastedFileName('image.png', 'image/jpeg', now, 1, 2),
        pastedFileName('', 'image/webp', now, 0, 1),
        pastedFileName('informe.pdf', 'application/pdf', now, 0, 1)
    ],
    split: [split.accepted.map(f => f.name), split.rejected.map(f => f.name)],
    maxBytes: NEW_REQUEST_MAX_FILE_BYTES,
    checklist,
    permissions: ['user', 'programador', 'admin', 'superadmin', ''].map(newRequestPermissions)
}));
JS;

$output = shell_exec('node -e ' . escapeshellarg($node) . ' ' . escapeshellarg($helpersPath) . ' 2>&1');
$result = json_decode((string) $output, true);
if (!is_array($result)) {
    fwrite(STDERR, "FAIL: could not run request-create-helpers.js with node:\n{$output}\n");
    exit(1);
}

assert_same(
    ['appId' => 4, 'priority' => 'medium', 'difficulty' => '', 'dueDate' => '', 'assignees' => [], 'files' => [], 'checklist' => []],
    $result['draft'],
    'a new draft starts with medium priority and nothing else chosen'
);
assert_same(
    ['app_id' => 3, 'title' => 'Exportar a PDF', 'description' => 'Detalle', 'priority' => 'medium', 'difficulty' => null],
    $result['payloadMinimal'],
    'minimal payload: trimmed title and description, no difficulty, no requester or date'
);
assert_same(
    ['app_id' => 5, 'title' => 'X', 'description' => '', 'priority' => 'high', 'difficulty' => 'low', 'requester_name' => 'Ana', 'requester_email' => 'ana@x.es', 'due_date' => '2026-10-24'],
    $result['payloadFull'],
    'full payload sends every field the API accepts'
);
assert_same(['title', 'app'], $result['missingAll'], 'title and app are required');
assert_same([], $result['missingNone'], 'nothing missing with title and app');
assert_same([false, false, true, true, true, true, true], $result['dirty'], 'only typed content, files or checklist count as unsaved work');
assert_same(['Prioridad crítica', 'Prioridad alta', 'Prioridad media', 'Prioridad baja', 'Prioridad media'], $result['priority'], 'priority chip labels');
assert_same(['Dificultad', 'Dificultad baja', 'Dificultad media', 'Dificultad alta'], $result['difficulty'], 'difficulty chip labels');
assert_same([
    ['key' => '', 'label' => 'Sin definir', 'active' => false],
    ['key' => 'low', 'label' => 'Baja', 'active' => false],
    ['key' => 'medium', 'label' => 'Media', 'active' => true],
    ['key' => 'high', 'label' => 'Alta', 'active' => false],
], $result['difficultyOptions'], 'difficulty menu options mark the current one');
assert_same([
    ['label' => 'Asignar', 'title' => 'Sin asignar', 'empty' => true],
    ['label' => 'Rebecca Soler', 'title' => 'Responsable: Rebecca Soler', 'empty' => false],
    ['label' => 'Rebecca Soler +2', 'title' => 'Responsable: Rebecca Soler · También: Lucía Gómez, angela', 'empty' => false],
], $result['assigneesLabel'], 'assignee chip label: the first person is the owner');
assert_same([[2, 1], [1]], $result['toggled'], 'toggling keeps the order and removes by id');
assert_same([[2], [2], [2], [1, 2, 3], [3]], $result['filtered'], 'people search ignores case and accents and matches username or name');
assert_same([['Acme', ['Web', 'CRM']], ['Beta', ['App']]], $result['groups'], 'apps are grouped by company in order of appearance');
assert_same([3, null, null, 2, null], $result['defaultApp'], 'default app: the app being viewed, or the only one');
assert_same(['captura-20261020-0905.png', 'captura-20261020-0905-2.jpg', 'captura-20261020-0905.webp', 'informe.pdf'], $result['pasted'], 'pasted screenshots get a recognisable name');
assert_same([['a.png'], ['b.zip']], $result['split'], 'files over the limit are rejected before creating');
assert_same(15728640, $result['maxBytes'], 'file limit matches api/upload.php (15 MB)');
assert_same(['Paso 1', 'Paso 2'], $result['checklist'], 'checklist items are trimmed and empty ones ignored');
assert_same([
    ['plan' => false, 'assign' => false, 'attach' => false],
    ['plan' => true, 'assign' => true, 'attach' => true],
    ['plan' => true, 'assign' => true, 'attach' => true],
    ['plan' => true, 'assign' => true, 'attach' => true],
    ['plan' => false, 'assign' => false, 'attach' => false],
], $result['permissions'], 'date, assignees and attachments follow the API permissions (edit roles only)');

// ---- Markup del modal (index.php) ----
$index = read_project_file('index.php');
$start = strpos($index, '<div class="modal" id="new-request-modal"');
$end = strpos($index, '<!-- Edit Request Modal');
assert_same(true, $start !== false && $end > $start, 'new request modal markup found');
$modal = substr($index, $start, $end - $start);
assert_contains_all($modal, [
    'role="dialog"',
    'aria-modal="true"',
    'aria-labelledby="new-request-heading"',
    'request-create-content',
    'id="new-request-bar"',
    'id="request-title"',
    'class="request-create-title"',
    'id="request-description"',
    'class="request-create-description"',
    'file-upload-area--compact',
    'pega una captura',
    'id="new-request-file-list"',
    'id="new-checklist-input"',
    'id="new-request-requester-name"',
    'id="new-request-requester-email"',
    'id="new-requester-dot"',
    'id="new-request-submit"',
    'Crear mejora',
    'requestCloseNewRequestModal()',
], 'new request modal has dialog semantics, chip bar, big title, compact upload, checklist, requester and footer');
assert_contains_none($modal, ['style="', 'modal-body-grid', 'modal-column-side', '<select', 'Nueva Petición', 'Crear Petición', 'Máximo 5MB'], 'new request modal has no inline styles, side column, selects or old texts');

// ---- Comportamiento (request-create.js) ----
$js = read_project_file('assets/js/request-create.js');
assert_contains_all($js, [
    'function openNewRequestModal(',
    'function requestCloseNewRequestModal(',
    'function resetNewRequestModal(',
    'function renderNewRequestBar(',
    'function openNewRequestMenu(',
    'function handleNewRequestKeydown(',
    'function trapNewRequestFocus(',
    'function handleNewRequestPaste(',
    'function addNewRequestFiles(',
    'function autoGrowNewRequestDescription(',
    'async function submitNewRequest(',
    "'/api/requests.php'",
    "'/api/assignments.php'",
    "'/api/request-checklist.php'",
    "'/api/upload.php'",
    'newRequestPayload(',
    'newRequestPermissions(',
    'workPriorityMenuOptions(',
    'workDateMenuOptions(',
], 'request-create.js renders the chip bar, menus, files, checklist, focus trap and sends everything to the API');
assert_contains_all($js, [
    "const focusedChip = bar.contains(document.activeElement)",
    "document.body.classList.add('is-creating-request')",
    "document.body.classList.remove('is-creating-request')",
], 'focus goes back to the chip after choosing, and the quick-task "+" is hidden while the modal is open');

$main = read_project_file('assets/js/main.js');
assert_contains_none($main, [
    'let selectedFiles',
    'function submitNewRequest(',
    'function openNewRequestModal(',
    'function setupFileUpload(',
    'function handleFiles(',
    'function renderPendingChecklist(',
    'function setupAssignSearchInput(',
    'function populateAppSelects(',
    'setupFileUpload();',
], 'main.js no longer carries the old new-request modal');
assert_contains_all($main, ["modalId === 'new-request-modal'", 'resetNewRequestModal()'], 'closeModal still resets the new request modal');

// ---- Estilos (work.css: hoja propia de index.php) ----
assert_contains_all(read_project_file('assets/css/work.css'), [
    '.request-create-content',
    '.request-create-chip',
    '.request-create-chip.is-empty',
    '.request-create-chip.is-invalid',
    '.request-create-title',
    '.request-create-description',
    '.request-create-menu',
    '.request-create-file',
    '.request-create-content.is-dragging',
    '#new-request-modal .request-create-content',
    'body.is-creating-request .floating-task-btn',
], 'work.css styles the new request modal, its chips and menus');

// ---- Scripts y versiones ----
assert_contains_all($index, [
    '/assets/css/work.css?v=6',
    '/assets/js/main.js?v=5.6',
    '/assets/js/request-create-helpers.js?v=1',
    '/assets/js/request-create.js?v=1',
], 'index loads the new scripts with bumped versions');
assert_same(
    true,
    strpos($index, 'request-create-helpers.js') < strpos($index, 'request-create.js?v=') && strpos($index, 'work-helpers.js') < strpos($index, 'request-create.js?v=') && strpos($index, 'main.js?v=') < strpos($index, 'request-create.js?v='),
    'request-create.js loads after its helpers, work-helpers.js and main.js'
);
assert_contains_all(read_project_file('sw.js'), ["const CACHE_NAME = 'prisma-v47';"], 'service worker cache is bumped');

foreach (['assets/js/request-create.js', 'assets/js/request-create-helpers.js', 'assets/js/main.js'] as $file) {
    $check = shell_exec('node --check ' . escapeshellarg(__DIR__ . '/../' . $file) . ' 2>&1');
    assert_same('', trim((string) $check), "{$file} parses");
}
$lint = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/../index.php') . ' 2>&1');
assert_same(true, strpos((string) $lint, 'No syntax errors') !== false, 'index.php has no PHP syntax errors');

fwrite(STDOUT, "Request create modal contract completed successfully.\n");

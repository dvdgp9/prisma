/**
 * Vista global "Mi trabajo": tareas y mejoras en una sola lista, agrupadas por
 * cuándo hay que hacerlas. "Para mí" enseña lo tuyo; "De todos" (programador,
 * admin y superadmin) todo lo que ves, con para quién es y filtro por persona.
 * El alcance, la agrupación y el orden viven en work-helpers.js (probados con
 * Node). De main.js usa: currentView, requests, unreadRequestIds, escapeHtml,
 * getPriorityLabel, showToast y openRequestDetailModal.
 */

const MY_WORK_FILTERS = ['all', 'task', 'request'];
const MY_WORK_SCOPES = ['mine', 'all'];
const MY_WORK_SCOPE_ROLES = ['programador', 'admin', 'superadmin']; // ven "De todos" (el resto, solo "Para mí")
const MY_WORK_GROUP_LIMIT = 8; // filas visibles por grupo antes de "Ver N más"

const MY_WORK_SCOPE_TEXT = {
    mine: { title: 'Mi trabajo', sub: 'Tus tareas y las mejoras que tienes asignadas' },
    all: { title: 'Trabajo del equipo', sub: 'Todas las tareas que ves y todas las mejoras activas' }
};

const myWork = {
    // Tareas de cada alcance: "Para mí" pide shared=0 y "De todos" shared=1 (incluye las de equipo).
    lists: {
        mine: { data: null, loading: false, error: false }, // data null = todavía sin cargar
        all: { data: null, loading: false, error: false }
    },
    members: null,         // equipo (/api/task-shares.php) para los nombres en "De todos"
    membersLoading: false,
    requests: [],          // copia de las mejoras cargadas en la Vista global
    requestsLoaded: false,
    requestsError: false,
    scope: readMyWorkScope(),
    filter: readMyWorkFilter(),
    person: 'all',         // filtro por persona de "De todos": 'all' | 'none' | id
    personNames: {},       // nombres vistos, para no perder la opción elegida si se queda sin nada
    expanded: new Set(),   // grupos con "Ver más" abierto
    items: [],             // items pintados ahora (para las acciones de cada fila)
    menu: null             // menú de fecha o urgencia abierto
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.work-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => setMyWorkFilter(btn.dataset.workFilter));
    });
    document.querySelectorAll('.work-scope-btn').forEach(btn => {
        btn.addEventListener('click', () => setMyWorkScope(btn.dataset.workScope));
    });
    document.getElementById('work-person')?.addEventListener('change', e => {
        myWork.person = e.target.value;
        renderMyWork();
    });
    document.getElementById('my-work-body')?.addEventListener('click', handleMyWorkClick);
    syncMyWorkFilterButtons();
    syncMyWorkScope();
});

// ========== Para mí | De todos (solo programador, admin y superadmin), recordado en el navegador ==========

function canSeeTeamWork() {
    return MY_WORK_SCOPE_ROLES.includes(document.body.dataset.userRole);
}

function readMyWorkScope() {
    if (!canSeeTeamWork()) return 'mine';
    try {
        return localStorage.getItem('prisma_mywork_scope') === 'all' ? 'all' : 'mine';
    } catch (error) {
        console.warn('Mi trabajo: no se pudo leer la vista guardada', error);
        return 'mine';
    }
}

function setMyWorkScope(scope) {
    if (!MY_WORK_SCOPES.includes(scope) || scope === myWork.scope || !canSeeTeamWork()) return;
    myWork.scope = scope;
    try {
        localStorage.setItem('prisma_mywork_scope', scope);
    } catch (error) {
        console.warn('Mi trabajo: no se pudo recordar la vista', error);
    }
    closeMyWorkMenu();
    syncMyWorkScope();
    loadMyWork();
}

function syncMyWorkScope() {
    const text = MY_WORK_SCOPE_TEXT[myWork.scope];
    const title = document.getElementById('my-work-title');
    const sub = document.getElementById('my-work-sub');
    if (title) title.textContent = text.title;
    if (sub) sub.textContent = text.sub;
    document.querySelectorAll('.work-scope-btn').forEach(btn => {
        btn.setAttribute('aria-pressed', btn.dataset.workScope === myWork.scope ? 'true' : 'false');
    });
    const personWrap = document.getElementById('work-person-wrap');
    if (personWrap) personWrap.hidden = myWork.scope !== 'all';
}

// ========== Filtro (Todo / Tareas / Mejoras), recordado en el navegador ==========

function readMyWorkFilter() {
    try {
        const stored = localStorage.getItem('prisma_mywork_filter');
        return MY_WORK_FILTERS.includes(stored) ? stored : 'all';
    } catch (error) {
        console.warn('Mi trabajo: no se pudo leer el filtro guardado', error);
        return 'all';
    }
}

function setMyWorkFilter(filter) {
    if (!MY_WORK_FILTERS.includes(filter) || filter === myWork.filter) return;
    myWork.filter = filter;
    try {
        localStorage.setItem('prisma_mywork_filter', filter);
    } catch (error) {
        console.warn('Mi trabajo: no se pudo recordar el filtro', error);
    }
    syncMyWorkFilterButtons();
    renderMyWork();
}

function syncMyWorkFilterButtons() {
    document.querySelectorAll('.work-filter-btn').forEach(btn => {
        const active = btn.dataset.workFilter === myWork.filter;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
}

// ========== Datos ==========

// Al abrir la Vista global o cambiar de alcance; force = true tras crear una tarea desde esta página.
function loadMyWork(forceTasks) {
    const scope = myWork.scope;
    if (forceTasks) {
        // La lista del otro alcance se vuelve a pedir cuando se abra.
        MY_WORK_SCOPES.filter(s => s !== scope && !myWork.lists[s].loading).forEach(s => { myWork.lists[s].data = null; });
    }
    const list = myWork.lists[scope];
    if (forceTasks || list.error || (list.data === null && !list.loading)) {
        loadMyWorkTasks(scope);
    }
    if (scope === 'all') loadMyWorkMembers();
    renderMyWork();
}

async function loadMyWorkTasks(scope) {
    const list = myWork.lists[scope];
    list.loading = true;
    list.error = false;
    try {
        const res = await fetch(`/api/tasks.php?completed=0&shared=${scope === 'all' ? 1 : 0}`);
        const data = await res.json();
        if (!data.success) throw new Error(`${res.status} ${data.error}`);
        list.data = data.data;
    } catch (error) {
        console.error('Mi trabajo: error cargando tareas', scope, error);
        list.error = true;
    }
    list.loading = false;
    renderMyWork();
}

// Nombres del equipo para "De todos". Si falla, se ve "1 persona" en vez del nombre (no bloquea).
async function loadMyWorkMembers() {
    if (myWork.members !== null || myWork.membersLoading) return;
    myWork.membersLoading = true;
    try {
        const res = await fetch('/api/task-shares.php');
        const data = await res.json();
        if (!data.success) throw new Error(`${res.status} ${data.error}`);
        myWork.members = data.data;
    } catch (error) {
        console.warn('Mi trabajo: no se pudo cargar el equipo; se verá el número de personas', error);
        myWork.members = [];
    }
    myWork.membersLoading = false;
    renderMyWork();
}

// La misma tarea puede estar en las dos listas: los cambios se aplican en ambas.
function myWorkTaskRecords(id) {
    return MY_WORK_SCOPES
        .map(scope => (myWork.lists[scope].data || []).find(t => Number(t.id) === Number(id)))
        .filter(Boolean);
}

// ========== Pintado ==========

/**
 * update (desde loadRequests de main.js en la Vista global):
 *   { requestsLoaded: true } guarda una copia de las mejoras recién cargadas;
 *   { requestsError: true } muestra el error.
 * Sin update, solo repinta (p. ej. al cambiar los avisos sin leer).
 */
function renderMyWork(update) {
    if (update && update.requestsLoaded) {
        myWork.requests = (typeof requests !== 'undefined' ? requests : []).slice();
        myWork.requestsLoaded = true;
        myWork.requestsError = false;
    }
    if (update && update.requestsError) {
        myWork.requestsError = true;
    }

    const body = document.getElementById('my-work-body');
    if (!body || typeof currentView === 'undefined' || currentView !== 'home') return;

    const scope = myWork.scope;
    const list = myWork.lists[scope];
    if (list.error || myWork.requestsError) {
        body.removeAttribute('aria-busy');
        body.innerHTML = myWorkMessageHtml('iconoir-warning-circle', scope === 'all' ? 'No se pudo cargar el trabajo del equipo' : 'No se pudo cargar tu trabajo',
            'Comprueba la conexión e inténtalo de nuevo.', '<button type="button" class="btn btn-secondary btn-sm" data-action="retry">Reintentar</button>');
        return;
    }

    if (list.data === null || !myWork.requestsLoaded || (scope === 'all' && myWork.members === null)) {
        body.setAttribute('aria-busy', 'true');
        body.innerHTML = myWorkSkeletonHtml();
        return;
    }
    body.removeAttribute('aria-busy');

    const viewer = { id: Number(document.body.dataset.userId), role: document.body.dataset.userRole, members: myWork.members || [] };
    let items = buildWorkItems(list.data, myWork.requests, viewer, scope);
    if (scope === 'all') {
        renderMyWorkPeople(workPeople(items, viewer));
        items = filterWorkItemsByPerson(items, myWork.person);
    }
    myWork.items = items;
    const counts = countWorkItems(items);
    MY_WORK_FILTERS.forEach(key => {
        const el = document.getElementById(`work-count-${key}`);
        if (el) el.textContent = counts[key];
    });

    const groups = groupWorkItems(items, new Date(), myWork.filter);
    body.innerHTML = groups.length
        ? groups.map(group => myWorkGroupHtml(group)).join('')
        : myWorkEmptyHtml();

    // Las tareas que te han asignado y ya se ven en pantalla cuentan como vistas (no las de "Ver más" cerrado).
    if (typeof markTasksSeen === 'function') {
        const shown = new Set([...body.querySelectorAll('.my-work-row--task')].map(row => Number(row.dataset.id)));
        markTasksSeen(items.filter(item => item.type === 'task' && shown.has(item.id) && taskIsNewForMe(item.raw, viewer.id)).map(item => item.id));
    }
}

/**
 * Opciones del filtro por persona ("De todos"): todas, tú, el resto por nombre y "Sin asignar".
 * Si la persona elegida se queda sin nada, su opción sigue (con 0) para que el filtro no salte.
 */
function renderMyWorkPeople({ people, unassigned }) {
    const select = document.getElementById('work-person');
    if (!select) return;

    people.forEach(person => { myWork.personNames[person.key] = person.label; });
    const options = [{ value: 'all', label: 'Todas las personas' }]
        .concat(people.map(person => ({ value: person.key, label: `${person.label} (${person.count})` })));
    if (unassigned > 0 || myWork.person === 'none') options.push({ value: 'none', label: `Sin asignar (${unassigned})` });
    if (!options.some(option => option.value === myWork.person)) {
        options.push({ value: myWork.person, label: `${myWork.personNames[myWork.person] || 'Persona'} (0)` });
    }

    const html = options.map(option => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`).join('');
    if (select.dataset.options !== html) { // sin cambios no se toca (el desplegable puede estar abierto)
        select.innerHTML = html;
        select.dataset.options = html;
    }
    select.value = myWork.person;
    select.closest('.work-person')?.classList.toggle('is-filtered', myWork.person !== 'all');
}

function myWorkGroupHtml(group) {
    const expanded = myWork.expanded.has(group.key);
    const visible = expanded ? group.items : group.items.slice(0, MY_WORK_GROUP_LIMIT);
    const hiddenCount = group.items.length - visible.length;

    return `
        <section class="my-work-group my-work-group--${group.key}" aria-labelledby="my-work-group-${group.key}">
            <div class="my-work-group-head">
                <h3 class="my-work-group-title" id="my-work-group-${group.key}">${group.label}</h3>
                <span class="my-work-group-count">${group.items.length}</span>
                ${group.hint ? `<span class="my-work-group-hint">${group.hint}</span>` : ''}
            </div>
            <ul class="my-work-list">
                ${visible.map(item => myWorkRowHtml(item, group.key)).join('')}
            </ul>
            ${hiddenCount > 0 ? `<button type="button" class="my-work-more" data-action="expand" data-group="${group.key}">Ver ${hiddenCount} más</button>` : ''}
            ${expanded && group.items.length > MY_WORK_GROUP_LIMIT ? `<button type="button" class="my-work-more" data-action="collapse" data-group="${group.key}">Ver menos</button>` : ''}
        </section>`;
}

function myWorkRowHtml(item, groupKey) {
    const isTask = item.type === 'task';
    const caps = item.raw.capabilities || {};
    const canComplete = isTask && caps.complete !== false;
    // Fecha: tareas con permiso de editar; mejoras con el mismo permiso que el estado.
    const canPlan = isTask ? caps.edit !== false : item.raw.capabilities?.update_status === true;
    // Urgencia: la API pide permiso de editar la mejora (programador, admin y superadmin).
    const canSetPriority = !isTask && item.raw.capabilities?.edit === true;
    const due = requestDueInfo(item.dueDate);
    const unread = !isTask && typeof unreadRequestIds !== 'undefined' && unreadRequestIds.has(item.id);
    const viewerId = Number(document.body.dataset.userId);

    const meta = [`<span class="my-work-kind">${isTask ? 'Tarea' : 'Mejora'}</span>`];
    // Te la han asignado y es la primera vez que la ves.
    if (isTask && taskIsNewForMe(item.raw, viewerId)) meta.push('<span class="my-work-new" title="Te la acaban de asignar">Nueva</span>');
    if (item.appName) meta.push(`<span>${escapeHtml(item.appName)}</span>`);
    if (!isTask && item.status === 'in_progress') meta.push('<span class="my-work-progress">En curso</span>');
    // "De todos": para quién es (tú, nombres, equipo o sin asignar). En "Para mí" todo es tuyo.
    if (myWork.scope === 'all' && item.forWhom) {
        // En las que has asignado tú: ✓ si aún no la han visto, ✓✓ cuando sí.
        const receipt = isTask ? taskSeenReceipt(item.raw, myWork.members || [], viewerId, new Date()) : null;
        const receiptHtml = receipt
            ? `<i class="task-seen task-seen--${receipt.state} ${receipt.icon}" aria-hidden="true"></i><span class="visually-hidden">· ${escapeHtml(receipt.label)}</span>`
            : '';
        const whomTitle = receipt ? `${item.forWhom.title} · ${receipt.title}` : item.forWhom.title;
        meta.push(item.forWhom.kind === 'unassigned'
            ? '<span class="my-work-unassigned">Sin asignar</span>'
            : `<span class="my-work-whom" title="${escapeHtml(whomTitle)}"><i class="${item.forWhom.kind === 'team' ? 'iconoir-group' : 'iconoir-user'}" aria-hidden="true"></i>${escapeHtml(item.forWhom.label)}${receiptHtml}</span>`);
    }
    // Tareas que te ha pasado otra persona (o de equipo creadas por otra persona).
    if (item.from) meta.push(`<span class="my-work-from">De ${escapeHtml(item.from)}</span>`);
    if (unread) meta.push('<span class="my-work-activity"><i class="iconoir-chat-bubble" aria-hidden="true"></i>Actividad nueva</span>');

    const lead = canComplete
        ? `<button type="button" class="my-work-check" data-action="complete" title="Completar" aria-label="Completar: ${escapeHtml(item.title)}"><i class="iconoir-check" aria-hidden="true"></i></button>`
        : `<span class="my-work-icon" aria-hidden="true"><i class="${isTask ? 'iconoir-task-list' : 'iconoir-light-bulb'}"></i></span>`;

    // Urgencia en todas las mejoras; media y baja en gris para que destaquen crítica y alta.
    let priorityControl = '';
    if (!isTask) {
        const label = escapeHtml(getPriorityLabel(item.priority));
        const quiet = item.priority === 'medium' || item.priority === 'low' ? ' is-quiet' : '';
        const priorityClass = `priority-badge priority-${item.priority} my-work-priority${quiet}`;
        priorityControl = canSetPriority
            ? `<button type="button" class="${priorityClass}" data-action="priority" aria-haspopup="menu" aria-expanded="false"
                       title="Cambiar la urgencia" aria-label="Urgencia: ${label}. Cambiar">${label}</button>`
            : `<span class="${priorityClass}" title="Urgencia">${label}</span>`;
    }

    let dateControl = '';
    if (canPlan) {
        dateControl = `
            <button type="button" class="request-due is-${due.tone} my-work-date-btn${item.dueDate ? '' : ' is-empty'}" data-action="date"
                    aria-haspopup="menu" aria-expanded="false" title="${item.dueDate ? 'Cambiar la fecha' : 'Poner fecha'}">
                <i class="iconoir-calendar" aria-hidden="true"></i><span>${escapeHtml(item.dueDate ? due.label : 'Fecha')}</span>
            </button>`;
    } else if (item.dueDate && groupKey !== 'today') { // sin permiso: solo se muestra (en "Hoy" sobra)
        dateControl = `<span class="request-due is-${due.tone}">${escapeHtml(due.label)}</span>`;
    }

    const href = isTask ? `/tasks.php?task=${item.id}` : `/index.php?open_request=${item.id}`;
    return `
        <li>
            <div class="my-work-row my-work-row--${item.type}" data-type="${item.type}" data-id="${item.id}">
                ${lead}
                <a class="my-work-link" href="${href}">
                    <span class="my-work-row-title">${escapeHtml(item.title)}</span>
                    <span class="my-work-meta">${meta.join('')}</span>
                </a>
                <span class="my-work-side">
                    ${priorityControl}
                    ${dateControl}
                </span>
            </div>
        </li>`;
}

function myWorkEmptyHtml() {
    const kinds = { all: 'trabajo pendiente', task: 'tareas pendientes', request: 'mejoras activas' };

    // "De todos" con una persona elegida: se dice de quién y se ofrece volver a todas.
    if (myWork.scope === 'all' && myWork.person !== 'all') {
        const showAll = '<button type="button" class="btn btn-secondary btn-sm" data-action="person-all">Ver todas las personas</button>';
        if (myWork.person === 'none') {
            return myWorkMessageHtml('iconoir-check-circle', 'No hay mejoras sin asignar', 'Todas las mejoras activas tienen a alguien.', showAll);
        }
        const isMe = Number(myWork.person) === Number(document.body.dataset.userId);
        const who = isMe ? 'ti' : escapeHtml(myWork.personNames[myWork.person] || 'esta persona');
        return myWorkMessageHtml('iconoir-check-circle', `Nada pendiente para ${who}`, `No hay ${kinds[myWork.filter] || kinds.all} con este filtro.`, showAll);
    }

    const messages = myWork.scope === 'all'
        ? {
            all: ['Todo al día', 'No hay tareas pendientes ni mejoras activas.'],
            task: ['Sin tareas pendientes', 'No hay tareas abiertas que puedas ver.'],
            request: ['No hay mejoras activas', 'Las nuevas aparecerán aquí.']
        }
        : {
            all: ['Todo al día', 'No tienes tareas pendientes ni mejoras asignadas.'],
            task: ['Sin tareas pendientes', 'Usa el botón + para apuntar una.'],
            request: ['No tienes mejoras asignadas', 'Aparecerán aquí en cuanto te asignen una.']
        };
    const [title, text] = messages[myWork.filter] || messages.all;
    return myWorkMessageHtml('iconoir-check-circle', title, text, '');
}

function myWorkMessageHtml(icon, title, text, actionHtml) {
    return `
        <div class="my-work-message">
            <span class="my-work-message-icon"><i class="${icon}" aria-hidden="true"></i></span>
            <p class="my-work-message-title">${title}</p>
            <p class="my-work-message-text">${text}</p>
            ${actionHtml}
        </div>`;
}

function myWorkSkeletonHtml() {
    const row = `
        <li class="my-work-skeleton-row">
            <span class="my-work-skeleton my-work-skeleton--icon"></span>
            <span class="my-work-skeleton-lines">
                <span class="my-work-skeleton my-work-skeleton--title"></span>
                <span class="my-work-skeleton my-work-skeleton--meta"></span>
            </span>
        </li>`;
    return `
        <div class="my-work-group" aria-hidden="true">
            <div class="my-work-group-head"><span class="my-work-skeleton my-work-skeleton--label"></span></div>
            <ul class="my-work-list">${row.repeat(3)}</ul>
        </div>`;
}

// ========== Clics ==========

function findMyWorkItem(row) {
    if (!row) return null;
    return myWork.items.find(item => item.type === row.dataset.type && item.id === Number(row.dataset.id)) || null;
}

function handleMyWorkClick(e) {
    const action = e.target.closest('[data-action]');
    if (action) {
        const item = findMyWorkItem(action.closest('.my-work-row'));
        const group = action.dataset.group;
        switch (action.dataset.action) {
            case 'complete':
                if (item) completeMyWorkTask(item);
                return;
            case 'date':
                if (item) openMyWorkDateMenu(action, item);
                return;
            case 'priority':
                if (item) openMyWorkPriorityMenu(action, item);
                return;
            case 'expand':
                myWork.expanded.add(group);
                break;
            case 'collapse':
                myWork.expanded.delete(group);
                break;
            case 'person-all':
                myWork.person = 'all';
                break;
            case 'retry':
                if (myWork.lists[myWork.scope].error) loadMyWorkTasks(myWork.scope);
                if (myWork.requestsError) {
                    myWork.requestsError = false;
                    if (typeof loadRequests === 'function') loadRequests();
                }
                break;
        }
        renderMyWork();
        return;
    }

    // Mejora: se abre el detalle aquí mismo. Tarea: el enlace abre esa tarea en Tareas.
    const link = e.target.closest('.my-work-row[data-type="request"] .my-work-link');
    if (link && typeof openRequestDetailModal === 'function') {
        e.preventDefault();
        openRequestDetailModal(Number(link.closest('.my-work-row').dataset.id));
    }
}

// ========== Completar tarea (con deshacer) ==========

async function completeMyWorkTask(item) {
    const records = myWorkTaskRecords(item.id);
    if (!records.length || Number(records[0].is_completed) === 1) return;

    records.forEach(task => { task.is_completed = 1; }); // sale de la lista al momento; si falla, vuelve
    renderMyWork();

    try {
        const res = await fetch('/api/tasks.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: item.id, is_completed: true })
        });
        const data = await res.json();
        if (!data.success) throw new Error(`${res.status} ${data.error}`);

        showToast({
            title: 'Tarea completada',
            message: escapeHtml(item.title),
            icon: 'iconoir-check',
            duration: 6000,
            action: { label: 'Deshacer', onClick: () => undoCompleteMyWorkTask(item.id) }
        }, 'toast-completed');
        if (typeof loadTasksCount === 'function') loadTasksCount();
    } catch (error) {
        console.error('Mi trabajo: no se pudo completar la tarea', item.id, error);
        records.forEach(task => { task.is_completed = 0; });
        renderMyWork();
        showMyWorkError('No se pudo completar la tarea');
    }
}

async function undoCompleteMyWorkTask(id) {
    try {
        const res = await fetch('/api/tasks.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, is_completed: false })
        });
        const data = await res.json();
        if (!data.success) throw new Error(`${res.status} ${data.error}`);

        myWorkTaskRecords(id).forEach(task => { task.is_completed = 0; });
        renderMyWork();
        if (typeof loadTasksCount === 'function') loadTasksCount();
    } catch (error) {
        console.error('Mi trabajo: no se pudo deshacer', id, error);
        showMyWorkError('No se pudo deshacer');
        loadMyWork(true);
    }
}

// ========== Fecha desde la fila ==========

function openMyWorkDateMenu(anchor, item) {
    const wasOpenHere = myWork.menu && myWork.menu.anchor === anchor;
    closeMyWorkMenu();
    if (wasOpenHere) return;

    const options = workDateMenuOptions(new Date(), item.dueDate);
    const itemHtml = option => `
        <button type="button" class="work-menu-item${option.active ? ' is-active' : ''}${option.key === 'clear' ? ' is-danger' : ''}"
                role="menuitem" data-key="${option.key}" data-value="${option.value || ''}">
            <span class="work-menu-label">${option.label}</span>
            ${option.hint ? `<span class="work-menu-hint">${option.hint}</span>` : ''}
            ${option.active ? '<i class="iconoir-check" aria-hidden="true"></i>' : ''}
        </button>`;

    const menu = createMyWorkMenu(item.type === 'task' ? 'Fecha de la tarea' : 'Fecha objetivo de la mejora', `
        <p class="work-menu-title">${item.type === 'task' ? 'Fecha de la tarea' : 'Fecha objetivo'}</p>
        ${options.filter(o => o.key !== 'pick' && o.key !== 'clear').map(itemHtml).join('')}
        <div class="work-menu-sep" role="separator"></div>
        ${options.filter(o => o.key === 'pick' || o.key === 'clear').map(itemHtml).join('')}
        <input type="date" class="work-menu-input" tabindex="-1" aria-label="Elegir fecha" value="${item.dueDate || ''}">`);

    const input = menu.querySelector('.work-menu-input');
    input.addEventListener('change', () => {
        if (!input.value) return;
        closeMyWorkMenu();
        saveMyWorkDate(item, input.value);
    });

    menu.addEventListener('click', e => {
        const btn = e.target.closest('[data-key]');
        if (!btn) return;
        if (btn.dataset.key === 'pick') {
            if (typeof input.showPicker === 'function') {
                try {
                    input.showPicker();
                    return;
                } catch (error) {
                    console.error('Mi trabajo: showPicker no disponible', error);
                }
            }
            input.focus();
            return;
        }
        closeMyWorkMenu();
        saveMyWorkDate(item, btn.dataset.value);
    });

    mountMyWorkMenu(anchor, menu);
}

function createMyWorkMenu(ariaLabel, html) {
    const menu = document.createElement('div');
    menu.className = 'work-menu';
    menu.setAttribute('role', 'menu');
    menu.setAttribute('aria-label', ariaLabel);
    menu.innerHTML = html;
    document.body.appendChild(menu);
    return menu;
}

// Común a los menús de la fila: posición junto al chip, flechas, Escape, Tab y clic fuera.
function mountMyWorkMenu(anchor, menu) {
    const rect = anchor.getBoundingClientRect();
    const left = Math.max(12, Math.min(rect.right - menu.offsetWidth, window.innerWidth - menu.offsetWidth - 12));
    const below = window.innerHeight - rect.bottom >= menu.offsetHeight + 12 || rect.top < menu.offsetHeight + 12;
    menu.style.left = `${left}px`;
    menu.style.top = below ? `${rect.bottom + 6}px` : `${rect.top - menu.offsetHeight - 6}px`;

    menu.addEventListener('keydown', e => {
        const buttons = Array.from(menu.querySelectorAll('[data-key]'));
        const index = buttons.indexOf(document.activeElement);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const next = e.key === 'ArrowDown' ? (index + 1) % buttons.length : (index - 1 + buttons.length) % buttons.length;
            buttons[next].focus();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closeMyWorkMenu(true);
        } else if (e.key === 'Tab') {
            closeMyWorkMenu();
        }
    });

    const onOutside = e => {
        if (!menu.contains(e.target) && !anchor.contains(e.target)) closeMyWorkMenu();
    };
    setTimeout(() => document.addEventListener('mousedown', onOutside), 0);
    window.addEventListener('resize', () => closeMyWorkMenu(), { once: true });

    myWork.menu = { menu, anchor, onOutside };
    anchor.setAttribute('aria-expanded', 'true');
    (menu.querySelector('.is-active[data-key]') || menu.querySelector('[data-key]')).focus();
}

// Tras guardar, la fila puede cambiar de grupo: el foco vuelve a su control (sin saltar de scroll).
function focusMyWorkControl(item, action) {
    const row = document.querySelector(`#my-work-body .my-work-row[data-type="${item.type}"][data-id="${item.id}"]`);
    row?.querySelector(`[data-action="${action}"]`)?.focus({ preventScroll: true });
}

// ========== Urgencia desde la fila (mejoras) ==========

function openMyWorkPriorityMenu(anchor, item) {
    const wasOpenHere = myWork.menu && myWork.menu.anchor === anchor;
    closeMyWorkMenu();
    if (wasOpenHere) return;

    const menu = createMyWorkMenu('Urgencia de la mejora', `
        <p class="work-menu-title">Urgencia</p>
        ${workPriorityMenuOptions(item.priority).map(option => `
            <button type="button" class="work-menu-item${option.active ? ' is-active' : ''}" role="menuitem" data-key="${option.key}">
                <span class="work-menu-dot work-menu-dot--${option.key}" aria-hidden="true"></span>
                <span class="work-menu-label">${option.label}</span>
                ${option.active ? '<i class="iconoir-check" aria-hidden="true"></i>' : ''}
            </button>`).join('')}`);

    menu.addEventListener('click', e => {
        const btn = e.target.closest('[data-key]');
        if (!btn) return;
        closeMyWorkMenu(true);
        saveMyWorkPriority(item, btn.dataset.key);
    });

    mountMyWorkMenu(anchor, menu);
}

async function saveMyWorkPriority(item, priority) {
    if (!priority || priority === item.priority) return;

    try {
        const res = await fetch('/api/requests.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: item.id, priority })
        });
        const data = await res.json();
        if (!data.success) throw new Error(`${res.status} ${data.error}`);

        // Copias locales: la fila se reordena (o pasa a "Urgente sin fecha") sin recargar.
        [myWork.requests, typeof requests !== 'undefined' ? requests : []].forEach(list => {
            const record = list.find(r => Number(r.id) === item.id);
            if (record) record.priority = priority;
        });
        renderMyWork();
        focusMyWorkControl(item, 'priority');

        showToast({
            title: 'Urgencia cambiada',
            message: `${escapeHtml(item.title)}: ${getPriorityLabel(priority).toLowerCase()}`,
            icon: 'iconoir-flash'
        });
    } catch (error) {
        console.error('Mi trabajo: no se pudo cambiar la urgencia', item.id, priority, error);
        showMyWorkError('No se pudo cambiar la urgencia');
    }
}

function closeMyWorkMenu(returnFocus) {
    if (!myWork.menu) return;
    const { menu, anchor, onOutside } = myWork.menu;
    document.removeEventListener('mousedown', onOutside);
    menu.remove();
    myWork.menu = null;
    if (document.body.contains(anchor)) {
        anchor.setAttribute('aria-expanded', 'false');
        if (returnFocus === true) anchor.focus();
    }
}

async function saveMyWorkDate(item, value) {
    const isTask = item.type === 'task';
    const dueDate = value || null;

    try {
        const res = await fetch(isTask ? '/api/tasks.php' : '/api/requests.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: item.id, due_date: dueDate })
        });
        const data = await res.json();
        if (!data.success) throw new Error(`${res.status} ${data.error}`);

        // Se actualizan las copias locales y la fila cambia de grupo sin recargar.
        const records = isTask
            ? myWorkTaskRecords(item.id)
            : [myWork.requests, typeof requests !== 'undefined' ? requests : []].map(list => list.find(r => Number(r.id) === item.id));
        records.filter(Boolean).forEach(record => { record.due_date = dueDate; });
        renderMyWork();
        focusMyWorkControl(item, 'date');

        showToast({
            title: dueDate ? 'Fecha guardada' : 'Fecha quitada',
            message: dueDate ? `${escapeHtml(item.title)}: ${requestDueInfo(dueDate).label.toLowerCase()}` : escapeHtml(item.title),
            icon: 'iconoir-calendar-check'
        });
        if (isTask && typeof loadTasksCount === 'function') loadTasksCount();
    } catch (error) {
        console.error('Mi trabajo: no se pudo cambiar la fecha', item.type, item.id, error);
        showMyWorkError('No se pudo cambiar la fecha');
    }
}

function showMyWorkError(message) {
    showToast({ title: 'Error', message, icon: 'iconoir-warning-circle' }, 'toast-error');
}

/**
 * Vista global "Mi trabajo": tareas y mejoras en una sola lista, agrupadas por
 * cuándo hay que hacerlas. La agrupación y el orden viven en work-helpers.js
 * (probados con Node). De main.js usa: currentView, requests, unreadRequestIds,
 * escapeHtml, getPriorityLabel y openRequestDetailModal.
 */

const MY_WORK_FILTERS = ['all', 'task', 'request'];
const MY_WORK_GROUP_LIMIT = 8; // filas visibles por grupo antes de "Ver N más"

const myWork = {
    tasks: null,           // null = todavía sin cargar
    tasksLoading: false,
    tasksError: false,
    requests: [],          // copia de las mejoras cargadas en la Vista global
    requestsLoaded: false,
    requestsError: false,
    filter: readMyWorkFilter(),
    expanded: new Set(),   // grupos con "Ver más" abierto
    items: [],             // items pintados ahora (para las acciones de cada fila)
    menu: null             // menú de fecha abierto
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.work-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => setMyWorkFilter(btn.dataset.workFilter));
    });
    document.getElementById('my-work-body')?.addEventListener('click', handleMyWorkClick);
    syncMyWorkFilterButtons();
});

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

// Al abrir la Vista global; force = true tras crear una tarea desde esta página.
function loadMyWork(forceTasks) {
    if (forceTasks || myWork.tasksError || (myWork.tasks === null && !myWork.tasksLoading)) {
        loadMyWorkTasks();
    }
    renderMyWork();
}

async function loadMyWorkTasks() {
    myWork.tasksLoading = true;
    myWork.tasksError = false;
    try {
        const res = await fetch('/api/tasks.php?completed=0&shared=0');
        const data = await res.json();
        if (!data.success) throw new Error(`${res.status} ${data.error}`);
        myWork.tasks = data.data;
    } catch (error) {
        console.error('Mi trabajo: error cargando tareas', error);
        myWork.tasksError = true;
    }
    myWork.tasksLoading = false;
    renderMyWork();
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

    if (myWork.tasksError || myWork.requestsError) {
        body.removeAttribute('aria-busy');
        body.innerHTML = myWorkMessageHtml('iconoir-warning-circle', 'No se pudo cargar tu trabajo',
            'Comprueba la conexión e inténtalo de nuevo.', '<button type="button" class="btn btn-secondary btn-sm" data-action="retry">Reintentar</button>');
        return;
    }

    if (myWork.tasks === null || !myWork.requestsLoaded) {
        body.setAttribute('aria-busy', 'true');
        body.innerHTML = myWorkSkeletonHtml();
        return;
    }
    body.removeAttribute('aria-busy');

    const viewer = { id: Number(document.body.dataset.userId), role: document.body.dataset.userRole };
    const items = buildWorkItems(myWork.tasks, myWork.requests, viewer);
    myWork.items = items;
    const counts = countWorkItems(items);
    MY_WORK_FILTERS.forEach(key => {
        const el = document.getElementById(`work-count-${key}`);
        if (el) el.textContent = counts[key];
    });

    const groups = groupWorkItems(items, new Date(), myWork.filter);
    body.innerHTML = groups.length
        ? groups.map(group => myWorkGroupHtml(group, viewer)).join('')
        : myWorkEmptyHtml(viewer);
}

function myWorkGroupHtml(group, viewer) {
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
                ${visible.map(item => myWorkRowHtml(item, group.key, viewer)).join('')}
            </ul>
            ${hiddenCount > 0 ? `<button type="button" class="my-work-more" data-action="expand" data-group="${group.key}">Ver ${hiddenCount} más</button>` : ''}
            ${expanded && group.items.length > MY_WORK_GROUP_LIMIT ? `<button type="button" class="my-work-more" data-action="collapse" data-group="${group.key}">Ver menos</button>` : ''}
        </section>`;
}

function myWorkRowHtml(item, groupKey, viewer) {
    const isTask = item.type === 'task';
    const caps = item.raw.capabilities || {};
    const canComplete = isTask && caps.complete !== false;
    // Fecha: tareas con permiso de editar; mejoras con el mismo permiso que el estado.
    const canPlan = isTask ? caps.edit !== false : item.raw.capabilities?.update_status === true;
    const due = requestDueInfo(item.dueDate);
    const showPriority = !isTask && (item.priority === 'critical' || item.priority === 'high');
    const unread = !isTask && typeof unreadRequestIds !== 'undefined' && unreadRequestIds.has(item.id);

    const meta = [`<span class="my-work-kind">${isTask ? 'Tarea' : 'Mejora'}</span>`];
    if (item.appName) meta.push(`<span>${escapeHtml(item.appName)}</span>`);
    if (!isTask && item.status === 'in_progress') meta.push('<span class="my-work-progress">En curso</span>');
    if (!isTask && viewer.role === 'superadmin') {
        meta.push(item.assignees.length
            ? `<span>${escapeHtml(item.assignees.join(', '))}</span>`
            : '<span class="my-work-unassigned">Sin asignar</span>');
    }
    if (unread) meta.push('<span class="my-work-activity"><i class="iconoir-chat-bubble" aria-hidden="true"></i>Actividad nueva</span>');

    const lead = canComplete
        ? `<button type="button" class="my-work-check" data-action="complete" title="Completar" aria-label="Completar: ${escapeHtml(item.title)}"><i class="iconoir-check" aria-hidden="true"></i></button>`
        : `<span class="my-work-icon" aria-hidden="true"><i class="${isTask ? 'iconoir-task-list' : 'iconoir-light-bulb'}"></i></span>`;

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
                    ${showPriority ? `<span class="priority-badge priority-${item.priority}">${escapeHtml(getPriorityLabel(item.priority))}</span>` : ''}
                    ${dateControl}
                </span>
            </div>
        </li>`;
}

function myWorkEmptyHtml(viewer) {
    const seeAll = viewer.role === 'superadmin';
    const messages = {
        all: ['Todo al día', seeAll ? 'No hay tareas pendientes ni mejoras activas.' : 'No tienes tareas pendientes ni mejoras asignadas.'],
        task: ['Sin tareas pendientes', 'Usa el botón + para apuntar una.'],
        request: seeAll
            ? ['No hay mejoras activas', 'Las nuevas aparecerán aquí.']
            : ['No tienes mejoras asignadas', 'Aparecerán aquí en cuanto te asignen una.']
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
            case 'expand':
                myWork.expanded.add(group);
                break;
            case 'collapse':
                myWork.expanded.delete(group);
                break;
            case 'retry':
                if (myWork.tasksError) loadMyWorkTasks();
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
    const task = (myWork.tasks || []).find(t => Number(t.id) === item.id);
    if (!task || Number(task.is_completed) === 1) return;

    task.is_completed = 1; // sale de la lista al momento; si falla, vuelve
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
        task.is_completed = 0;
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

        const task = (myWork.tasks || []).find(t => Number(t.id) === id);
        if (task) task.is_completed = 0;
        renderMyWork();
        if (typeof loadTasksCount === 'function') loadTasksCount();
    } catch (error) {
        console.error('Mi trabajo: no se pudo deshacer', id, error);
        showMyWorkError('No se pudo deshacer');
        loadMyWorkTasks();
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

    const menu = document.createElement('div');
    menu.className = 'work-menu';
    menu.setAttribute('role', 'menu');
    menu.setAttribute('aria-label', item.type === 'task' ? 'Fecha de la tarea' : 'Fecha objetivo de la mejora');
    menu.innerHTML = `
        <p class="work-menu-title">${item.type === 'task' ? 'Fecha de la tarea' : 'Fecha objetivo'}</p>
        ${options.filter(o => o.key !== 'pick' && o.key !== 'clear').map(itemHtml).join('')}
        <div class="work-menu-sep" role="separator"></div>
        ${options.filter(o => o.key === 'pick' || o.key === 'clear').map(itemHtml).join('')}
        <input type="date" class="work-menu-input" tabindex="-1" aria-label="Elegir fecha" value="${item.dueDate || ''}">`;
    document.body.appendChild(menu);

    const rect = anchor.getBoundingClientRect();
    const left = Math.max(12, Math.min(rect.right - menu.offsetWidth, window.innerWidth - menu.offsetWidth - 12));
    const below = window.innerHeight - rect.bottom >= menu.offsetHeight + 12 || rect.top < menu.offsetHeight + 12;
    menu.style.left = `${left}px`;
    menu.style.top = below ? `${rect.bottom + 6}px` : `${rect.top - menu.offsetHeight - 6}px`;

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
    menu.querySelector('[data-key]').focus();
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
        const source = (isTask ? myWork.tasks : myWork.requests) || [];
        const record = source.find(r => Number(r.id) === item.id);
        if (record) record.due_date = dueDate;
        if (!isTask && typeof requests !== 'undefined') {
            const shared = requests.find(r => Number(r.id) === item.id);
            if (shared) shared.due_date = dueDate;
        }
        renderMyWork();

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

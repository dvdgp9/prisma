/**
 * Tasks Page JavaScript
 */

let currentTaskId = null;
// Capacidades del usuario sobre la tarea abierta en el modal (vienen de la API).
let currentTaskCaps = {};
let teamMembers = [];
// Quien mira la página (para escribir "Tú" en con quién se comparte).
const CURRENT_USER_ID = Number(document.body.dataset.userId);

// Detección descartada por el usuario en la creación rápida (chips con la "x").
// Se mantiene hasta que se crea la tarea o se vacía el input.
let quickIgnore = { date: false, app: false, person: false };

// Texto del buscador (filtra en cliente sobre la última carga).
let searchQuery = '';
// Con "Completadas" activo se muestran las de los últimos 7 días salvo que se pida ver todas.
let showAllCompletedFlag = false;

document.addEventListener('DOMContentLoaded', () => {
    updateViewToggleUI();
    loadTasks();
    setupQuickAdd();
    setupSearch();
    setupTaskModal();
    loadTeamMembers();

    // Enlace directo desde la Vista global: tasks.php?task=ID abre esa tarea.
    const linkedTaskId = Number(new URLSearchParams(window.location.search).get('task'));
    if (linkedTaskId > 0) {
        openTaskModal(linkedTaskId);
        history.replaceState(null, '', '/tasks.php' + window.location.hash);
    }
});

// Setup Quick Add functionality
function setupQuickAdd() {
    const input = document.getElementById('quick-add-input');
    const expandBtn = document.getElementById('expand-btn');
    const expanded = document.getElementById('quick-add-expanded');
    
    // Enter to create task
    input.addEventListener('keydown', async (e) => {
        if (e.key === 'Enter' && input.value.trim()) {
            e.preventDefault();
            await createQuickTask();
        }
        if (e.key === 'Escape') {
            input.value = '';
            input.blur();
            expanded.classList.remove('visible');
            expandBtn.classList.remove('active');
            resetQuickPreview();
        }
    });

    // Preview en vivo de fecha/app detectadas (con debounce ligero).
    let previewTimer = null;
    input.addEventListener('input', () => {
        if (!input.value.trim()) {
            resetQuickPreview();
            return;
        }
        clearTimeout(previewTimer);
        previewTimer = setTimeout(renderQuickPreview, 120);
    });

    // Toggle expanded options
    expandBtn.addEventListener('click', () => {
        expanded.classList.toggle('visible');
        expandBtn.classList.toggle('active');
    });
}

// Analiza el input actual con el parser (si está disponible).
function parseQuickInput() {
    const input = document.getElementById('quick-add-input');
    if (typeof parseQuickTask !== 'function') return { date: null, app: null, person: null };
    return parseQuickTask(input.value, typeof QUICK_APPS !== 'undefined' ? QUICK_APPS : [], teamMembers);
}

// Pinta los chips de detección bajo el input.
function renderQuickPreview() {
    const container = document.getElementById('quick-add-chips');
    if (!container) return;

    const parsed = parseQuickInput();
    const chips = [];

    if (parsed.date && !quickIgnore.date) {
        chips.push(`
            <span class="quick-chip" data-kind="date">
                <i class="iconoir-calendar"></i>
                <span>${escapeHtml(formatDueDate(parsed.date.value))}</span>
                <button type="button" class="quick-chip-remove" onclick="dismissQuickChip('date')" title="No usar la fecha">
                    <i class="iconoir-xmark"></i>
                </button>
            </span>`);
    }
    if (parsed.app && !quickIgnore.app) {
        chips.push(`
            <span class="quick-chip" data-kind="app">
                <i class="iconoir-app-window"></i>
                <span>${escapeHtml(parsed.app.name)}</span>
                <button type="button" class="quick-chip-remove" onclick="dismissQuickChip('app')" title="No usar la app">
                    <i class="iconoir-xmark"></i>
                </button>
            </span>`);
    }

    if (parsed.person && !quickIgnore.person) {
        chips.push(`
            <span class="quick-chip" data-kind="person">
                <i class="iconoir-user"></i>
                <span>${escapeHtml(parsed.person.name)}</span>
                <button type="button" class="quick-chip-remove" onclick="dismissQuickChip('person')" title="No asignarla">
                    <i class="iconoir-xmark"></i>
                </button>
            </span>`);
    }

    if (chips.length === 0) {
        container.hidden = true;
        container.innerHTML = '';
        return;
    }
    container.hidden = false;
    container.innerHTML = chips.join('');
}

// El usuario descarta una detección: se conserva como texto literal del título.
function dismissQuickChip(kind) {
    quickIgnore[kind] = true;
    renderQuickPreview();
    document.getElementById('quick-add-input').focus();
}

// Limpia preview y estado de descartes.
function resetQuickPreview() {
    quickIgnore = { date: false, app: false, person: false };
    const container = document.getElementById('quick-add-chips');
    if (container) {
        container.hidden = true;
        container.innerHTML = '';
    }
}

// Create task from quick add
async function createQuickTask() {
    const input = document.getElementById('quick-add-input');
    const raw = input.value.trim();

    if (!raw) return;

    // Detección NLP: limpiamos del título solo los tokens no descartados
    // (si todo era metadato, el título se queda con el texto tal cual).
    const parsed = parseQuickInput();
    const title = quickTaskTitle(raw, parsed, quickIgnore);
    const parsedAppId = parsed.app && !quickIgnore.app ? parsed.app.id : null;
    const parsedDueDate = parsed.date && !quickIgnore.date ? parsed.date.value : null;

    const expanded = document.getElementById('quick-add-expanded');
    const isExpanded = expanded.classList.contains('visible');

    const taskData = {
        title: title
    };

    if (parsedAppId) taskData.app_id = parsedAppId;
    if (parsedDueDate) taskData.due_date = parsedDueDate;
    // "para Ana" / "+ana": se crea ya asignada (le llega el aviso, como con "Compartir con").
    if (parsed.person && !quickIgnore.person) taskData.shared_user_ids = [parsed.person.id];

    // Las opciones manuales del panel expandido tienen prioridad si se rellenan.
    if (isExpanded) {
        const appId = document.getElementById('quick-add-app').value;
        const description = document.getElementById('quick-add-description').value.trim();
        const dueDate = document.getElementById('quick-add-due-date').value;

        if (appId) taskData.app_id = parseInt(appId);
        if (description) taskData.description = description;
        if (dueDate) taskData.due_date = dueDate;
    }

    try {
        const response = await fetch('/api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(taskData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Reset form
            input.value = '';
            document.getElementById('quick-add-description').value = '';
            document.getElementById('quick-add-due-date').value = '';
            resetQuickPreview();

            // Reload tasks
            await loadTasks();
            
            // Focus back to input for continuous adding
            input.focus();
        } else {
            showErrorToast(data.error || 'No se pudo completar la acción');
        }
    } catch (error) {
        console.error('Error creating task:', error);
        showErrorToast('No se pudo crear la tarea');
    }
}

// Load tasks
async function loadTasks() {
    const showCompleted = document.getElementById('show-completed').checked;
    const showShared = document.getElementById('show-shared').checked;
    const appId = document.getElementById('app-filter').value;
    
    if (!showCompleted) showAllCompletedFlag = false;

    let url = `/api/tasks.php?completed=${showCompleted ? '1' : '0'}&shared=${showShared ? '1' : '0'}`;
    if (appId) url += `&app_id=${appId}`;
    if (showCompleted && !showAllCompletedFlag) url += '&completed_days=7';

    try {
        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            renderTasks(data.data);
            maybeScrollToBucket();
            // Mantiene al día el contador del sidebar y el "hechas hoy" tras crear/completar/borrar.
            if (typeof loadTasksCount === 'function') loadTasksCount();
            loadTasksSummary();
        } else {
            console.error('Error loading tasks', url, response.status, data.error);
            showErrorToast(data.error || 'No se pudieron cargar las tareas');
        }
    } catch (error) {
        console.error('Error loading tasks:', error);
        showErrorToast('No se pudieron cargar las tareas');
    }
}

// "N hechas hoy" junto al título de la página.
async function loadTasksSummary() {
    const pill = document.getElementById('done-today');
    if (!pill) return;

    try {
        const response = await fetch('/api/tasks.php?summary=1');
        const data = await response.json();
        if (!data.success) {
            console.error('Error loading tasks summary', response.status, data.error);
            return;
        }
        const n = data.data.completed_today;
        pill.hidden = n === 0;
        document.getElementById('done-today-text').textContent = `${n} ${n === 1 ? 'hecha' : 'hechas'} hoy`;
    } catch (error) {
        console.error('Error loading tasks summary:', error);
    }
}

// Carga también las completadas de hace más de 7 días.
function showAllCompleted() {
    showAllCompletedFlag = true;
    loadTasks();
}

// Buscador: filtra en cliente; Escape lo vacía.
function setupSearch() {
    const input = document.getElementById('tasks-search');
    if (!input) return;

    input.addEventListener('input', () => {
        searchQuery = input.value;
        renderTasks(lastTasks);
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            input.value = '';
            searchQuery = '';
            renderTasks(lastTasks);
            input.blur();
        }
    });
}

// Si se llega con #overdue / #today / #week (desde el widget del home),
// forzar vista Agenda y desplazar a esa sección.
function maybeScrollToBucket() {
    const bucket = (location.hash || '').replace('#', '');
    if (['overdue', 'today', 'week'].indexOf(bucket) === -1) return;
    if (getTasksView() !== 'agenda') {
        localStorage.setItem('prisma_tasks_view', 'agenda');
        updateViewToggleUI();
        renderTasks(lastTasks);
    }
    const el = document.querySelector('.agenda-' + bucket);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Última tanda de tareas cargada (para re-render al cambiar de vista sin refetch).
let lastTasks = [];

// Definición de buckets de la vista Agenda, en orden de presentación.
const AGENDA_BUCKETS = [
    { key: 'overdue', label: 'Vencidas', icon: 'iconoir-warning-triangle' },
    { key: 'today', label: 'Hoy', icon: 'iconoir-sun-light' },
    { key: 'tomorrow', label: 'Mañana', icon: 'iconoir-calendar' },
    { key: 'week', label: 'Esta semana', icon: 'iconoir-calendar' },
    { key: 'later', label: 'Más adelante', icon: 'iconoir-calendar' },
    { key: 'nodate', label: 'Sin fecha', icon: 'iconoir-minus' },
    { key: 'done', label: 'Completadas', icon: 'iconoir-check-circle' }
];

// Día local (sin hora) para cálculos de fecha.
function getLocalToday() {
    const t = new Date();
    return new Date(t.getFullYear(), t.getMonth(), t.getDate());
}

// Diferencia en días entre due_date (YYYY-MM-DD) y hoy, en horario local.
function diffDaysFromToday(dueDate, localToday) {
    const [y, m, d] = dueDate.split('-').map(Number);
    const due = new Date(y, m - 1, d);
    return Math.round((due - localToday) / (1000 * 60 * 60 * 24));
}

// Clasifica una tarea en su bucket de agenda.
function getTaskBucket(task, localToday) {
    if (task.is_completed) return 'done';
    if (!task.due_date) return 'nodate';
    const diff = diffDaysFromToday(task.due_date, localToday);
    if (diff < 0) return 'overdue';
    if (diff === 0) return 'today';
    if (diff === 1) return 'tomorrow';
    if (diff <= 7) return 'week';
    return 'later';
}

// Vista actual ('agenda' por defecto), persistida en localStorage.
function getTasksView() {
    return localStorage.getItem('prisma_tasks_view') === 'list' ? 'list' : 'agenda';
}

// Construye el HTML de una tarjeta de tarea.
function buildTaskCard(task, localToday) {
    // Sin capacidades (API antigua) se mantiene el comportamiento previo: mostrar todo.
    const caps = task.capabilities || {};
    // Fecha, app y título se cambian desde la propia tarjeta si se puede editar y no está hecha.
    const canEdit = caps.edit !== false && !task.is_completed;
    let dueDateHtml = '';
    let dueDateClass = '';

    if (task.due_date && !task.is_completed) {
        const diffDays = diffDaysFromToday(task.due_date, localToday);
        let icon = 'iconoir-calendar';
        let title = 'Fecha límite';

        if (diffDays < 0) {
            dueDateClass = 'overdue';
            icon = 'iconoir-warning-triangle';
            title = 'Vencida';
        } else if (diffDays === 0) {
            dueDateClass = 'today';
            icon = 'iconoir-clock';
            title = 'Hoy';
        } else if (diffDays <= 3) {
            dueDateClass = 'soon';
            title = 'Próximamente';
        }

        dueDateHtml = canEdit
            ? `<button type="button" class="task-due-date ${dueDateClass}" title="${title} · cambiar fecha" onclick="openPostponeMenu(${task.id}, this, event)">
                <i class="${icon}"></i>${formatDueDate(task.due_date)}
            </button>`
            : `<span class="task-due-date ${dueDateClass}" title="${title}">
                <i class="${icon}"></i>${formatDueDate(task.due_date)}
            </span>`;
    }

    const meta = [];
    // Te la han asignado y es la primera vez que la ves.
    if (taskIsNewForMe(task, CURRENT_USER_ID)) {
        meta.push('<span class="task-new-pill" title="Te la acaban de asignar">Nueva</span>');
    }
    if (dueDateHtml) meta.push(dueDateHtml);
    if (task.app_name) {
        meta.push(canEdit
            ? `<button type="button" class="task-meta-item task-meta-btn" title="Cambiar aplicación" onclick="openAppMenu(${task.id}, this, event)">
                <i class="iconoir-app-window"></i>${escapeHtml(task.app_name)}
            </button>`
            : `<span class="task-meta-item"><i class="iconoir-app-window"></i>${escapeHtml(task.app_name)}</span>`);
    }
    // En tareas de otras personas, quién la creó.
    const creator = task.creator_name || task.creator_username;
    if (caps.share === false && creator) {
        meta.push(`<span class="task-meta-item task-creator" title="Creada por ${escapeHtml(creator)}"><i class="iconoir-user"></i>${escapeHtml(creator)}</span>`);
    }
    // Para quién es (siempre). Si es tuya, al pulsar la etiqueta se cambia con quién se comparte.
    const canShare = caps.share !== false && !task.is_completed;
    const forWhom = taskForWhom(task, teamMembers, CURRENT_USER_ID);
    // Si la has asignado tú: ✓ si aún no la han visto, ✓✓ cuando sí (cuándo, en el title).
    const receipt = taskSeenReceipt(task, teamMembers, CURRENT_USER_ID, new Date());
    const receiptHtml = receipt
        ? `<i class="task-seen task-seen--${receipt.state} ${receipt.icon}" aria-hidden="true"></i><span class="visually-hidden">· ${escapeHtml(receipt.label)}</span>`
        : '';
    const forWhomTitle = receipt ? `${forWhom.title} · ${receipt.title}` : forWhom.title;
    const forWhomInner = `<i class="${forWhom.icon}"></i><span class="task-badge-names">${escapeHtml(forWhom.label)}</span><span class="task-badge-count">${escapeHtml(forWhom.count)}</span>${receiptHtml}`;
    const forWhomClass = `task-badge shared task-badge--${forWhom.kind}`;
    meta.push(canShare
        ? `<button type="button" class="${forWhomClass} task-badge-btn" title="${escapeHtml(forWhomTitle)} · cambiar (s)" onclick="openShareMenu(${task.id}, this, event)">${forWhomInner}</button>`
        : `<span class="${forWhomClass}" title="${escapeHtml(forWhomTitle)}">${forWhomInner}</span>`);
    if (task.attachment_count > 0) {
        meta.push(`<span class="task-meta-item"><i class="iconoir-attachment"></i>${task.attachment_count}</span>`);
    }
    if (task.description) {
        meta.push('<span class="task-meta-item" title="Tiene descripción"><i class="iconoir-notes"></i></span>');
    }

    return `
        <div class="task-item ${task.is_completed ? 'completed' : ''} ${dueDateClass}" data-id="${task.id}">
            <div class="task-checkbox ${task.is_completed ? 'checked' : ''}" role="checkbox" aria-checked="${task.is_completed ? 'true' : 'false'}"
                 title="${task.is_completed ? 'Marcar como pendiente' : 'Completar'}"
                 onclick="toggleTask(${task.id}, ${task.is_completed ? 'false' : 'true'}, event)">
            </div>
            <div class="task-content" onclick="onTaskContentClick(${task.id}, event)">
                <div class="task-title"${canEdit ? ` ondblclick="startInlineTitleEdit(${task.id}, event)"` : ''}>${escapeHtml(task.title)}</div>
                ${meta.length ? `<div class="task-meta">${meta.join('')}</div>` : ''}
            </div>
            <div class="task-actions">
                ${canEdit ? `
                    <button type="button" class="task-action-btn" onclick="openPostponeMenu(${task.id}, this, event)" title="${task.due_date ? 'Posponer' : 'Poner fecha'} (p)">
                        <i class="iconoir-alarm"></i>
                    </button>
                ` : ''}
                <button type="button" class="task-action-btn" onclick="openTaskModal(${task.id})" title="Editar (e)">
                    <i class="iconoir-edit"></i>
                </button>
                ${caps.delete !== false ? `
                    <button type="button" class="task-action-btn delete" onclick="deleteTaskDirect(${task.id}, event)" title="Eliminar">
                        <i class="iconoir-trash"></i>
                    </button>
                ` : ''}
            </div>
        </div>`;
}

// Render principal: guarda los datos, aplica la búsqueda y delega según la vista activa.
function renderTasks(tasks) {
    lastTasks = tasks || [];
    lastTasks.forEach(normalizeTaskShares);
    const container = document.getElementById('tasks-list');
    const emptyState = document.getElementById('empty-state');

    if (lastTasks.length === 0) {
        container.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }

    emptyState.style.display = 'none';
    const localToday = getLocalToday();
    const visible = typeof matchesTaskSearch === 'function'
        ? lastTasks.filter(t => matchesTaskSearch(t, searchQuery))
        : lastTasks;

    if (visible.length === 0) {
        container.innerHTML = `<div class="tasks-no-results"><i class="iconoir-search"></i>Ninguna tarea coincide con «${escapeHtml(searchQuery.trim())}»</div>`;
    } else if (getTasksView() === 'list') {
        container.innerHTML = visible.map(t => buildTaskCard(t, localToday)).join('') + completedFooterHtml();
    } else {
        renderAgenda(container, visible, localToday);
        container.insertAdjacentHTML('beforeend', completedFooterHtml());
    }

    // Las que te han asignado y ya tienes delante cuentan como vistas (doble check para quien las asignó).
    if (typeof markTasksSeen === 'function') {
        markTasksSeen(visible.filter(t => taskIsNewForMe(t, CURRENT_USER_ID)).map(t => t.id));
    }

    if (typeof onTasksRendered === 'function') onTasksRendered();
}

// Pie con "Completadas": por defecto solo las de los últimos 7 días.
function completedFooterHtml() {
    if (!document.getElementById('show-completed').checked || showAllCompletedFlag) return '';
    return `<div class="tasks-completed-footer">
        Completadas de los últimos 7 días ·
        <button type="button" class="tasks-link-btn" onclick="showAllCompleted()">Ver todas</button>
    </div>`;
}

// Render agrupado por buckets temporales.
function renderAgenda(container, tasks, localToday) {
    const groups = {};
    tasks.forEach(task => {
        const bucket = getTaskBucket(task, localToday);
        (groups[bucket] = groups[bucket] || []).push(task);
    });

    const html = AGENDA_BUCKETS
        .filter(b => groups[b.key] && groups[b.key].length)
        .map(b => {
            const items = groups[b.key].map(t => buildTaskCard(t, localToday)).join('');
            const canMoveOverdue = b.key === 'overdue'
                && groups.overdue.some(t => (t.capabilities || {}).edit !== false);
            return `
                <section class="agenda-section agenda-${b.key}">
                    <header class="agenda-section-header">
                        <i class="${b.icon}"></i>
                        <span class="agenda-section-title">${b.label}</span>
                        <span class="agenda-section-count">${groups[b.key].length}</span>
                        ${canMoveOverdue ? `
                            <button type="button" class="agenda-section-action" onclick="moveOverdueToToday()">
                                <i class="iconoir-sun-light"></i>Pasar todo a hoy
                            </button>
                        ` : ''}
                    </header>
                    <div class="agenda-section-items">${items}</div>
                </section>`;
        })
        .join('');

    container.innerHTML = html;
}

// Cambia la vista, persiste la preferencia y re-renderiza sin refetch.
function setTasksView(view) {
    localStorage.setItem('prisma_tasks_view', view === 'list' ? 'list' : 'agenda');
    updateViewToggleUI();
    renderTasks(lastTasks);
}

// Marca el botón activo del toggle según la preferencia guardada.
function updateViewToggleUI() {
    const current = getTasksView();
    document.querySelectorAll('#view-toggle .view-toggle-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === current);
    });
}

// Toggle task completion
async function toggleTask(taskId, complete, event) {
    if (event) event.stopPropagation();

    if (complete) {
        await completeTaskWithUndo(taskId);
        return;
    }

    try {
        const response = await fetch('/api/tasks.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId, is_completed: complete })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadTasks();
        } else {
            console.error('Error toggling task', taskId, response.status, data.error);
            showErrorToast(data.error || 'No se pudo actualizar la tarea');
        }
    } catch (error) {
        console.error('Error toggling task:', error);
        showErrorToast('No se pudo actualizar la tarea');
    }
}

// Completa con una animación corta y ofrece "Deshacer" durante unos segundos.
async function completeTaskWithUndo(taskId) {
    const card = document.querySelector(`.task-item[data-id="${taskId}"]`);
    if (card && card.classList.contains('is-completing')) return;
    const task = lastTasks.find(t => t.id == taskId);
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (card) card.classList.add('is-completing');
    const [ok] = await Promise.all([
        putTask({ id: taskId, is_completed: true }, 'No se pudo completar la tarea'),
        new Promise(resolve => setTimeout(resolve, card && !reduceMotion ? 320 : 0))
    ]);

    if (!ok) {
        if (card) card.classList.remove('is-completing');
        return;
    }

    await loadTasks();
    showToast({
        title: 'Tarea completada',
        message: task ? escapeHtml(task.title) : '',
        icon: 'iconoir-check',
        duration: 6000,
        action: {
            label: 'Deshacer',
            onClick: async () => {
                if (await putTask({ id: taskId, is_completed: false }, 'No se pudo deshacer')) {
                    await loadTasks();
                }
            }
        }
    }, 'toast-completed');
}

// PUT a la API de tareas. Devuelve true si fue bien; si no, avisa y deja log de depuración.
async function putTask(payload, errorMessage) {
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (data.success) return true;

        console.error('Error updating task', payload, response.status, data.error);
        showErrorToast(data.error || errorMessage);
    } catch (error) {
        console.error('Error updating task:', payload, error);
        showErrorToast(errorMessage);
    }
    return false;
}

// Delete task directly from list
async function deleteTaskDirect(taskId, event) {
    event.stopPropagation();
    
    if (!confirm('¿Eliminar esta tarea?')) return;
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadTasks();
            showToast({ title: 'Eliminada', message: 'Tarea eliminada', icon: 'iconoir-check' }, 'toast-completed');
        } else {
            console.error('Error deleting task', taskId, response.status, data.error);
            showErrorToast(data.error || 'No se pudo eliminar la tarea');
        }
    } catch (error) {
        console.error('Error deleting task:', error);
        showErrorToast('No se pudo eliminar la tarea');
    }
}

// ========== Modal de tarea ==========
// Barra de propiedades (completar, fecha, app, compartir) con guardado al momento;
// título y descripción con "Guardar cambios". Esc / clic fuera cierran avisando
// si hay cambios sin guardar.

// Tarea abierta tal como la devolvió la API (por si deja de estar en la lista, p. ej. al completarla).
let currentModalTask = null;
// Título y descripción al abrir, para detectar cambios sin guardar.
let taskModalSnapshot = null;
// Elemento con el foco antes de abrir, para devolvérselo al cerrar.
let taskModalReturnFocus = null;
// Evita que un repintado lento de la barra pise a uno más reciente.
let taskMetaBarRenderToken = 0;

function isTaskModalOpen() {
    return document.getElementById('task-modal').classList.contains('active');
}

// La copia de la lista si existe (es la que actualizan los menús); si no, la del modal.
// La del modal se mantiene al día con la de la lista para cuando esta desaparezca
// (p. ej. al completarla con "Completadas" desactivado).
function getModalTask() {
    if (currentTaskId === null) return null;
    const listed = lastTasks.find(t => t.id == currentTaskId);
    if (listed && currentModalTask && listed !== currentModalTask) Object.assign(currentModalTask, listed);
    return listed || currentModalTask;
}

function setupTaskModal() {
    const modal = document.getElementById('task-modal');
    const content = document.getElementById('task-modal-content');
    const fileUpload = document.getElementById('task-file-upload');
    const fileInput = document.getElementById('task-file-input');

    fileUpload.addEventListener('click', () => fileInput.click());
    fileUpload.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            fileInput.click();
        }
    });
    fileInput.addEventListener('change', () => {
        handleTaskFiles(fileInput.files);
        fileInput.value = '';
    });

    // Soltar archivos en cualquier parte del modal.
    const hasFiles = (e) => !!e.dataTransfer && Array.from(e.dataTransfer.types || []).includes('Files');
    let dragDepth = 0;
    content.addEventListener('dragenter', (e) => {
        if (!hasFiles(e) || currentTaskCaps.attach === false) return;
        e.preventDefault();
        dragDepth++;
        content.classList.add('is-dragging');
    });
    content.addEventListener('dragover', (e) => {
        if (hasFiles(e) && currentTaskCaps.attach !== false) e.preventDefault();
    });
    content.addEventListener('dragleave', () => {
        dragDepth = Math.max(0, dragDepth - 1);
        if (dragDepth === 0) content.classList.remove('is-dragging');
    });
    content.addEventListener('drop', (e) => {
        if (!hasFiles(e) || currentTaskCaps.attach === false) return;
        e.preventDefault();
        dragDepth = 0;
        content.classList.remove('is-dragging');
        handleTaskFiles(e.dataTransfer.files);
    });

    content.addEventListener('paste', handleTaskModalPaste);

    // Clic en el fondo: solo si el clic empieza y termina fuera del contenido
    // (así no se cierra al seleccionar texto y soltar fuera).
    let pressedOnBackdrop = false;
    modal.addEventListener('mousedown', (e) => { pressedOnBackdrop = e.target === modal; });
    modal.addEventListener('click', (e) => {
        if (pressedOnBackdrop && e.target === modal) requestCloseTaskModal();
        pressedOnBackdrop = false;
    });

    document.addEventListener('keydown', handleTaskModalKeydown);

    const title = document.getElementById('task-title');
    const description = document.getElementById('task-description');
    title.addEventListener('input', updateTaskDirtyHint);
    description.addEventListener('input', () => {
        updateTaskDirtyHint();
        autoGrowTaskDescription();
    });

    // Enlace a la mejora de origen: avisa antes de salir con cambios sin guardar.
    document.getElementById('task-source-link').addEventListener('click', (e) => {
        if (isTaskModalDirty() && !confirm('Tienes cambios sin guardar en la tarea. ¿Salir sin guardar?')) {
            e.preventDefault();
        }
    });
}

function handleTaskModalKeydown(e) {
    if (!isTaskModalOpen()) return;
    // El visor de archivos y los menús gestionan su propio Escape.
    if (document.querySelector('.fv-overlay.active') || (typeof openMenuEl !== 'undefined' && openMenuEl)) return;

    if (e.key === 'Escape') {
        e.preventDefault();
        requestCloseTaskModal();
    } else if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        document.getElementById('task-form').requestSubmit();
    } else if (e.key === 'Tab') {
        trapTaskModalFocus(e);
    }
}

// Mantiene el foco dentro del modal con Tab / Shift+Tab.
function trapTaskModalFocus(e) {
    const focusables = Array.from(document.querySelectorAll(
        '#task-modal-content button:not([disabled]), #task-modal-content a[href], #task-modal-content input:not([type="hidden"]):not(.task-file-input), #task-modal-content textarea, #task-modal-content [tabindex="0"]'
    )).filter(el => el.offsetParent !== null);
    if (!focusables.length) return;

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (!focusables.includes(document.activeElement)) {
        e.preventDefault();
        first.focus();
    } else if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

function isTaskModalDirty() {
    if (!taskModalSnapshot) return false;
    return document.getElementById('task-title').value.trim() !== taskModalSnapshot.title
        || document.getElementById('task-description').value.trim() !== taskModalSnapshot.description;
}

function updateTaskDirtyHint() {
    document.getElementById('task-unsaved-hint').hidden = !isTaskModalDirty();
}

// La descripción crece con el texto (hasta la mitad de la pantalla).
function autoGrowTaskDescription() {
    const textarea = document.getElementById('task-description');
    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight + 2, window.innerHeight * 0.5)}px`;
}

// Pegar una captura (Ctrl/Cmd+V) la adjunta. Pegar texto en los campos funciona como siempre.
function handleTaskModalPaste(e) {
    const files = Array.from((e.clipboardData && e.clipboardData.files) || []);
    if (!files.length || currentTaskCaps.attach === false) return;
    e.preventDefault();

    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const stamp = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}`;
    // Las capturas llegan como "image.png": se les da un nombre reconocible.
    const named = files.map((file, i) => {
        if (file.name && file.name !== 'image.png') return file;
        const ext = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
        return new File([file], `captura-${stamp}${files.length > 1 ? `-${i + 1}` : ''}.${ext}`, { type: file.type });
    });
    handleTaskFiles(named);
}

// Sube los archivos de uno en uno mostrando el progreso.
async function handleTaskFiles(fileList) {
    const files = Array.from(fileList || []);
    if (!currentTaskId || files.length === 0 || currentTaskCaps.attach === false) return;

    const status = document.getElementById('task-upload-status');
    const taskId = currentTaskId;
    let uploaded = 0;

    try {
        for (const [index, file] of files.entries()) {
            status.hidden = false;
            status.textContent = files.length > 1
                ? `Subiendo ${index + 1} de ${files.length}: ${file.name}…`
                : `Subiendo ${file.name}…`;

            const formData = new FormData();
            formData.append('task_id', taskId);
            formData.append('file', file);

            try {
                const response = await fetch('/api/task-attachments.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    uploaded++;
                } else {
                    console.error('Error uploading file', file.name, response.status, data.error);
                    showErrorToast(`${file.name}: ${data.error || 'No se pudo subir'}`);
                }
            } catch (error) {
                console.error('Error uploading file:', file.name, error);
                showErrorToast('Error de conexión al subir el archivo');
            }
        }
    } finally {
        status.hidden = true;
    }

    if (uploaded && currentTaskId === taskId) {
        await loadTaskAttachments(taskId);
        loadTasks(); // el contador de adjuntos de la tarjeta
    }
}

// Open task modal
async function openTaskModal(taskId) {
    if (isTaskModalOpen() && currentTaskId == taskId) return;
    const returnFocus = document.activeElement;
    currentTaskId = taskId;

    try {
        // Pide la tarea por id: así se abren también completadas y del equipo.
        const response = await fetch(`/api/tasks.php?id=${taskId}`);
        const data = await response.json();

        if (!data.success) {
            console.error('Error loading task', taskId, response.status, data.error);
            currentTaskId = null;
            showErrorToast(data.error || 'No se pudo abrir la tarea');
            return;
        }

        const task = normalizeTaskShares(data.data);
        // Te la han asignado: al abrirla queda vista y su aviso de la campana, leído.
        if (Number(task.user_id) !== CURRENT_USER_ID && typeof markTaskOpened === 'function') markTaskOpened(task.id);
        currentModalTask = task;
        currentTaskCaps = task.capabilities || {};
        taskModalReturnFocus = returnFocus;

        const title = document.getElementById('task-title');
        const description = document.getElementById('task-description');
        document.getElementById('task-id').value = task.id;
        document.getElementById('task-id-display').textContent = `#${task.id}`;
        title.value = task.title;
        description.value = task.description || '';
        title.readOnly = description.readOnly = currentTaskCaps.edit === false;
        taskModalSnapshot = { title: task.title.trim(), description: (task.description || '').trim() };
        updateTaskDirtyHint();

        // Borrar la tarea es solo del dueño; adjuntar, de quien pueda trabajar en ella.
        document.getElementById('task-delete-btn').hidden = currentTaskCaps.delete === false;
        document.getElementById('task-file-upload').hidden = currentTaskCaps.attach === false;
        renderTaskSourceLink(task);

        await renderTaskMetaBar();
        await loadTaskAttachments(task.id);

        document.getElementById('task-modal').classList.add('active');
        autoGrowTaskDescription();
        title.focus();
        title.setSelectionRange(title.value.length, title.value.length);
    } catch (error) {
        console.error('Error loading task:', error);
        currentTaskId = null;
        showErrorToast('No se pudo abrir la tarea');
    }
}

// "Mejora #142" en la cabecera si la tarea se creó desde una mejora.
function renderTaskSourceLink(task) {
    const link = document.getElementById('task-source-link');
    const requestId = typeof taskSourceRequestId === 'function' ? taskSourceRequestId(task.description) : null;
    link.hidden = !requestId;
    if (!requestId) return;
    link.href = `/index.php?open_request=${requestId}`;
    link.title = 'Abrir la mejora de origen';
    document.getElementById('task-source-link-text').textContent = `Mejora #${requestId}`;
}

// Llamado tras cada repintado de la lista: la barra refleja lo que cambien los menús.
function refreshTaskModalBar() {
    if (isTaskModalOpen()) renderTaskMetaBar();
}

// Ids de las personas con las que está compartida (la lista solo trae cuántas).
// La API trae los ids de con quién se comparte como texto ("6,8"); se guardan como lista.
function normalizeTaskShares(task) {
    if (task && !Array.isArray(task.shared_user_ids) && task.share_user_ids !== undefined) {
        task.shared_user_ids = parseShareIds(task.share_user_ids);
    }
    return task;
}

async function getTaskSharedUserIds(task) {
    if (task.is_shared == 1 || !(task.share_count > 0)) return [];
    normalizeTaskShares(task);
    if (Array.isArray(task.shared_user_ids) && task.shared_user_ids.length === Number(task.share_count)) {
        return task.shared_user_ids;
    }
    try {
        const response = await fetch(`/api/task-shares.php?task_id=${task.id}`);
        const data = await response.json();
        if (!data.success) throw new Error(`${response.status} ${data.error}`);
        task.shared_user_ids = data.data.map(u => Number(u.id));
    } catch (error) {
        console.error('Error loading task shares', task.id, error);
        task.shared_user_ids = [];
    }
    return task.shared_user_ids;
}

// Fecha corta "10 oct" a partir de "YYYY-MM-DD HH:MM:SS".
function formatShortDate(dateTime) {
    const [y, m, d] = dateTime.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
}

async function renderTaskMetaBar() {
    const bar = document.getElementById('task-meta-bar');
    const task = getModalTask();
    if (!bar || !task) return;
    const token = ++taskMetaBarRenderToken;

    const caps = task.capabilities || currentTaskCaps;
    const done = task.is_completed == 1;
    const canEdit = caps.edit !== false && !done;
    const canShare = caps.share !== false && !done;
    const creator = task.creator_name || task.creator_username || '';

    // Completar / reabrir
    const completeControl = caps.complete !== false
        ? `<button type="button" class="task-complete-toggle ${done ? 'is-done' : ''}" onclick="toggleTaskFromModal()" aria-pressed="${done ? 'true' : 'false'}">
               <span class="task-checkbox ${done ? 'checked' : ''}" aria-hidden="true"></span>${done ? 'Completada' : 'Completar'}
           </button>`
        : '';

    // Fecha (mismo chip y menú "Posponer" que la tarjeta)
    let dateControl = '';
    if (task.due_date) {
        const diff = diffDaysFromToday(task.due_date, getLocalToday());
        const tone = done ? '' : diff < 0 ? 'overdue' : diff === 0 ? 'today' : diff <= 3 ? 'soon' : '';
        const label = formatDueDate(task.due_date);
        dateControl = canEdit
            ? `<button type="button" class="task-due-date ${tone}" onclick="openPostponeMenu(${task.id}, this, event)" title="Posponer"><i class="iconoir-calendar"></i>${label}</button>`
            : `<span class="task-due-date ${tone}"><i class="iconoir-calendar"></i>${label}</span>`;
    } else if (canEdit) {
        dateControl = `<button type="button" class="task-meta-chip is-empty" onclick="openPostponeMenu(${task.id}, this, event)"><i class="iconoir-calendar-plus"></i>Poner fecha</button>`;
    }

    // Aplicación
    let appControl = '';
    if (canEdit) {
        appControl = `<button type="button" class="task-meta-chip ${task.app_name ? '' : 'is-empty'}" onclick="openAppMenu(${task.id}, this, event)" title="Cambiar aplicación">
            <i class="iconoir-app-window"></i><span>${task.app_name ? escapeHtml(task.app_name) : 'Sin aplicación'}</span>
        </button>`;
    } else if (task.app_name) {
        appControl = `<span class="task-meta-chip is-static"><i class="iconoir-app-window"></i><span>${escapeHtml(task.app_name)}</span></span>`;
    }

    // Compartir: el dueño lo cambia; el resto lo ve
    let shareControl;
    if (caps.share !== false) {
        const ids = await getTaskSharedUserIds(task);
        if (token !== taskMetaBarRenderToken) return;
        const summary = taskShareSummary(ids, teamMembers, CURRENT_USER_ID);
        const shareLabel = task.is_shared == 1 ? 'Todo el equipo'
            : summary.label || (task.share_count > 0 ? `${task.share_count} ${task.share_count == 1 ? 'persona' : 'personas'}` : 'Solo para mí');
        const icon = task.is_shared == 1 ? 'iconoir-group' : ids.length || task.share_count > 0 ? 'iconoir-user' : 'iconoir-lock';
        shareControl = canShare
            ? `<button type="button" class="task-meta-chip" onclick="openShareMenu(${task.id}, this, event)" title="Cambiar con quién se comparte"><i class="${icon}"></i><span>${escapeHtml(shareLabel)}</span></button>`
            : `<span class="task-meta-chip is-static"><i class="${icon}"></i><span>${escapeHtml(shareLabel)}</span></span>`;
    } else {
        const shareLabel = task.is_shared == 1 ? 'Todo el equipo' : 'Compartida contigo';
        shareControl = `<span class="task-meta-chip is-static" title="Creada por ${escapeHtml(creator || 'otra persona')}. Solo esa persona puede cambiar con quién se comparte.">
            <i class="iconoir-group"></i><span>${shareLabel}</span>
        </span>`;
    }

    // Metadatos de solo lectura
    const today = getLocalToday();
    const facts = [];
    if (creator) {
        facts.push(`<span class="request-meta-bar__fact"><i class="iconoir-user"></i>${caps.share !== false ? 'Creada por ti' : `Creada por ${escapeHtml(creator)}`}</span>`);
    }
    if (task.created_at) {
        facts.push(`<span class="request-meta-bar__fact" title="Creada"><i class="iconoir-calendar"></i>${formatShortDate(task.created_at)} · ${taskRelativeDay(task.created_at, today)}</span>`);
    }
    if (done && task.completed_at) {
        facts.push(`<span class="request-meta-bar__fact task-fact-done"><i class="iconoir-check-circle"></i>Completada ${taskRelativeDay(task.completed_at, today)}</span>`);
    }
    // Si la has asignado tú: quién la ha visto ya y cuándo.
    const receipt = taskSeenReceipt(task, teamMembers, CURRENT_USER_ID, new Date());
    if (receipt) {
        facts.push(`<span class="request-meta-bar__fact task-seen-facts task-seen-facts--${receipt.state}"><i class="${receipt.icon}"></i>${escapeHtml(receipt.title)}</span>`);
    }

    bar.innerHTML = `
        <div class="request-meta-bar__controls">
            ${completeControl}
            ${completeControl ? '<span class="request-meta-bar__divider" aria-hidden="true"></span>' : ''}
            ${dateControl}${appControl}${shareControl}
        </div>
        <div class="request-meta-bar__facts">${facts.join('')}</div>`;
}

// "YYYY-MM-DD HH:MM:SS" local de ahora (para pintar "Completada hoy" sin recargar).
function nowAsSqlDateTime() {
    const n = new Date();
    const pad = (v) => String(v).padStart(2, '0');
    return `${n.getFullYear()}-${pad(n.getMonth() + 1)}-${pad(n.getDate())} ${pad(n.getHours())}:${pad(n.getMinutes())}:${pad(n.getSeconds())}`;
}

// Completar / reabrir desde la barra del modal (sin cerrarlo).
async function toggleTaskFromModal() {
    const task = getModalTask();
    if (!task) return;
    const complete = task.is_completed != 1;

    if (!(await putTask({ id: task.id, is_completed: complete }, complete ? 'No se pudo completar la tarea' : 'No se pudo reabrir la tarea'))) {
        return;
    }

    [task, currentModalTask].forEach(t => {
        if (!t) return;
        t.is_completed = complete ? 1 : 0;
        t.completed_at = complete ? nowAsSqlDateTime() : null;
    });
    await loadTasks();
    renderTaskMetaBar();
}

// Tamaño legible: "482 KB", "1,2 MB".
function formatFileSize(bytes) {
    const size = Number(bytes) || 0;
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
}

// Load task attachments
async function loadTaskAttachments(taskId) {
    const container = document.getElementById('task-attachments');
    const count = document.getElementById('task-attachment-count');

    try {
        const response = await fetch(`/api/task-attachments.php?task_id=${taskId}`);
        const data = await response.json();
        if (!data.success) {
            console.error('Error loading attachments', taskId, response.status, data.error);
            return;
        }

        count.textContent = data.data.length || '';
        count.hidden = data.data.length === 0;
        container.innerHTML = data.data.map(att => {
            const isImage = (att.mime_type || '').startsWith('image/');
            return `
                <div class="task-attachment-item">
                    <a href="#" class="fv-trigger" data-type="task" data-id="${att.id}" data-mime="${att.mime_type || ''}">
                        ${isImage
                            ? `<img class="task-attachment-thumb" src="/api/file.php?type=task&id=${att.id}" alt="" loading="lazy">`
                            : `<span class="task-attachment-icon"><i class="${getFileIcon(att.mime_type)}"></i></span>`}
                        <span class="fv-name">${escapeHtml(att.original_filename)}</span>
                        ${att.file_size ? `<span class="task-attachment-size">${formatFileSize(att.file_size)}</span>` : ''}
                    </a>
                    ${currentTaskCaps.delete !== false ? `
                        <button type="button" class="task-action-btn delete" onclick="deleteTaskAttachment(${att.id})" title="Eliminar archivo" aria-label="Eliminar ${escapeHtml(att.original_filename)}">
                            <i class="iconoir-trash"></i>
                        </button>
                    ` : ''}
                </div>`;
        }).join('');
    } catch (error) {
        console.error('Error loading attachments:', error);
    }
}

// Delete task attachment
async function deleteTaskAttachment(attachmentId) {
    try {
        const response = await fetch('/api/task-attachments.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: attachmentId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadTaskAttachments(currentTaskId);
            loadTasks(); // el contador de adjuntos de la tarjeta
        } else {
            console.error('Error deleting attachment', attachmentId, response.status, data.error);
            showErrorToast(data.error || 'No se pudo eliminar el archivo');
        }
    } catch (error) {
        console.error('Error deleting attachment:', error);
        showErrorToast('No se pudo eliminar el archivo');
    }
}

// Guardar: solo título y descripción (fecha, app, compartir y completar se guardan al momento).
async function saveTask(event) {
    event.preventDefault();
    
    const taskId = parseInt(document.getElementById('task-id').value);
    
    const taskData = {
        id: taskId,
        title: document.getElementById('task-title').value.trim(),
        description: document.getElementById('task-description').value.trim() || null
    };
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(taskData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            taskModalSnapshot = null;
            closeTaskModal();
            await loadTasks();
            showToast({ title: 'Guardado', message: 'Tarea actualizada', icon: 'iconoir-check' }, 'toast-completed');
        } else {
            console.error('Error saving task', taskId, response.status, data.error);
            showErrorToast(data.error || 'No se pudo guardar la tarea');
        }
    } catch (error) {
        console.error('Error saving task:', error);
        showErrorToast('No se pudo guardar la tarea');
    }
}

// Delete task from modal
async function deleteTask() {
    if (!confirm('¿Eliminar esta tarea? También se borrarán sus archivos.')) return;
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentTaskId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            taskModalSnapshot = null;
            closeTaskModal();
            await loadTasks();
            showToast({ title: 'Eliminada', message: 'Tarea eliminada', icon: 'iconoir-check' }, 'toast-completed');
        } else {
            console.error('Error deleting task', currentTaskId, response.status, data.error);
            showErrorToast(data.error || 'No se pudo eliminar la tarea');
        }
    } catch (error) {
        console.error('Error deleting task:', error);
        showErrorToast('No se pudo eliminar la tarea');
    }
}

// Cerrar desde X, Cancelar, Esc o el fondo: avisa si hay cambios sin guardar.
function requestCloseTaskModal() {
    if (isTaskModalDirty() && !confirm('Tienes cambios sin guardar. ¿Cerrar sin guardar?')) return;
    closeTaskModal();
}

// Close task modal
function closeTaskModal() {
    if (typeof closeTaskMenu === 'function') closeTaskMenu();
    document.getElementById('task-modal').classList.remove('active');
    document.getElementById('task-modal-content').classList.remove('is-dragging');
    currentTaskId = null;
    currentTaskCaps = {};
    currentModalTask = null;
    taskModalSnapshot = null;

    const returnFocus = taskModalReturnFocus;
    taskModalReturnFocus = null;
    if (returnFocus && document.contains(returnFocus) && typeof returnFocus.focus === 'function') {
        returnFocus.focus({ preventScroll: true });
    }
}

// Format due date for display
function formatDueDate(dateString) {
    if (!dateString) return '';
    
    const [y, m, d] = dateString.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    const today = new Date();
    const localToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    
    const diffDays = Math.ceil((date - localToday) / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return 'Hoy';
    if (diffDays === 1) return 'Mañana';
    if (diffDays === -1) return 'Ayer';
    
    const options = { day: 'numeric', month: 'short' };
    return date.toLocaleDateString('es-ES', options);
}

// Utility functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getFileIcon(mimeType) {
    if (!mimeType) return 'iconoir-attachment';
    if (mimeType.startsWith('image/')) return 'iconoir-media-image';
    if (mimeType === 'application/pdf') return 'iconoir-page';
    if (mimeType.includes('word')) return 'iconoir-page';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'iconoir-table-2-columns';
    return 'iconoir-attachment';
}

// ========== Equipo ==========

// Personas de la empresa (para el menú "Compartir con" y los nombres de la barra del modal).
async function loadTeamMembers() {
    try {
        const response = await fetch('/api/task-shares.php');
        const data = await response.json();
        if (data.success) {
            teamMembers = data.data;
            refreshTaskModalBar();
            if (lastTasks.length) renderTasks(lastTasks); // ya se pueden poner nombres en las tarjetas
        }
    } catch (error) {
        console.error('Error loading team members:', error);
    }
}

// Get initials from name
function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
}

// config: { title, message, icon, duration (ms, 3000 por defecto), action: { label, onClick } }
function showToast(config, className = '') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${className}`;
    toast.innerHTML = `
        <div class="toast-icon"><i class="${config.icon || 'iconoir-check'}"></i></div>
        <div class="toast-content">
            <div class="toast-title">${config.title || ''}</div>
            <div class="toast-message">${config.message || ''}</div>
        </div>
        ${config.action ? `<button type="button" class="toast-action">${escapeHtml(config.action.label)}</button>` : ''}
    `;
    
    container.appendChild(toast);

    let dismissed = false;
    const dismiss = () => {
        if (dismissed) return;
        dismissed = true;
        toast.classList.add('toast-hide');
        setTimeout(() => toast.remove(), 300);
    };

    if (config.action) {
        toast.querySelector('.toast-action').addEventListener('click', () => {
            dismiss();
            config.action.onClick();
        });
    }
    
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(dismiss, config.duration || 3000);
}

// Aviso de error estándar de la página.
function showErrorToast(message) {
    showToast({ title: 'Error', message: escapeHtml(message), icon: 'iconoir-warning-circle' }, 'toast-error');
}

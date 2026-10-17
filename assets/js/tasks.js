/**
 * Tasks Page JavaScript
 */

let currentTaskId = null;
// Capacidades del usuario sobre la tarea abierta en el modal (vienen de la API).
let currentTaskCaps = {};
let teamMembers = [];
let selectedShareUsers = [];
let shareWithAll = false;

// Detección descartada por el usuario en la creación rápida (chips con la "x").
// Se mantiene hasta que se crea la tarea o se vacía el input.
let quickIgnore = { date: false, app: false };

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
    setupShareDropdown();
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
    if (typeof parseQuickTask !== 'function') return { date: null, app: null };
    return parseQuickTask(input.value, typeof QUICK_APPS !== 'undefined' ? QUICK_APPS : []);
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
    quickIgnore = { date: false, app: false };
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

    // Detección NLP: limpiamos del título solo los tokens no descartados.
    const parsed = parseQuickInput();
    let title = raw;
    let parsedAppId = null;
    let parsedDueDate = null;

    if (parsed.date && !quickIgnore.date) {
        title = stripQuickMatch(title, parsed.date.match);
        parsedDueDate = parsed.date.value;
    }
    if (parsed.app && !quickIgnore.app) {
        title = stripQuickMatch(title, parsed.app.match);
        parsedAppId = parsed.app.id;
    }
    title = title.trim();
    if (!title) title = raw; // no dejar el título vacío si todo era metadato

    const expanded = document.getElementById('quick-add-expanded');
    const isExpanded = expanded.classList.contains('visible');

    const taskData = {
        title: title
    };

    if (parsedAppId) taskData.app_id = parsedAppId;
    if (parsedDueDate) taskData.due_date = parsedDueDate;

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
    if (task.is_shared) {
        meta.push('<span class="task-badge shared"><i class="iconoir-group"></i>Equipo</span>');
    } else if (task.share_count > 0) {
        meta.push(`<span class="task-badge shared"><i class="iconoir-user"></i>${task.share_count}</span>`);
    }
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
                    <button type="button" class="task-action-btn" onclick="openPostponeMenu(${task.id}, this, event)" title="Posponer (p)">
                        <i class="iconoir-clock-rotate-right"></i>
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

// Setup task modal
function setupTaskModal() {
    const fileUpload = document.getElementById('task-file-upload');
    const fileInput = document.getElementById('task-file-input');
    
    fileUpload.addEventListener('click', () => fileInput.click());
    fileUpload.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileUpload.style.borderColor = 'var(--primary-color)';
    });
    fileUpload.addEventListener('dragleave', () => {
        fileUpload.style.borderColor = 'var(--border-color)';
    });
    fileUpload.addEventListener('drop', (e) => {
        e.preventDefault();
        fileUpload.style.borderColor = 'var(--border-color)';
        handleTaskFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', () => {
        handleTaskFiles(fileInput.files);
        fileInput.value = '';
    });
}

// Handle file upload for task
async function handleTaskFiles(files) {
    if (!currentTaskId || files.length === 0) return;
    
    const progressContainer = document.getElementById('upload-progress-container');
    if (progressContainer) progressContainer.style.display = 'flex';
    
    try {
        for (const file of files) {
            const formData = new FormData();
            formData.append('task_id', currentTaskId);
            formData.append('file', file);
            
            try {
                const response = await fetch('/api/task-attachments.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await loadTaskAttachments(currentTaskId);
                } else {
                    showErrorToast(data.error || 'No se pudo completar la acción');
                }
            } catch (error) {
                console.error('Error uploading file:', error);
                showErrorToast('Error de conexión al subir el archivo');
            }
        }
    } finally {
        if (progressContainer) progressContainer.style.display = 'none';
    }
}

// Open task modal
async function openTaskModal(taskId) {
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

        const task = data.data;
        currentTaskCaps = task.capabilities || {};
        document.getElementById('task-id').value = task.id;
        document.getElementById('task-title').value = task.title;
        document.getElementById('task-description').value = task.description || '';
        document.getElementById('task-app').value = task.app_id || '';
        document.getElementById('task-due-date').value = task.due_date || '';

        // Load share settings
        await loadTaskShares(taskId, task.is_shared == 1);

        await loadTaskAttachments(taskId);

        applyTaskModalPermissions(task);

        document.getElementById('task-modal').classList.add('active');
    } catch (error) {
        console.error('Error loading task:', error);
        currentTaskId = null;
        showErrorToast('No se pudo abrir la tarea');
    }
}

// Ajusta el modal a lo que el usuario puede hacer con la tarea abierta.
function applyTaskModalPermissions(task) {
    const canShare = currentTaskCaps.share !== false;
    const hint = document.getElementById('share-readonly-hint');

    document.getElementById('share-selector').classList.toggle('is-readonly', !canShare);
    document.getElementById('task-danger-zone').hidden = currentTaskCaps.delete === false;
    hint.hidden = canShare;

    if (!canShare) {
        const creator = task.creator_name || task.creator_username || 'otra persona';
        hint.textContent = `Creada por ${creator}. Solo esa persona puede cambiar con quién se comparte.`;
        if (!shareWithAll) {
            document.getElementById('share-status').textContent = 'Compartida contigo';
        }
    }
}

// Load task attachments
async function loadTaskAttachments(taskId) {
    const container = document.getElementById('task-attachments');
    
    try {
        const response = await fetch(`/api/task-attachments.php?task_id=${taskId}`);
        const data = await response.json();
        
        if (data.success) {
            if (data.data.length === 0) {
                container.innerHTML = '<p class="text-muted text-small">Sin archivos adjuntos</p>';
            } else {
                container.innerHTML = data.data.map(att => `
                    <div class="task-attachment-item">
                        <a href="#" class="fv-trigger" data-type="task" data-id="${att.id}" data-mime="${att.mime_type || ''}">
                            <i class="${getFileIcon(att.mime_type)}"></i>
                            <span class="fv-name">${escapeHtml(att.original_filename)}</span>
                        </a>
                        ${currentTaskCaps.delete !== false ? `
                            <button type="button" class="task-action-btn delete" onclick="deleteTaskAttachment(${att.id})" title="Eliminar archivo">
                                <i class="iconoir-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                `).join('');
            }
        }
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
        } else {
            console.error('Error deleting attachment', attachmentId, response.status, data.error);
            showErrorToast(data.error || 'No se pudo eliminar el archivo');
        }
    } catch (error) {
        console.error('Error deleting attachment:', error);
        showErrorToast('No se pudo eliminar el archivo');
    }
}

// Save task
async function saveTask(event) {
    event.preventDefault();
    
    const taskId = parseInt(document.getElementById('task-id').value);
    
    const taskData = {
        id: taskId,
        title: document.getElementById('task-title').value.trim(),
        description: document.getElementById('task-description').value.trim() || null,
        app_id: document.getElementById('task-app').value || null,
        due_date: document.getElementById('task-due-date').value || null
    };
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(taskData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Compartir solo lo cambia el dueño; para el resto el selector es de solo lectura.
            if (currentTaskCaps.share !== false && !(await saveTaskShares(taskId))) {
                showErrorToast('La tarea se guardó, pero no se pudo cambiar con quién se comparte');
            }
            
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
    if (!confirm('¿Eliminar esta tarea?')) return;
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentTaskId })
        });
        
        const data = await response.json();
        
        if (data.success) {
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

// Close task modal
function closeTaskModal() {
    document.getElementById('task-modal').classList.remove('active');
    document.getElementById('share-selector').classList.remove('open');
    currentTaskId = null;
    currentTaskCaps = {};
    selectedShareUsers = [];
    shareWithAll = false;
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

// Escape HTML helper
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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

// ========== Share Functions ==========

// Load team members for sharing
async function loadTeamMembers() {
    try {
        const response = await fetch('/api/task-shares.php');
        const data = await response.json();
        if (data.success) {
            teamMembers = data.data;
            renderShareUsersList();
        }
    } catch (error) {
        console.error('Error loading team members:', error);
    }
}

// Render users list in dropdown
function renderShareUsersList() {
    const container = document.getElementById('share-users-list');
    if (!container) return;
    
    if (teamMembers.length === 0) {
        container.innerHTML = '<div class="share-option" style="color: var(--text-muted); font-size: 0.8rem; text-align: center; padding: 1rem;">No hay otros miembros en el equipo</div>';
        return;
    }
    
    container.innerHTML = teamMembers.map(user => {
        const initials = getInitials(user.full_name || user.username);
        const isChecked = selectedShareUsers.includes(user.id);
        return `
            <div class="share-option">
                <label>
                    <div class="share-user-item">
                        <div class="share-user-avatar">${initials}</div>
                        <span class="share-user-name">${escapeHtml(user.full_name || user.username)}</span>
                    </div>
                    <input type="checkbox" 
                           value="${user.id}" 
                           ${isChecked ? 'checked' : ''}
                           ${shareWithAll ? 'disabled' : ''}
                           onchange="toggleShareUser(${user.id})">
                </label>
            </div>
        `;
    }).join('');
}

// Get initials from name
function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
}

// Toggle share dropdown
function toggleShareDropdown() {
    const selector = document.getElementById('share-selector');
    if (selector.classList.contains('is-readonly')) return;
    selector.classList.toggle('open');
}

// Setup share dropdown (close on outside click)
function setupShareDropdown() {
    document.addEventListener('click', (e) => {
        const selector = document.getElementById('share-selector');
        if (selector && !selector.contains(e.target)) {
            selector.classList.remove('open');
        }
    });
}

// Toggle share with all
function toggleShareAll() {
    shareWithAll = document.getElementById('share-with-all').checked;
    if (shareWithAll) {
        selectedShareUsers = [];
    }
    renderShareUsersList();
    updateShareStatus();
}

// Toggle individual user
function toggleShareUser(userId) {
    const index = selectedShareUsers.indexOf(userId);
    if (index > -1) {
        selectedShareUsers.splice(index, 1);
    } else {
        selectedShareUsers.push(userId);
    }
    updateShareStatus();
}

// Update share status display
function updateShareStatus() {
    const statusEl = document.getElementById('share-status');
    
    if (shareWithAll) {
        statusEl.innerHTML = '<span class="user-chip all"><i class="iconoir-group"></i> Todo el equipo</span>';
    } else if (selectedShareUsers.length === 0) {
        statusEl.textContent = 'Privada';
    } else {
        const chips = selectedShareUsers.slice(0, 3).map(userId => {
            const user = teamMembers.find(u => u.id === userId);
            if (user) {
                const initials = getInitials(user.full_name || user.username);
                return `<span class="user-chip">${initials}</span>`;
            }
            return '';
        }).join('');
        
        const extra = selectedShareUsers.length > 3 ? `<span class="user-chip">+${selectedShareUsers.length - 3}</span>` : '';
        statusEl.innerHTML = chips + extra;
    }
}

// Load task shares when opening modal
async function loadTaskShares(taskId, isSharedWithAll) {
    shareWithAll = isSharedWithAll;
    selectedShareUsers = [];
    
    document.getElementById('share-with-all').checked = isSharedWithAll;
    
    if (!isSharedWithAll) {
        try {
            const response = await fetch(`/api/task-shares.php?task_id=${taskId}`);
            const data = await response.json();
            if (data.success) {
                selectedShareUsers = data.data.map(u => u.id);
            }
        } catch (error) {
            console.error('Error loading task shares:', error);
        }
    }
    
    renderShareUsersList();
    updateShareStatus();
}

// Save share settings (called from saveTask)
// Devuelve true si se guardó.
async function saveTaskShares(taskId) {
    try {
        const response = await fetch('/api/task-shares.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: taskId,
                user_ids: selectedShareUsers,
                share_with_all: shareWithAll
            })
        });
        const data = await response.json();
        if (!data.success) {
            console.error('Error saving shares', taskId, response.status, data.error);
        }
        return data.success === true;
    } catch (error) {
        console.error('Error saving shares:', error);
        return false;
    }
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

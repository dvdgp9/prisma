/**
 * Mis tareas: interacciones rápidas sobre la lista.
 * Menú contextual (posponer / cambiar app), renombrar en línea, atajos de
 * teclado y deslizar en móvil. Depende de tasks.js (lastTasks, renderTasks,
 * putTask, loadTasks, showToast...) y de task-parser.js (taskDateShortcuts).
 */

document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('keydown', handleTasksShortcut);
    setupTaskSwipe();
});

// La tarea de la lista o, si ya no está en ella (p. ej. completada con el filtro desactivado), la del modal.
function findTask(taskId) {
    return lastTasks.find(t => t.id == taskId)
        || (typeof currentModalTask !== 'undefined' && currentModalTask && currentModalTask.id == taskId ? currentModalTask : undefined);
}

function canEditTask(task) {
    return !!task && (task.capabilities || {}).edit !== false && !task.is_completed;
}

// ========== Menú contextual genérico ==========

let openMenuEl = null;
let openMenuCleanup = null;

function closeTaskMenu() {
    if (openMenuCleanup) openMenuCleanup();
    if (openMenuEl) openMenuEl.remove();
    openMenuEl = null;
    openMenuCleanup = null;
}

/**
 * Abre un menú flotante junto a `anchor` y lo devuelve.
 * items: [{ label, icon, hint, active, onSelect }]
 *      | { type: 'toggle', key, label, icon | avatar, onToggle }  (no cierra el menú; el estado lo pinta quien lo abre)
 *      | { type: 'date', label, value, onSelect } | { type: 'note', label } | { type: 'separator' }
 * options: { title } — se muestra arriba y sirve de nombre accesible.
 */
function openTaskMenu(anchor, items, options = {}) {
    closeTaskMenu();

    const menu = document.createElement('div');
    menu.className = 'task-menu';
    menu.setAttribute('role', 'menu');
    menu.setAttribute('aria-label', options.title || 'Opciones de la tarea');
    if (options.title) {
        menu.insertAdjacentHTML('beforeend', `<div class="task-menu-title">${escapeHtml(options.title)}</div>`);
    }

    items.forEach(item => {
        if (item.type === 'separator') {
            menu.insertAdjacentHTML('beforeend', '<div class="task-menu-separator" role="separator"></div>');
            return;
        }

        if (item.type === 'note') {
            menu.insertAdjacentHTML('beforeend', `<div class="task-menu-note">${escapeHtml(item.label)}</div>`);
            return;
        }

        // Fila fija, no se puede pulsar (p. ej. quien crea la tarea en "Compartir con").
        if (item.type === 'fixed') {
            menu.insertAdjacentHTML('beforeend', `
                <div class="task-menu-item task-menu-fixed" aria-disabled="true"${item.title ? ` title="${escapeHtml(item.title)}"` : ''}>
                    <span class="task-menu-avatar">${escapeHtml(item.avatar)}</span>
                    <span>${escapeHtml(item.label)}</span>
                    <i class="iconoir-check task-menu-check" aria-hidden="true"></i>
                </div>`);
            return;
        }

        if (item.type === 'toggle') {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'task-menu-item task-menu-toggle';
            btn.setAttribute('role', 'menuitemcheckbox');
            btn.dataset.key = item.key;
            btn.innerHTML = `
                ${item.avatar ? `<span class="task-menu-avatar">${escapeHtml(item.avatar)}</span>` : `<i class="${item.icon}"></i>`}
                <span>${escapeHtml(item.label)}</span>
                <i class="iconoir-check task-menu-check"></i>`;
            btn.addEventListener('click', () => item.onToggle());
            menu.appendChild(btn);
            return;
        }

        if (item.type === 'date') {
            const row = document.createElement('label');
            row.className = 'task-menu-item task-menu-date';
            row.innerHTML = `<i class="iconoir-calendar"></i><span>${escapeHtml(item.label)}</span>`;
            const input = document.createElement('input');
            input.type = 'date';
            input.value = item.value || '';
            input.addEventListener('change', () => {
                if (!input.value) return;
                closeTaskMenu();
                item.onSelect(input.value);
            });
            row.appendChild(input);
            menu.appendChild(row);
            return;
        }

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'task-menu-item' + (item.active ? ' is-active' : '');
        btn.setAttribute('role', 'menuitem');
        btn.innerHTML = `
            <i class="${item.icon || 'iconoir-arrow-right'}"></i>
            <span>${escapeHtml(item.label)}</span>
            ${item.hint ? `<span class="task-menu-hint">${escapeHtml(item.hint)}</span>` : ''}
            ${item.active ? '<i class="iconoir-check task-menu-check"></i>' : ''}`;
        btn.addEventListener('click', () => {
            closeTaskMenu();
            item.onSelect();
        });
        menu.appendChild(btn);
    });

    document.body.appendChild(menu);
    positionTaskMenu(menu, anchor);
    openMenuEl = menu;

    // Cerrar al hacer clic fuera, con Escape, al hacer scroll o al redimensionar.
    const onPointerDown = (e) => {
        if (!menu.contains(e.target) && !anchor.contains(e.target)) closeTaskMenu();
    };
    const onKeyDown = (e) => {
        if (e.key === 'Escape') {
            e.stopPropagation();
            closeTaskMenu();
            return;
        }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            moveMenuFocus(menu, e.key === 'ArrowDown' ? 1 : -1);
        }
    };
    const onViewportChange = () => closeTaskMenu();

    setTimeout(() => document.addEventListener('pointerdown', onPointerDown, true), 0);
    document.addEventListener('keydown', onKeyDown, true);
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);
    openMenuCleanup = () => {
        document.removeEventListener('pointerdown', onPointerDown, true);
        document.removeEventListener('keydown', onKeyDown, true);
        window.removeEventListener('resize', onViewportChange);
        window.removeEventListener('scroll', onViewportChange, true);
    };

    const first = menu.querySelector('button.task-menu-item');
    if (first) first.focus({ preventScroll: true });
    return menu;
}

// Coloca el menú bajo el ancla, dentro de la ventana (encima si no cabe debajo).
function positionTaskMenu(menu, anchor) {
    const gap = 6;
    const margin = 8;
    const rect = anchor.getBoundingClientRect();
    const width = menu.offsetWidth;
    const height = menu.offsetHeight;

    let left = Math.min(rect.left, window.innerWidth - width - margin);
    left = Math.max(margin, left);
    let top = rect.bottom + gap;
    if (top + height > window.innerHeight - margin) {
        top = Math.max(margin, rect.top - height - gap);
    }

    menu.style.left = `${left}px`;
    menu.style.top = `${top}px`;
}

function moveMenuFocus(menu, step) {
    const items = [...menu.querySelectorAll('button.task-menu-item, .task-menu-date input')];
    if (!items.length) return;
    const index = items.indexOf(document.activeElement);
    const next = items[(index + step + items.length) % items.length];
    next.focus();
}

// ========== Posponer / cambiar fecha ==========

// Etiqueta corta "vie 16 oct" para las pistas del menú.
function shortDateLabel(iso) {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d)
        .toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short' })
        .replace(',', '');
}

function openPostponeMenu(taskId, anchor, event) {
    if (event) event.stopPropagation();
    const task = findTask(taskId);
    if (!canEditTask(task)) return;

    const dates = taskDateShortcuts(new Date());
    const pick = (value) => () => setTaskDueDate(taskId, value);
    const items = [];

    if (task.due_date !== dates.today) {
        items.push({ label: 'Hoy', icon: 'iconoir-sun-light', hint: shortDateLabel(dates.today), onSelect: pick(dates.today) });
    }
    items.push(
        { label: 'Mañana', icon: 'iconoir-calendar', hint: shortDateLabel(dates.tomorrow), active: task.due_date === dates.tomorrow, onSelect: pick(dates.tomorrow) },
        { label: 'Próximo lunes', icon: 'iconoir-skip-next', hint: shortDateLabel(dates.nextMonday), active: task.due_date === dates.nextMonday, onSelect: pick(dates.nextMonday) },
        { label: 'En una semana', icon: 'iconoir-calendar-plus', hint: shortDateLabel(dates.nextWeek), active: task.due_date === dates.nextWeek, onSelect: pick(dates.nextWeek) },
        { type: 'separator' },
        { type: 'date', label: 'Elegir fecha', value: task.due_date, onSelect: (value) => setTaskDueDate(taskId, value) }
    );
    if (task.due_date) {
        items.push({ label: 'Quitar fecha', icon: 'iconoir-calendar-xmark', onSelect: pick(null) });
    }

    openTaskMenu(anchor, items, { title: task.due_date ? 'Posponer' : 'Poner fecha' });
}

// Texto para avisos: "para hoy", "para mañana", "para el 20 oct", "sin fecha".
function describeDueDate(value) {
    if (!value) return 'sin fecha';
    const label = formatDueDate(value);
    return label === 'Hoy' || label === 'Mañana' ? `para ${label.toLowerCase()}` : `para el ${label}`;
}

// Cambio optimista: se ve al instante, se revierte si la API falla, y se puede deshacer.
async function setTaskDueDate(taskId, dueDate) {
    const task = findTask(taskId);
    if (!task || task.due_date === dueDate) return;

    const previous = task.due_date;
    task.due_date = dueDate;
    renderTasks(lastTasks);

    if (!(await putTask({ id: taskId, due_date: dueDate }, 'No se pudo cambiar la fecha'))) {
        task.due_date = previous;
        renderTasks(lastTasks);
        return;
    }

    if (typeof loadTasksCount === 'function') loadTasksCount();
    showToast({
        title: 'Fecha cambiada',
        message: `${escapeHtml(task.title)} · ${describeDueDate(dueDate)}`,
        icon: 'iconoir-calendar',
        duration: 6000,
        action: { label: 'Deshacer', onClick: () => setTaskDueDate(taskId, previous) }
    }, 'toast-completed');
}

// "Pasar todo a hoy" en la cabecera de Vencidas.
async function moveOverdueToToday() {
    const today = taskDateShortcuts(new Date()).today;
    const localToday = getLocalToday();
    const overdue = lastTasks.filter(t => canEditTask(t) && getTaskBucket(t, localToday) === 'overdue');
    if (!overdue.length) return;

    const previous = new Map(overdue.map(t => [t.id, t.due_date]));
    overdue.forEach(t => { t.due_date = today; });
    renderTasks(lastTasks);

    const results = await Promise.all(overdue.map(t => putTask({ id: t.id, due_date: today }, 'No se pudo mover alguna tarea')));
    const moved = overdue.filter((t, i) => results[i]);
    overdue.filter((t, i) => !results[i]).forEach(t => { t.due_date = previous.get(t.id); });
    renderTasks(lastTasks);
    if (typeof loadTasksCount === 'function') loadTasksCount();
    if (!moved.length) return;

    showToast({
        title: 'Vencidas pasadas a hoy',
        message: `${moved.length} ${moved.length === 1 ? 'tarea' : 'tareas'}`,
        icon: 'iconoir-sun-light',
        duration: 6000,
        action: {
            label: 'Deshacer',
            onClick: async () => {
                await Promise.all(moved.map(t => putTask({ id: t.id, due_date: previous.get(t.id) }, 'No se pudo deshacer')));
                await loadTasks();
            }
        }
    }, 'toast-completed');
}

// ========== Cambiar aplicación ==========

function openAppMenu(taskId, anchor, event) {
    if (event) event.stopPropagation();
    const task = findTask(taskId);
    if (!canEditTask(task)) return;

    const apps = typeof QUICK_APPS !== 'undefined' ? QUICK_APPS : [];
    const items = apps.map(app => ({
        label: app.name,
        icon: 'iconoir-app-window',
        active: task.app_id == app.id,
        onSelect: () => setTaskApp(taskId, app.id, app.name)
    }));
    items.push({ type: 'separator' }, {
        label: 'Sin aplicación',
        icon: 'iconoir-xmark',
        active: !task.app_id,
        onSelect: () => setTaskApp(taskId, null, null)
    });

    openTaskMenu(anchor, items, { title: 'Aplicación' });
}

async function setTaskApp(taskId, appId, appName) {
    const task = findTask(taskId);
    if (!task || task.app_id == appId) return;

    const previous = { app_id: task.app_id, app_name: task.app_name };
    task.app_id = appId;
    task.app_name = appName;
    renderTasks(lastTasks);

    if (!(await putTask({ id: taskId, app_id: appId }, 'No se pudo cambiar la aplicación'))) {
        Object.assign(task, previous);
        renderTasks(lastTasks);
    }
}

// ========== Compartir ==========

// Los guardados se encadenan para que varios clics seguidos lleguen en orden.
let shareSaveChain = Promise.resolve();

// Solo el dueño cambia con quién se comparte (capacidad "share").
function canShareTask(task) {
    return !!task && (task.capabilities || {}).share !== false && !task.is_completed;
}

async function openShareMenu(taskId, anchor, event) {
    if (event) event.stopPropagation();
    const task = findTask(taskId);
    if (!canShareTask(task)) return;

    // El listado solo trae cuántas personas; para marcarlas hay que pedir quiénes son
    // (salvo que ya se sepa, p. ej. porque la barra del modal ya lo pidió).
    let users = Array.isArray(task.shared_user_ids) ? task.shared_user_ids.slice() : [];
    if (!users.length && !task.is_shared && task.share_count > 0) {
        try {
            const response = await fetch(`/api/task-shares.php?task_id=${taskId}`);
            const data = await response.json();
            if (!data.success) throw new Error(`${response.status} ${data.error}`);
            users = data.data.map(u => Number(u.id));
        } catch (error) {
            console.error('Error loading task shares', taskId, error);
            showErrorToast('No se pudo cargar con quién está compartida');
            return;
        }
    }

    const state = { all: task.is_shared == 1, users };
    const members = typeof teamMembers !== 'undefined' ? teamMembers : [];
    let menu = null;

    // Pinta las marcas según el estado (el menú no se reconstruye para no perder el foco).
    const refresh = () => {
        menu.querySelectorAll('.task-menu-toggle').forEach(btn => {
            const key = btn.dataset.key;
            const checked = key === 'private' ? (!state.all && state.users.length === 0)
                : key === 'all' ? state.all
                : state.users.includes(Number(key.replace('user-', '')));
            btn.classList.toggle('is-active', checked);
            btn.setAttribute('aria-checked', checked ? 'true' : 'false');
            btn.disabled = key.startsWith('user-') && state.all;
        });
    };

    const change = (next) => {
        Object.assign(state, next);
        task.is_shared = state.all ? 1 : 0;
        task.share_count = state.all ? 0 : state.users.length;
        task.shared_user_ids = state.users.slice();
        renderTasks(lastTasks);
        refresh();
        saveTaskShareState(taskId, { all: state.all, users: state.users.slice() });
    };

    const items = [
        { type: 'toggle', key: 'private', icon: 'iconoir-lock', label: 'Solo para mí', onToggle: () => change({ all: false, users: [] }) },
        { type: 'toggle', key: 'all', icon: 'iconoir-group', label: 'Todo el equipo', onToggle: () => change({ all: !state.all, users: [] }) },
        { type: 'separator' },
        // Quien la crea siempre la tiene: se ve arriba, marcada y fija.
        { type: 'fixed', avatar: getInitials(document.body.dataset.userName || '') || 'Tú', label: 'Tú (es tuya)', title: 'Quien crea la tarea siempre la tiene' }
    ];
    if (members.length) {
        members.forEach(member => {
            const id = Number(member.id);
            const name = member.full_name || member.username;
            items.push({
                type: 'toggle',
                key: `user-${id}`,
                avatar: getInitials(name),
                label: name,
                onToggle: () => change({
                    all: false,
                    users: state.users.includes(id) ? state.users.filter(u => u !== id) : state.users.concat(id)
                })
            });
        });
    } else {
        items.push({ type: 'note', label: 'No hay más personas en el equipo' });
    }

    menu = openTaskMenu(anchor, items, { title: 'Compartir con' });
    refresh();
}

function saveTaskShareState(taskId, state) {
    const payload = { task_id: taskId, user_ids: state.users, share_with_all: state.all };

    shareSaveChain = shareSaveChain.then(async () => {
        try {
            const response = await fetch('/api/task-shares.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (data.success) return;
            console.error('Error saving task shares', payload, response.status, data.error);
            showErrorToast(data.error || 'No se pudo cambiar con quién se comparte');
        } catch (error) {
            console.error('Error saving task shares:', payload, error);
            showErrorToast('No se pudo cambiar con quién se comparte');
        }
        // Si falla, se vuelve a lo que diga el servidor.
        closeTaskMenu();
        await loadTasks();
    });
}

// ========== Renombrar en línea ==========

// Un clic en el título abre el editor con un pequeño retraso para dejar sitio al doble clic.
let titleClickTimer = null;
// Tras deslizar en móvil se ignora el clic que genera el navegador.
let suppressClicksUntil = 0;

function onTaskContentClick(taskId, event) {
    if (Date.now() < suppressClicksUntil) return;
    if (event.target.closest('.task-title-input')) return;

    const onTitle = event.target.closest('.task-title');
    if (onTitle && canEditTask(findTask(taskId))) {
        clearTimeout(titleClickTimer);
        titleClickTimer = setTimeout(() => openTaskModal(taskId), 250);
        return;
    }
    openTaskModal(taskId);
}

function startInlineTitleEdit(taskId, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    clearTimeout(titleClickTimer);

    const task = findTask(taskId);
    const titleEl = document.querySelector(`.task-item[data-id="${taskId}"] .task-title`);
    if (!canEditTask(task) || !titleEl || titleEl.querySelector('input')) return;

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'task-title-input';
    input.value = task.title;
    input.maxLength = 500;
    input.setAttribute('aria-label', 'Nuevo título');
    titleEl.textContent = '';
    titleEl.appendChild(input);
    input.focus();
    input.select();

    let finished = false;
    const finish = async (save) => {
        if (finished) return;
        finished = true;
        const newTitle = input.value.trim();

        if (!save || !newTitle || newTitle === task.title) {
            renderTasks(lastTasks);
            return;
        }

        const previous = task.title;
        task.title = newTitle;
        renderTasks(lastTasks);
        if (!(await putTask({ id: taskId, title: newTitle }, 'No se pudo renombrar la tarea'))) {
            task.title = previous;
            renderTasks(lastTasks);
        }
    };

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            finish(true);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            finish(false);
        }
    });
    input.addEventListener('blur', () => finish(true));
    input.addEventListener('click', (e) => e.stopPropagation());
    input.addEventListener('dblclick', (e) => e.stopPropagation());
}

// ========== Atajos de teclado ==========

let focusedTaskId = null;

function getRenderedTaskCards() {
    return [...document.querySelectorAll('#tasks-list .task-item')];
}

function setFocusedTask(taskId) {
    getRenderedTaskCards().forEach(el => el.classList.remove('is-focused'));
    focusedTaskId = taskId;
    const card = document.querySelector(`#tasks-list .task-item[data-id="${taskId}"]`);
    if (card) {
        card.classList.add('is-focused');
        card.scrollIntoView({ block: 'nearest' });
    }
}

// Llamado por renderTasks: mantiene la tarea enfocada tras cada repintado.
function onTasksRendered() {
    if (typeof refreshTaskModalBar === 'function') refreshTaskModalBar();
    if (focusedTaskId === null) return;
    const card = document.querySelector(`#tasks-list .task-item[data-id="${focusedTaskId}"]`);
    if (card) card.classList.add('is-focused');
    else focusedTaskId = null;
}

function moveTaskFocus(step) {
    const cards = getRenderedTaskCards();
    if (!cards.length) return;
    const index = cards.findIndex(el => el.dataset.id == focusedTaskId);
    const next = index === -1 ? (step > 0 ? 0 : cards.length - 1) : Math.min(cards.length - 1, Math.max(0, index + step));
    setFocusedTask(cards[next].dataset.id);
}

function isTypingTarget(el) {
    return !!el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
}

function handleTasksShortcut(e) {
    if (e.metaKey || e.ctrlKey || e.altKey || openMenuEl) return;
    if (document.getElementById('task-modal').classList.contains('active')) return;
    if (isTypingTarget(document.activeElement)) return;

    const focusedCard = focusedTaskId !== null
        ? document.querySelector(`#tasks-list .task-item[data-id="${focusedTaskId}"]`)
        : null;
    const focusedTask = focusedCard ? findTask(focusedTaskId) : null;

    switch (e.key) {
        case '/':
            e.preventDefault();
            document.getElementById('tasks-search').focus();
            break;
        case 'n':
            e.preventDefault();
            document.getElementById('quick-add-input').focus();
            break;
        case 'j':
            e.preventDefault();
            moveTaskFocus(1);
            break;
        case 'k':
            e.preventDefault();
            moveTaskFocus(-1);
            break;
        case 'ArrowDown':
        case 'ArrowUp':
            // Las flechas solo mueven el foco si ya hay una tarea seleccionada (si no, hacen scroll).
            if (!focusedCard) return;
            e.preventDefault();
            moveTaskFocus(e.key === 'ArrowDown' ? 1 : -1);
            break;
        case 'x':
            if (!focusedTask) return;
            e.preventDefault();
            toggleTask(focusedTask.id, !focusedTask.is_completed);
            break;
        case 'p':
            if (!canEditTask(focusedTask)) return;
            e.preventDefault();
            openPostponeMenu(focusedTask.id, focusedCard.querySelector('.task-due-date') || focusedCard);
            break;
        case 'r':
            if (!canEditTask(focusedTask)) return;
            e.preventDefault();
            startInlineTitleEdit(focusedTask.id);
            break;
        case 's':
            if (!canShareTask(focusedTask)) return;
            e.preventDefault();
            openShareMenu(focusedTask.id, focusedCard.querySelector('.task-badge-btn') || focusedCard);
            break;
        case 'e':
        case 'Enter':
            if (!focusedTask) return;
            e.preventDefault();
            openTaskModal(focusedTask.id);
            break;
        case 'Escape':
            if (focusedCard) {
                focusedCard.classList.remove('is-focused');
                focusedTaskId = null;
            }
            break;
    }
}

// ========== Deslizar en móvil ==========

const SWIPE_THRESHOLD = 80;

// Derecha: completar. Izquierda: menú de posponer.
function setupTaskSwipe() {
    const list = document.getElementById('tasks-list');
    if (!list) return;
    let swipe = null;

    const reset = (card) => {
        card.style.transform = '';
        card.classList.remove('is-swiping', 'swipe-complete', 'swipe-postpone');
    };

    list.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'touch') return;
        const card = e.target.closest('.task-item');
        if (!card || card.classList.contains('completed') || e.target.closest('input')) return;
        swipe = { card, id: card.dataset.id, x: e.clientX, y: e.clientY, active: false };
    });

    list.addEventListener('pointermove', (e) => {
        if (!swipe) return;
        const dx = e.clientX - swipe.x;
        const dy = e.clientY - swipe.y;

        if (!swipe.active) {
            if (Math.abs(dy) > 12 && Math.abs(dy) > Math.abs(dx)) {
                swipe = null; // es scroll vertical
                return;
            }
            if (Math.abs(dx) < 12) return;
            swipe.active = true;
            swipe.card.classList.add('is-swiping');
        }

        const limited = Math.max(-120, Math.min(120, dx));
        swipe.card.style.transform = `translateX(${limited}px)`;
        swipe.card.classList.toggle('swipe-complete', dx > SWIPE_THRESHOLD);
        swipe.card.classList.toggle('swipe-postpone', dx < -SWIPE_THRESHOLD && canEditTask(findTask(swipe.id)));
    });

    list.addEventListener('pointerup', (e) => {
        if (!swipe) return;
        const { card, id, x, active } = swipe;
        swipe = null;
        if (!active) return;

        const dx = e.clientX - x;
        reset(card);
        suppressClicksUntil = Date.now() + 400;

        if (dx > SWIPE_THRESHOLD) {
            completeTaskWithUndo(id);
        } else if (dx < -SWIPE_THRESHOLD && canEditTask(findTask(id))) {
            openPostponeMenu(id, card);
        }
    });

    list.addEventListener('pointercancel', () => {
        if (swipe) reset(swipe.card);
        swipe = null;
    });

    // El clic sintético tras deslizar no debe abrir el modal ni marcar la casilla.
    list.addEventListener('click', (e) => {
        if (Date.now() < suppressClicksUntil) {
            e.stopPropagation();
            e.preventDefault();
        }
    }, true);
}

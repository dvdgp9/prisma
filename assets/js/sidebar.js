// Shared navigation for every authenticated page, including the index app view.
let sidebarAppsGrouped = [];
let sidebarApps = [];
const sidebarMobile = matchMedia('(max-width: 768px)');
const sidebarInertBefore = new Map();
const sidebarIdentity = document.querySelector('.sidebar')?.dataset;
const sidebarCollapseKey = `prisma_sidebar:${sidebarIdentity?.userId}:${sidebarIdentity?.companyId}:collapsed`;
let sidebarCollapsed = {};
try { sidebarCollapsed = JSON.parse(sessionStorage.getItem(sidebarCollapseKey) || '{}') || {}; } catch { /* Storage can be unavailable. */ }

function sidebarAppState(state) {
    const target = document.getElementById('apps-nav');
    if (!target) return;
    target.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
    const status = document.getElementById('sidebar-search-status');
    if (status) status.textContent = '';
    if (state === 'loading') {
        target.innerHTML = '<div class="sidebar-apps-loading" aria-hidden="true"><span></span><span></span><span></span></div><span class="sidebar-sr-only">Cargando aplicaciones</span>';
    } else if (state === 'error') {
        target.innerHTML = '<div class="sidebar-apps-message" role="status"><p>No se han podido cargar las aplicaciones.</p><button type="button" onclick="retrySidebarApps()">Reintentar</button></div>';
    }
}

function retrySidebarApps() {
    document.getElementById('sidebar-search-input')?.focus();
    sidebarAppState('loading');
    if (typeof loadApps === 'function') loadApps(); else loadAppsForSidebar();
}

async function loadAppsForSidebar() {
    try {
        const response = await fetch('/api/apps.php?grouped=1');
        const data = await response.json();
        if (!response.ok || !data.success || !Array.isArray(data.data)) throw new Error('Apps unavailable');
        sidebarAppsGrouped = data.data;
        sidebarApps = sidebarAppsGrouped.flatMap(group => group.apps || []);
        renderSidebarApps();
    } catch (error) {
        sidebarAppState('error');
        console.error('Error loading apps for sidebar:', error);
    }
}

function renderSidebarApps() { renderSidebarAppGroups(sidebarAppsGrouped, sidebarApps); }

// The same real links and group controls on Inicio, Notas, Tareas and administration.
function renderSidebarAppGroups(groups, flatApps = []) {
    const target = document.getElementById('apps-nav');
    if (!target) return;
    const onIndex = location.pathname === '/' || location.pathname.endsWith('/index.php');
    const params = new URLSearchParams(location.search);
    const activeApp = params.get('app') || params.get('app_id');
    const activeCompany = params.get('company');
    const localLink = (kind, id) => onIndex ? ` onclick="return navigateSidebarView(event, '${kind}', ${id})"` : '';
    const appMarkup = app => {
        const id = Number(app.id);
        return `<a href="/index.php?app=${id}" class="nav-item${onIndex && activeApp == id ? ' active' : ''}" data-app-id="${id}"${localLink('app', id)} title="${sidebarEscapeHtml(app.name)}">
            <i class="iconoir-app-window" aria-hidden="true"></i><span>${sidebarEscapeHtml(app.name)}</span><span class="nav-count" data-app-count="${id}" hidden></span>
        </a>`;
    };
    target.innerHTML = groups.length ? groups.map(group => {
        const id = Number(group.id);
        const containsActive = onIndex && (group.apps || []).some(app => app.id == activeApp);
        const collapsed = Boolean(sidebarCollapsed[id]) && !containsActive;
        return `<div class="company-group${collapsed ? ' collapsed' : ''}" data-company-id="${id}">
            <div class="company-group-header">
                <a href="/index.php?company=${id}" class="company-nav-item${onIndex && activeCompany == id ? ' active' : ''}" data-company-id="${id}"${localLink('company', id)} title="${sidebarEscapeHtml(group.name)} · Ver todas sus mejoras">
                    <span class="company-group-title"><i class="iconoir-building" aria-hidden="true"></i><span>${sidebarEscapeHtml(group.name)}</span></span>
                </a>
                <button type="button" class="company-group-toggle-btn" onclick="toggleCompanyGroup(${id})" aria-expanded="${!collapsed}" aria-controls="sidebar-company-${id}" aria-label="${collapsed ? 'Mostrar' : 'Ocultar'} aplicaciones de ${sidebarEscapeHtml(group.name)}" data-company-name="${sidebarEscapeHtml(group.name)}">
                    <i class="iconoir-nav-arrow-down company-group-toggle" aria-hidden="true"></i>
                </button>
            </div>
            <div class="company-group-apps" id="sidebar-company-${id}"${collapsed ? ' hidden' : ''}>
                ${(group.apps || []).length ? group.apps.map(appMarkup).join('') : '<p class="sidebar-group-empty">Sin aplicaciones</p>'}
            </div>
        </div>`;
    }).join('') : flatApps.map(appMarkup).join('');
    if (!target.children.length) target.innerHTML = '<p class="sidebar-apps-message">Todavía no tienes aplicaciones disponibles.</p>';
    target.setAttribute('aria-busy', 'false');
    filterSidebarApps(document.getElementById('sidebar-search-input')?.value || '');
    syncSidebarCurrent();
}

function navigateSidebarView(event, kind, id) {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return true;
    event.preventDefault();
    loadView(kind, id, event);
    return false;
}

function setSidebarGroupExpanded(group, expanded) {
    group.classList.toggle('collapsed', !expanded);
    group.querySelector('.company-group-apps').hidden = !expanded;
    const button = group.querySelector('.company-group-toggle-btn');
    button.setAttribute('aria-expanded', String(expanded));
    button.setAttribute('aria-label', `${expanded ? 'Ocultar' : 'Mostrar'} aplicaciones de ${button.dataset.companyName}`);
}

function toggleCompanyGroup(companyId) {
    const group = document.querySelector(`.company-group[data-company-id="${companyId}"]`);
    if (!group) return;
    const expanded = group.classList.contains('collapsed');
    setSidebarGroupExpanded(group, expanded);
    // Searching temporarily expands matches without replacing the saved browsing preference.
    if (!document.getElementById('sidebar-search-input')?.value.trim()) {
        sidebarCollapsed[companyId] = !expanded;
        try { sessionStorage.setItem(sidebarCollapseKey, JSON.stringify(sidebarCollapsed)); } catch { /* Optional preference. */ }
    }
}

function filterSidebarApps(searchTerm) {
    const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    const term = normalize(searchTerm);
    const target = document.getElementById('apps-nav');
    if (!target) return;
    let visible = 0;
    const items = target.querySelectorAll('[data-app-id]');
    items.forEach(item => {
        const company = item.closest('.company-group')?.querySelector('.company-group-title')?.textContent || '';
        const matches = !term || normalize(`${item.querySelector('span').textContent} ${company}`).includes(term);
        item.hidden = !matches;
        if (matches) visible++;
    });
    target.querySelectorAll('.company-group').forEach(group => {
        group.hidden = Boolean(term) && ![...group.querySelectorAll('[data-app-id]')].some(item => !item.hidden);
        setSidebarGroupExpanded(group, term ? true : !sidebarCollapsed[group.dataset.companyId]);
        if (!term && group.querySelector('[data-app-id].active')) setSidebarGroupExpanded(group, true);
    });
    const clear = document.querySelector('.sidebar-search-clear');
    if (clear) clear.hidden = !searchTerm;
    const status = document.getElementById('sidebar-search-status');
    if (status) status.textContent = term ? (visible ? `${visible} ${visible === 1 ? 'aplicación encontrada' : 'aplicaciones encontradas'}` : 'No hay aplicaciones que coincidan. Prueba otro nombre.') : '';
}

function clearSidebarSearch() {
    const input = document.getElementById('sidebar-search-input');
    input.value = ''; filterSidebarApps(''); input.focus();
}

function sidebarEscapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function escapeHtml(text) { return sidebarEscapeHtml(text); }

function syncSidebarCurrent() {
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        if (link.classList.contains('active')) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    });
}

function closeSidebarUserMenu(restoreFocus = false) {
    const wrap = document.getElementById('sidebar-user');
    wrap?.classList.remove('is-open');
    const menu = document.getElementById('sidebar-user-menu');
    if (menu) menu.hidden = true;
    const trigger = wrap?.querySelector('.sidebar-user-trigger');
    trigger?.setAttribute('aria-expanded', 'false');
    if (restoreFocus) trigger?.focus();
}

function toggleSidebarUserMenu(event) {
    event?.stopPropagation();
    const wrap = document.getElementById('sidebar-user');
    if (!wrap) return;
    if (wrap.classList.contains('is-open')) { closeSidebarUserMenu(); return; }
    wrap.classList.add('is-open');
    const menu = document.getElementById('sidebar-user-menu');
    menu.hidden = false;
    wrap.querySelector('.sidebar-user-trigger').setAttribute('aria-expanded', 'true');
    menu.querySelector('a, button:not([hidden])')?.focus();
}

function openSidebarProfile(event) {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || typeof openProfileModal !== 'function') return true;
    event.preventDefault(); closeSidebarUserMenu();
    if (sidebarMobile.matches) toggleMobileSidebar(false);
    openProfileModal(); return false;
}

function toggleMobileSidebar(force) {
    const sidebar = document.querySelector('.sidebar');
    const button = document.querySelector('.sidebar-mobile-toggle');
    if (!sidebar || !button) return;
    const open = sidebarMobile.matches && (force ?? !sidebar.classList.contains('is-mobile-open'));
    for (const [element, inert] of sidebarInertBefore) element.inert = inert;
    sidebarInertBefore.clear();
    sidebar.classList.toggle('is-mobile-open', open);
    document.body.classList.toggle('sidebar-menu-open', open);
    document.getElementById('sidebar-backdrop').hidden = !open;
    button.setAttribute('aria-expanded', String(open));
    if (open) {
        window.PrismaAssistant?.close();
        if (document.getElementById('inbox-panel')?.classList.contains('open')) toggleInbox();
        sidebar.inert = false;
        sidebar.setAttribute('role', 'dialog'); sidebar.setAttribute('aria-modal', 'true');
        for (const sibling of sidebar.parentElement.children) {
            if (sibling === sidebar || sibling.id === 'sidebar-backdrop' || ['SCRIPT','LINK','STYLE'].includes(sibling.tagName)) continue;
            sidebarInertBefore.set(sibling, sibling.inert); sibling.inert = true;
        }
        sidebar.querySelector('.sidebar-close').focus();
    } else {
        sidebar.inert = sidebarMobile.matches;
        sidebar.removeAttribute('role'); sidebar.removeAttribute('aria-modal'); closeSidebarUserMenu();
        if (sidebarMobile.matches) button.focus({preventScroll:true});
    }
}

sidebarMobile.addEventListener('change', () => toggleMobileSidebar(false));
document.addEventListener('DOMContentLoaded', () => {
    if (location.pathname !== '/' && !location.pathname.endsWith('/index.php')) {
        loadAppsForSidebar();
        if (document.getElementById('pending-count')) updatePendingCount();
    }
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    sidebar.inert = sidebarMobile.matches;
    syncSidebarCurrent();
    new MutationObserver(syncSidebarCurrent).observe(sidebar.querySelector('.sidebar-nav'), {subtree:true, attributes:true, attributeFilter:['class']});
});
document.addEventListener('click', event => {
    if (!event.target.closest('#sidebar-user')) closeSidebarUserMenu();
    if (!event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.target.closest('.sidebar a') && document.querySelector('.sidebar.is-mobile-open')) toggleMobileSidebar(false);
});
document.addEventListener('keydown', event => {
    const sidebar = document.querySelector('.sidebar');
    if (event.key === 'Escape' && document.querySelector('.sidebar-user.is-open')) {
        event.preventDefault(); event.stopImmediatePropagation(); closeSidebarUserMenu(true); return;
    }
    if (!sidebar?.classList.contains('is-mobile-open')) return;
    if ((event.metaKey || event.ctrlKey) && !event.altKey && !event.isComposing && event.key.toLowerCase() === 'j') { toggleMobileSidebar(false); return; }
    if (event.key === 'Escape') {
        event.preventDefault(); event.stopImmediatePropagation(); toggleMobileSidebar(false);
    } else if (event.key === 'Tab') {
        const elements = [...sidebar.querySelectorAll('a, button, input')].filter(el => !el.disabled && el.getClientRects().length);
        const first = elements[0], last = elements.at(-1);
        if (event.shiftKey && (document.activeElement === first || !sidebar.contains(document.activeElement))) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
    // Keep page shortcuts from opening an editor behind the mobile drawer.
    event.stopImmediatePropagation();
}, true);

// Update pending count badge
async function updatePendingCount() {
    try {
        const response = await fetch('/api/pending-approvals.php');
        const data = await response.json();

        if (data.success) {
            const count = data.data.length;
            const badge = document.getElementById('pending-count');

            if (!badge) {
                return;
            }

            badge.hidden = count === 0;
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error updating pending count:', error);
    }
}

// ===================
// Mis tareas: contador de vencidas + hoy (shared across all pages)
// ===================

// Cuenta tareas pendientes vencidas y para hoy, en horario local.
function countTasksDue(tasks, localToday) {
    const counts = { overdue: 0, today: 0 };
    (tasks || []).forEach(task => {
        if (task.is_completed == 1 || !task.due_date) return;
        const [y, m, d] = task.due_date.split('-').map(Number);
        const diff = Math.round((new Date(y, m - 1, d) - localToday) / (1000 * 60 * 60 * 24));
        if (diff < 0) counts.overdue++;
        else if (diff === 0) counts.today++;
    });
    return counts;
}

async function loadTasksCount() {
    const badge = document.getElementById('tasks-count');
    if (!badge) return;

    try {
        const response = await fetch('/api/tasks.php?completed=0&shared=0');
        const data = await response.json();
        if (!data.success) {
            console.error('Error loading tasks count', response.status, data.error);
            return;
        }

        const now = new Date();
        const counts = countTasksDue(data.data, new Date(now.getFullYear(), now.getMonth(), now.getDate()));
        const total = counts.overdue + counts.today;

        badge.hidden = total === 0;
        badge.textContent = total > 99 ? '99+' : total;
        badge.classList.toggle('nav-count--accent', counts.overdue > 0);
        badge.title = `${counts.overdue} ${counts.overdue === 1 ? 'vencida' : 'vencidas'} · ${counts.today} para hoy`;
    } catch (error) {
        console.error('Error loading tasks count:', error);
    }
}

document.addEventListener('DOMContentLoaded', loadTasksCount);

// Tareas que te han asignado y ya se te han enseñado en una lista (Tareas o Mi trabajo):
// se marcan como vistas para que quien las asignó vea el doble check. Una vez por carga.
// No tocan la campana: su aviso sigue sin leer hasta que lo pulses o abras la tarea.
const tasksSeenSent = new Set();

async function putTaskShares(payload) {
    const response = await fetch('/api/task-shares.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await response.json();
    if (!data.success) throw new Error(`${response.status} ${data.error}`);
    return data.data || {};
}

async function markTasksSeen(taskIds) {
    const ids = (taskIds || []).map(Number).filter(id => id > 0 && !tasksSeenSent.has(id));
    if (!ids.length) return;
    ids.forEach(id => tasksSeenSent.add(id));

    try {
        await putTaskShares({ seen_task_ids: ids });
    } catch (error) {
        ids.forEach(id => tasksSeenSent.delete(id)); // se reintenta en el siguiente pintado
        console.error('Error marking tasks as seen', ids, error);
    }
}

// Has abierto una tarea que te han asignado: vista y, además, su aviso de la campana leído.
async function markTaskOpened(taskId) {
    const id = Number(taskId);
    if (!(id > 0)) return;
    tasksSeenSent.add(id);

    try {
        const result = await putTaskShares({ opened_task_ids: [id] });
        if (result.notifications_read > 0) loadNotifications();
    } catch (error) {
        console.error('Error marking task as opened', id, error);
    }
}

// ===================
// Inbox / Notifications (shared across all pages)
// ===================

let inboxNotifications = [];
let inboxReadFilter = 'all';
let inboxTypeFilter = 'all';

const INBOX_PRESENTATION = {
    mention: { iconClass: 'mention', iconName: 'iconoir-at-sign' },
    assignment: { iconClass: 'assignment', iconName: 'iconoir-user-badge-check' },
    completion: { iconClass: 'completion', iconName: 'iconoir-check-circle' },
    status_change: { iconClass: 'status', iconName: 'iconoir-refresh-double' },
    comment: { iconClass: 'comment', iconName: 'iconoir-chat-bubble' },
    note_share: { iconClass: 'note', iconName: 'iconoir-page' },
    task_assignment: { iconClass: 'assignment', iconName: 'iconoir-task-list' },
    task_completion: { iconClass: 'completion', iconName: 'iconoir-check-circle' }
};

const INBOX_EMPTY_MESSAGES = {
    all: 'No hay notificaciones',
    mention: 'Nadie te ha mencionado todavía',
    assignment: 'No tienes asignaciones nuevas',
    comment: 'No hay comentarios nuevos',
    status: 'Sin cambios de estado recientes',
    note_share: 'Nadie te ha compartido notas'
};

function isUnreadNotification(n) {
    return parseInt(n.is_read, 10) === 0;
}

async function loadNotifications() {
    try {
        const response = await fetch('/api/notifications.php');
        const data = await response.json();

        if (data.success) {
            const { notifications, unread_count } = data.data;
            inboxNotifications = notifications || [];

            window.dispatchEvent(new CustomEvent('prisma:notifications-updated', {
                detail: { notifications: inboxNotifications }
            }));

            updateInboxBadge(unread_count);
            updateInboxHeaderState(unread_count);
            updateInboxFilterCounts();

            // Avoid resetting the reading position: skip the re-render when the
            // panel is open and the user has scrolled into the list.
            const panel = document.getElementById('inbox-panel');
            const body = document.getElementById('inbox-body');
            const isReading = panel?.classList.contains('open') && body && body.scrollTop > 0;
            if (!isReading) {
                renderNotifications();
            }
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

function updateInboxBadge(unreadCount) {
    const badge = document.getElementById('inbox-count');
    if (!badge) return;
    badge.hidden = unreadCount <= 0;
    if (unreadCount > 0) {
        badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

function updateInboxHeaderState(unreadCount) {
    const markAllBtn = document.getElementById('inbox-mark-all-btn');
    if (markAllBtn) {
        markAllBtn.hidden = unreadCount === 0;
    }
}

function updateInboxFilterCounts() {
    const unread = inboxNotifications.filter(isUnreadNotification).length;
    const count = document.getElementById('inbox-segment-count');
    if (count) {
        count.textContent = unread;
        count.hidden = unread === 0;
    }
}

function setInboxReadFilter(filter) {
    inboxReadFilter = filter;
    document.querySelectorAll('.inbox-segment').forEach(seg => {
        seg.classList.toggle('active', seg.dataset.readFilter === filter);
    });
    renderNotifications();
}

function setInboxTypeFilter(value) {
    inboxTypeFilter = value;
    renderNotifications();
}

function matchesInboxTypeFilter(n) {
    if (inboxTypeFilter === 'all') return true;
    if (inboxTypeFilter === 'status') {
        return n.type === 'completion' || n.type === 'status_change' || n.type === 'task_completion';
    }
    // "Asignaciones" incluye las mejoras y las tareas que te asignan.
    if (inboxTypeFilter === 'assignment') {
        return n.type === 'assignment' || n.type === 'task_assignment';
    }
    return n.type === inboxTypeFilter;
}

function getFilteredNotifications() {
    return inboxNotifications.filter(n => {
        if (inboxReadFilter === 'unread' && !isUnreadNotification(n)) return false;
        return matchesInboxTypeFilter(n);
    });
}

function getInboxGroupLabel(date) {
    const now = new Date();
    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const dayMs = 24 * 60 * 60 * 1000;
    if (date >= startOfToday) return 'Hoy';
    if (date >= new Date(startOfToday - dayMs)) return 'Ayer';
    if (date >= new Date(startOfToday - 7 * dayMs)) return 'Últimos 7 días';
    return 'Anteriores';
}

function renderNotifications() {
    const body = document.getElementById('inbox-body');
    if (!body) return;

    const notifications = getFilteredNotifications();

    if (notifications.length === 0) {
        const unreadView = inboxReadFilter === 'unread';
        const message = unreadView
            ? 'Estás al día'
            : (INBOX_EMPTY_MESSAGES[inboxTypeFilter] || INBOX_EMPTY_MESSAGES.all);
        body.innerHTML = `
            <div class="inbox-empty">
                <i class="${unreadView ? 'iconoir-check-circle' : 'iconoir-bell'}"></i>
                <p>${message}</p>
            </div>
        `;
        return;
    }

    let html = '';
    let currentGroup = null;

    notifications.forEach(n => {
        const presentation = INBOX_PRESENTATION[n.type] || INBOX_PRESENTATION.comment;
        const createdAt = new Date(n.created_at.replace(' ', 'T'));
        const group = getInboxGroupLabel(createdAt);
        const unread = isUnreadNotification(n);

        if (group !== currentGroup) {
            html += `<div class="inbox-group-label">${group}</div>`;
            currentGroup = group;
        }

        html += `
            <div class="inbox-item ${unread ? 'unread' : ''}" role="button" tabindex="0"
                 onclick="handleNotificationClick(${n.id}, ${n.request_id || 'null'}, ${n.note_id || 'null'}, ${n.task_id || 'null'})"
                 onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();handleNotificationClick(${n.id}, ${n.request_id || 'null'}, ${n.note_id || 'null'}, ${n.task_id || 'null'});}">
                <div class="inbox-item-icon ${presentation.iconClass}">
                    <i class="${presentation.iconName}"></i>
                </div>
                <div class="inbox-item-content">
                    <div class="inbox-item-text">${escapeHtml(n.message)}</div>
                    <div class="inbox-item-meta">
                        <span class="inbox-item-request">${escapeHtml(n.task_id ? n.task_title : n.note_id ? n.note_title : n.request_title)}</span>
                        <time title="${escapeHtml(formatInboxFullDate(createdAt))}">${formatInboxTime(createdAt)}</time>
                    </div>
                </div>
                <button type="button" class="inbox-item-read-btn ${unread ? '' : 'is-read'}"
                        aria-label="${unread ? 'Marcar como leída' : 'Marcar como no leída'}"
                        title="${unread ? 'Marcar como leída' : 'Marcar como no leída'}"
                        onclick="event.stopPropagation(); setNotificationRead(${n.id}, ${unread ? 1 : 0});">
                    <span class="inbox-unread-dot" aria-hidden="true"></span>
                </button>
            </div>
        `;
    });

    body.innerHTML = html;
}

function formatInboxTime(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    if (seconds < 60) return 'ahora';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `hace ${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `hace ${hours}h`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `hace ${days}d`;
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
}

function formatInboxFullDate(date) {
    return date.toLocaleDateString('es-ES', {
        day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
}

function toggleInbox() {
    const panel = document.getElementById('inbox-panel');
    const overlay = document.getElementById('inbox-overlay');

    const trigger = document.getElementById('inbox-nav-btn');
    if (panel.classList.contains('open')) {
        trigger?.setAttribute('aria-expanded', 'false');
        panel.classList.remove('open');
        overlay.classList.remove('open');
        if (trigger) trigger.focus();
    } else {
        window.PrismaAssistant?.close();
        trigger?.setAttribute('aria-expanded', 'true');
        loadNotifications();
        panel.classList.add('open');
        overlay.classList.add('open');
        panel.focus();
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const panel = document.getElementById('inbox-panel');
        if (panel?.classList.contains('open')) {
            toggleInbox();
        }
    }
});

async function setNotificationRead(notificationId, isRead) {
    try {
        // Optimistic local update, then sync with the server
        const notification = inboxNotifications.find(n => parseInt(n.id, 10) === notificationId);
        if (notification) notification.is_read = isRead;
        const unread = inboxNotifications.filter(isUnreadNotification).length;
        updateInboxBadge(unread);
        updateInboxHeaderState(unread);
        updateInboxFilterCounts();
        renderNotifications();

        await fetch('/api/notifications.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: notificationId, is_read: isRead })
        });
    } catch (error) {
        console.error('Error updating notification:', error);
    }
}

async function handleNotificationClick(notificationId, requestId, noteId, taskId) {
    await fetch('/api/notifications.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: notificationId })
    });

    toggleInbox();

    // Aviso de tarea asignada: se abre la tarea (en la misma página si ya estamos en Tareas).
    if (taskId) {
        if (typeof openTaskModal === 'function' && window.location.pathname.includes('tasks.php')) {
            openTaskModal(Number(taskId));
            loadNotifications();
        } else {
            window.location.href = `/tasks.php?task=${taskId}`;
        }
        return;
    }

    // Aviso de nota compartida: se abre en Notas (en la misma página si ya estamos ahí).
    if (noteId) {
        if (typeof selectNote === 'function' && window.location.pathname.includes('notes.php')) {
            selectNote(Number(noteId));
            loadNotifications();
        } else {
            window.location.href = `/notes.php?id=${noteId}`;
        }
        return;
    }

    // Navigate to index with the request if not already on index
    const isIndexPage = window.location.pathname === '/' ||
                        window.location.pathname.includes('index.php');

    if (isIndexPage && typeof openEditRequestModal === 'function') {
        openEditRequestModal(requestId);
        reopenInboxAfterModalClose();
    } else {
        window.location.href = `/index.php?open_request=${requestId}`;
    }

    loadNotifications();
}

// When a request is opened from a notification, bring the panel back after
// the modal closes so the user can continue with the next notification.
function reopenInboxAfterModalClose() {
    const modal = document.getElementById('edit-request-modal');
    if (!modal) return;

    let sawOpen = modal.classList.contains('active');
    const observer = new MutationObserver(() => {
        const isOpen = modal.classList.contains('active');
        if (isOpen) {
            sawOpen = true;
            return;
        }
        if (sawOpen) {
            observer.disconnect();
            const panel = document.getElementById('inbox-panel');
            if (panel && !panel.classList.contains('open')) {
                toggleInbox();
            }
        }
    });
    observer.observe(modal, { attributes: true, attributeFilter: ['class'] });

    // Safety: if the modal never opened (e.g. fetch failed), stop watching
    setTimeout(() => {
        if (!sawOpen) observer.disconnect();
    }, 5000);
}

async function markAllNotificationsRead() {
    try {
        await fetch('/api/notifications.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mark_all_read: true })
        });

        await loadNotifications();

        if (typeof showToast === 'function' && document.getElementById('toast-container')) {
            showToast({
                icon: 'iconoir-check-circle',
                title: 'Notificaciones',
                message: 'Todo marcado como leído'
            }, 'success');
        }
    } catch (error) {
        console.error('Error marking notifications as read:', error);
    }
}

// Load notifications on page load and refresh every 60s
document.addEventListener('DOMContentLoaded', loadNotifications);
setInterval(loadNotifications, 60000);

// Sidebar - Common functionality for all pages
// Note: On index.php, main.js handles the sidebar instead

let sidebarAppsGrouped = [];
let sidebarApps = [];

// Load apps on page load (skip if main.js will handle it)
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on index.php where main.js handles the sidebar
    const isIndexPage = window.location.pathname === '/' || 
                        window.location.pathname.includes('index.php');
    
    // Only load if not on index page (main.js handles it there)
    if (!isIndexPage) {
        loadAppsForSidebar();
    }
});

// Load all apps (grouped by company) for sidebar
async function loadAppsForSidebar() {
    try {
        const response = await fetch('/api/apps.php?grouped=1');
        const data = await response.json();

        if (data.success) {
            sidebarAppsGrouped = data.data;
            // Flatten for compatibility
            sidebarApps = [];
            sidebarAppsGrouped.forEach(group => {
                if (group.apps) {
                    sidebarApps.push(...group.apps);
                }
            });
            renderSidebarApps();
        }
    } catch (error) {
        console.error('Error loading apps for sidebar:', error);
    }
}

// Render apps in sidebar navigation (grouped by company)
function renderSidebarApps() {
    const appsNav = document.getElementById('apps-nav');
    if (!appsNav) return;
    
    let navContent = '';

    if (sidebarAppsGrouped.length > 0) {
        navContent += sidebarAppsGrouped.map(group => `
            <div class="company-group ${sidebarAppsGrouped.length === 1 ? 'single-company' : ''}" data-company-id="${group.id}">
                <div class="company-group-header">
                    <div class="company-nav-item">
                        <div class="company-group-title">
                            <i class="iconoir-building"></i>
                            <span>${escapeHtml(group.name)}</span>
                        </div>
                    </div>
                    <button type="button" class="company-group-toggle-btn" onclick="event.stopPropagation(); toggleCompanyGroup(${group.id})" title="Expandir/contraer apps">
                        <i class="iconoir-nav-arrow-down company-group-toggle"></i>
                    </button>
                </div>
                <div class="company-group-apps" style="max-height: none;">
                    ${group.apps.map(app => `
                        <a href="${getAppLink(app.id)}" class="nav-item" data-app-id="${app.id}">
                            <i class="iconoir-app-window"></i>
                            <span>${escapeHtml(app.name)}</span>
                        </a>
                    `).join('')}
                </div>
            </div>
        `).join('');
    } else {
        navContent += sidebarApps.map(app => `
            <a href="${getAppLink(app.id)}" class="nav-item" data-app-id="${app.id}">
                <i class="iconoir-app-window"></i>
                <span>${escapeHtml(app.name)}</span>
            </a>
        `).join('');
    }

    appsNav.innerHTML = navContent;
}

// Get link for app based on current page
function getAppLink(appId) {
    // Always return direct link - index.php will handle the app parameter
    return `/index.php?app=${appId}`;
}

// Toggle company group collapse/expand
function toggleCompanyGroup(companyId) {
    const group = document.querySelector(`.company-group[data-company-id="${companyId}"]`);
    if (group) {
        group.classList.toggle('collapsed');
    }
}

// Filter sidebar apps by search term
function filterSidebarApps(searchTerm) {
    const term = searchTerm.toLowerCase().trim();
    const appsNav = document.getElementById('apps-nav');
    if (!appsNav) return;

    // Get all app nav items
    const appItems = appsNav.querySelectorAll('.nav-item[data-app-id]');
    
    if (!term) {
        // Show all
        appItems.forEach(item => item.style.display = '');
        
        // Show all company groups
        const companyGroups = appsNav.querySelectorAll('.company-group');
        companyGroups.forEach(group => group.style.display = '');
        return;
    }

    // Filter apps
    let visibleCount = 0;
    const visibleCompanies = new Set();
    
    appItems.forEach(item => {
        const appName = item.querySelector('span').textContent.toLowerCase();
        if (appName.includes(term)) {
            item.style.display = '';
            visibleCount++;
            
            // Mark parent company as visible
            const companyGroup = item.closest('.company-group');
            if (companyGroup) {
                visibleCompanies.add(companyGroup.dataset.companyId);
            }
        } else {
            item.style.display = 'none';
        }
    });

    // Show/hide company groups based on visible apps
    const companyGroups = appsNav.querySelectorAll('.company-group');
    companyGroups.forEach(group => {
        if (visibleCompanies.has(group.dataset.companyId)) {
            group.style.display = '';
            // Expand group when searching
            group.classList.remove('collapsed');
        } else {
            group.style.display = 'none';
        }
    });
}

// Escape HTML helper
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===================
// Menú plegable en móvil (shared across all pages)
// ===================

function toggleMobileSidebar(force) {
    const sidebar = document.querySelector('.sidebar');
    const button = sidebar && sidebar.querySelector('.sidebar-mobile-toggle');
    if (!button) return;

    const open = sidebar.classList.toggle('is-mobile-open', force);
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    button.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    button.querySelector('i').className = open ? 'iconoir-xmark' : 'iconoir-menu';
}

// Al elegir un destino del menú en móvil, se pliega de nuevo.
document.addEventListener('click', (e) => {
    const sidebar = document.querySelector('.sidebar.is-mobile-open');
    if (sidebar && e.target.closest('.sidebar-nav a')) toggleMobileSidebar(false);
});

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
    task_assignment: { iconClass: 'assignment', iconName: 'iconoir-task-list' }
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
        return n.type === 'completion' || n.type === 'status_change';
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

    if (panel.classList.contains('open')) {
        panel.classList.remove('open');
        overlay.classList.remove('open');
        const trigger = document.getElementById('inbox-nav-btn');
        if (trigger) trigger.focus();
    } else {
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

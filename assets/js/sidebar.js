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
    
    // Check if we have multiple companies
    const hasMultipleCompanies = sidebarAppsGrouped.length > 1;
    
    let navContent = '<div class="nav-section-title">Aplicaciones</div>';
    
    if (hasMultipleCompanies) {
        // Render grouped by company
        navContent += sidebarAppsGrouped.map(group => `
            <div class="company-group" data-company-id="${group.id}">
                <div class="company-group-header" onclick="toggleCompanyGroup(${group.id})">
                    <div class="company-group-title">
                        <i class="iconoir-building"></i>
                        <span>${escapeHtml(group.name)}</span>
                    </div>
                    <i class="iconoir-nav-arrow-down company-group-toggle"></i>
                </div>
                <div class="company-group-apps" style="max-height: none;">
                    ${group.apps.map(app => {
                        const appLink = getAppLink(app.id);
                        return `
                            <a href="${appLink}" class="nav-item" data-app-id="${app.id}">
                                <i class="iconoir-app-window"></i>
                                <span>${escapeHtml(app.name)}</span>
                            </a>
                        `;
                    }).join('')}
                </div>
            </div>
        `).join('');
    } else {
        // Single company or flat list
        navContent += sidebarApps.map(app => {
            const appLink = getAppLink(app.id);
            return `
                <a href="${appLink}" class="nav-item" data-app-id="${app.id}">
                    <i class="iconoir-app-window"></i>
                    <span>${escapeHtml(app.name)}</span>
                </a>
            `;
        }).join('');
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
// Inbox / Notifications (shared across all pages)
// ===================

async function loadNotifications() {
    try {
        const response = await fetch('/api/notifications.php');
        const data = await response.json();
        
        if (data.success) {
            const { notifications, unread_count } = data.data;
            
            const badge = document.getElementById('inbox-count');
            if (badge) {
                if (unread_count > 0) {
                    badge.textContent = unread_count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }
            
            const body = document.getElementById('inbox-body');
            if (body) {
                if (notifications.length === 0) {
                    body.innerHTML = `
                        <div class="inbox-empty">
                            <i class="iconoir-bell"></i>
                            <p>No hay notificaciones</p>
                        </div>
                    `;
                } else {
                    body.innerHTML = notifications.map(n => {
                        const iconClass = n.type === 'mention' ? 'mention' : (n.type === 'assignment' ? 'assignment' : 'comment');
                        const iconName = n.type === 'mention' ? 'iconoir-at-sign' : (n.type === 'assignment' ? 'iconoir-user-badge-check' : 'iconoir-chat-bubble');
                        const timeAgo = getTimeAgo(new Date(n.created_at));
                        
                        return `
                            <div class="inbox-item ${n.is_read == 0 ? 'unread' : ''}" 
                                 onclick="handleNotificationClick(${n.id}, ${n.request_id})">
                                <div class="inbox-item-icon ${iconClass}">
                                    <i class="${iconName}"></i>
                                </div>
                                <div class="inbox-item-content">
                                    <div class="inbox-item-text">${escapeHtml(n.message)}</div>
                                    <div class="inbox-item-meta">
                                        ${escapeHtml(n.request_title)} · ${timeAgo}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

function getTimeAgo(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    if (seconds < 60) return 'ahora';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `hace ${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `hace ${hours}h`;
    const days = Math.floor(hours / 24);
    return `hace ${days}d`;
}

function toggleInbox() {
    const panel = document.getElementById('inbox-panel');
    const overlay = document.getElementById('inbox-overlay');
    
    if (panel.classList.contains('open')) {
        panel.classList.remove('open');
        overlay.classList.remove('open');
    } else {
        loadNotifications();
        panel.classList.add('open');
        overlay.classList.add('open');
    }
}

async function handleNotificationClick(notificationId, requestId) {
    await fetch('/api/notifications.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: notificationId })
    });
    
    toggleInbox();
    
    // Navigate to index with the request if not already on index
    const isIndexPage = window.location.pathname === '/' || 
                        window.location.pathname.includes('index.php');
    
    if (isIndexPage && typeof openEditRequestModal === 'function') {
        openEditRequestModal(requestId);
    } else {
        window.location.href = `/index.php?open_request=${requestId}`;
    }
    
    loadNotifications();
}

async function markAllNotificationsRead() {
    try {
        await fetch('/api/notifications.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mark_all_read: true })
        });
        
        loadNotifications();
    } catch (error) {
        console.error('Error marking notifications as read:', error);
    }
}

// Load notifications on page load and refresh every 60s
document.addEventListener('DOMContentLoaded', loadNotifications);
setInterval(loadNotifications, 60000);

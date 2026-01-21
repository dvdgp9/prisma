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
                <div class="company-group-apps" style="max-height: 500px;">
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

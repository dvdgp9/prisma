// Global variables
let apps = [];
let changelogData = [];
let filteredData = [];

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadApps();
    loadChangelog();
});

// Load apps for filter
async function loadApps() {
    try {
        const response = await fetch('/api/apps.php');
        const data = await response.json();

        if (data.success) {
            apps = data.data;
            
            const appFilter = document.getElementById('app-filter');
            apps.forEach(app => {
                const option = document.createElement('option');
                option.value = app.id;
                option.textContent = app.name;
                appFilter.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading apps:', error);
    }
}

// Load changelog
async function loadChangelog() {
    try {
        const appId = document.getElementById('app-filter').value;
        const period = document.getElementById('period-filter').value;

        let url = '/api/changelog.php?';
        const params = [];

        if (appId) params.push(`app_id=${appId}`);
        if (period !== 'all') params.push(`days=${period}`);

        url += params.join('&');

        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            changelogData = data.data;
            filteredData = changelogData;
            renderChangelog();
        }
    } catch (error) {
        console.error('Error loading changelog:', error);
        showError();
    }
}

// Filter changelog by search
function filterChangelog() {
    const searchTerm = document.getElementById('search-filter').value.toLowerCase();

    if (!searchTerm) {
        filteredData = changelogData;
    } else {
        filteredData = changelogData.filter(item => 
            item.title.toLowerCase().includes(searchTerm) ||
            (item.description && item.description.toLowerCase().includes(searchTerm)) ||
            (item.app_name && item.app_name.toLowerCase().includes(searchTerm))
        );
    }

    renderChangelog();
}

// Render changelog
function renderChangelog() {
    const container = document.getElementById('changelog-content');

    if (filteredData.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="iconoir-journal-page"></i>
                <h3>No hay mejoras completadas</h3>
                <p>No se encontraron mejoras completadas en el período seleccionado.</p>
            </div>
        `;
        return;
    }

    // Group by date
    const grouped = {};
    filteredData.forEach(item => {
        const date = item.completion_date || item.updated_at.split(' ')[0];
        if (!grouped[date]) {
            grouped[date] = [];
        }
        grouped[date].push(item);
    });

    // Sort dates descending
    const sortedDates = Object.keys(grouped).sort((a, b) => b.localeCompare(a));

    // Render
    let html = '';
    sortedDates.forEach(date => {
        const items = grouped[date];
        const dateObj = new Date(date + 'T00:00:00');
        const day = dateObj.getDate();
        const month = dateObj.toLocaleDateString('es-ES', { month: 'short' });
        const year = dateObj.getFullYear();

        html += `
            <div class="timeline-item">
                <div class="timeline-date">
                    <div class="timeline-date-day">${day}</div>
                    <div class="timeline-date-month">${month} ${year}</div>
                </div>
                <div class="timeline-content">
        `;

        items.forEach(item => {
            html += createChangelogEntry(item);
        });

        html += `
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// Create changelog entry
function createChangelogEntry(item) {
    const priorityLabels = {
        'critical': 'CRÍTICA',
        'high': 'ALTA',
        'medium': 'MEDIA',
        'low': 'BAJA'
    };

    return `
        <div class="changelog-entry">
            <div class="changelog-entry-header">
                <div class="priority-badge priority-${item.priority}" style="font-size: 0.75rem; padding: 2px 6px;">
                    ${priorityLabels[item.priority] || item.priority}
                </div>
                <h3 class="changelog-entry-title">${escapeHtml(item.title)}</h3>
            </div>
            ${item.description ? `
                <p class="changelog-entry-description">${escapeHtml(item.description)}</p>
            ` : ''}
            <div class="changelog-entry-meta">
                ${item.app_name ? `
                    <span class="app-badge-footer" style="font-size: 0.75rem;">
                        ${escapeHtml(item.app_name)}
                    </span>
                ` : ''}
                <span class="text-small text-muted">
                    <i class="iconoir-user"></i>
                    ${escapeHtml(item.creator_full_name || item.creator_username || 'Usuario')}
                </span>
            </div>
        </div>
    `;
}

// Export to Markdown
function exportToMarkdown() {
    if (filteredData.length === 0) {
        alert('No hay datos para exportar');
        return;
    }

    const appFilter = document.getElementById('app-filter');
    const selectedApp = appFilter.options[appFilter.selectedIndex].text;
    const periodFilter = document.getElementById('period-filter');
    const selectedPeriod = periodFilter.options[periodFilter.selectedIndex].text;

    // Build markdown
    let markdown = `# Changelog`;
    
    if (selectedApp !== 'Todas las apps') {
        markdown += ` - ${selectedApp}`;
    }
    
    markdown += `\n## ${selectedPeriod}\n\n`;

    // Group by date
    const grouped = {};
    filteredData.forEach(item => {
        const date = item.completion_date || item.updated_at.split(' ')[0];
        if (!grouped[date]) {
            grouped[date] = [];
        }
        grouped[date].push(item);
    });

    // Sort dates descending
    const sortedDates = Object.keys(grouped).sort((a, b) => b.localeCompare(a));

    // Generate markdown
    sortedDates.forEach(date => {
        const dateObj = new Date(date + 'T00:00:00');
        const formattedDate = dateObj.toLocaleDateString('es-ES', { 
            day: '2-digit', 
            month: 'long', 
            year: 'numeric' 
        });

        markdown += `### ${formattedDate}\n\n`;

        const items = grouped[date];
        items.forEach(item => {
            const priorityLabels = {
                'critical': 'CRÍTICA',
                'high': 'ALTA',
                'medium': 'MEDIA',
                'low': 'BAJA'
            };

            const priority = priorityLabels[item.priority] || item.priority.toUpperCase();
            markdown += `- **[${priority}]** ${item.title}`;
            
            if (item.app_name && selectedApp === 'Todas las apps') {
                markdown += ` _(${item.app_name})_`;
            }
            
            markdown += '\n';
            
            if (item.description) {
                // Indent description
                const description = item.description.trim().replace(/\n/g, '\n  ');
                markdown += `  - ${description}\n`;
            }
            
            markdown += '\n';
        });
    });

    // Add footer
    markdown += `\n---\n`;
    markdown += `_Generado el ${new Date().toLocaleDateString('es-ES', { 
        day: '2-digit', 
        month: 'long', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })}_\n`;
    markdown += `_Total de mejoras: ${filteredData.length}_\n`;

    // Download file
    const blob = new Blob([markdown], { type: 'text/markdown;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    
    const filename = `changelog_${selectedApp.toLowerCase().replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.md`;
    link.href = url;
    link.download = filename;
    link.click();
    
    URL.revokeObjectURL(url);

    // Show toast notification
    showToast({
        title: 'Exportado correctamente',
        message: `Se ha descargado ${filename}`,
        icon: 'iconoir-check'
    });
}

// Show error
function showError() {
    const container = document.getElementById('changelog-content');
    container.innerHTML = `
        <div class="empty-state">
            <i class="iconoir-warning-triangle"></i>
            <h3>Error al cargar</h3>
            <p>No se pudo cargar el changelog. Por favor, intenta de nuevo.</p>
            <button class="btn btn-primary" onclick="loadChangelog()">Reintentar</button>
        </div>
    `;
}

// Toast notification
function showToast(config, type = '') {
    // Create container if it doesn't exist
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = `
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        `;
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.style.cssText = `
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        display: flex;
        gap: 0.75rem;
        align-items: center;
        min-width: 300px;
        animation: slideIn 0.3s ease;
    `;

    toast.innerHTML = `
        <div style="color: var(--primary-color); font-size: 1.25rem;">
            <i class="${config.icon}"></i>
        </div>
        <div style="flex: 1;">
            <div style="font-weight: 600; margin-bottom: 0.25rem;">${config.title}</div>
            <div style="font-size: 0.875rem; color: var(--text-secondary);">${config.message}</div>
        </div>
    `;

    container.appendChild(toast);

    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (container.contains(toast)) {
                container.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }
`;
document.head.appendChild(style);

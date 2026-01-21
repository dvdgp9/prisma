// Global variables
let changelogData = [];
let apps = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', async () => {
    await loadApps();
    await loadChangelog();

    // Set default date range for custom filter
    const today = new Date();
    const monthAgo = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());

    document.getElementById('date-to').value = today.toISOString().split('T')[0];
    document.getElementById('date-from').value = monthAgo.toISOString().split('T')[0];
});

// Load apps for filter (sidebar is handled by sidebar.js)
async function loadApps() {
    try {
        const response = await fetch('/api/apps.php');
        const data = await response.json();

        if (data.success) {
            apps = data.data;

            // Populate app filter dropdown
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

// Load changelog data
async function loadChangelog() {
    try {
        const appId = document.getElementById('app-filter').value;
        const period = document.getElementById('period-filter').value;

        let url = '/api/changelog.php?';
        const params = [];

        if (appId) {
            params.push(`app_id=${appId}`);
        }

        if (period === 'custom') {
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;

            if (dateFrom && dateTo) {
                params.push(`date_from=${dateFrom}`);
                params.push(`date_to=${dateTo}`);
            }
        } else {
            params.push(`days=${period}`);
        }

        url += params.join('&');

        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            changelogData = data.data;
            renderChangelog();
        } else {
            showError(data.error || 'Error al cargar el changelog');
        }
    } catch (error) {
        console.error('Error loading changelog:', error);
        showError('Error al cargar el changelog');
    }
}

// Render changelog
function renderChangelog() {
    const container = document.getElementById('changelog-container');
    const groupBy = document.getElementById('group-by').value;
    const searchTerm = document.getElementById('search-input').value.toLowerCase();

    // Filter by search
    let filteredData = changelogData;
    if (searchTerm) {
        filteredData = changelogData.filter(item =>
            item.title.toLowerCase().includes(searchTerm) ||
            (item.description && item.description.toLowerCase().includes(searchTerm)) ||
            item.app_name.toLowerCase().includes(searchTerm)
        );
    }

    if (filteredData.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                <i class="iconoir-info-circle" style="font-size: 3rem; opacity: 0.3;"></i>
                <h3>No hay cambios completados en este período</h3>
                <p>Cambia los filtros para ver más resultados.</p>
            </div>
        `;
        return;
    }

    // Group data by period
    const grouped = groupDataByPeriod(filteredData, groupBy);

    // Render HTML
    let html = '<div class="changelog-timeline">';

    Object.keys(grouped).forEach(dateKey => {
        const items = grouped[dateKey];

        html += `
            <div class="changelog-group">
                <div class="changelog-date-header">
                    <div class="date-badge">
                        <i class="iconoir-calendar"></i>
                        ${formatGroupDate(dateKey, groupBy)}
                    </div>
                    <div class="changelog-count">${items.length} ${items.length === 1 ? 'cambio' : 'cambios'}</div>
                </div>
                <div class="changelog-items">
        `;

        items.forEach(item => {
            html += createChangelogItem(item);
        });

        html += `
                </div>
            </div>
        `;
    });

    html += '</div>';

    container.innerHTML = html;
}

// Group data by period
function groupDataByPeriod(data, groupBy) {
    const grouped = {};

    data.forEach(item => {
        if (!item.completed_at) return;

        const date = new Date(item.completed_at);
        let key;

        if (groupBy === 'day') {
            key = date.toISOString().split('T')[0]; // YYYY-MM-DD
        } else if (groupBy === 'week') {
            const weekStart = getWeekStart(date);
            key = weekStart.toISOString().split('T')[0];
        } else if (groupBy === 'month') {
            key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        }

        if (!grouped[key]) {
            grouped[key] = [];
        }
        grouped[key].push(item);
    });

    return grouped;
}

// Get week start (Monday)
function getWeekStart(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is Sunday
    return new Date(d.setDate(diff));
}

// Format group date header
function formatGroupDate(dateKey, groupBy) {
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    if (groupBy === 'day') {
        const date = new Date(dateKey);
        return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
    } else if (groupBy === 'week') {
        const weekStart = new Date(dateKey);
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekEnd.getDate() + 6);

        return `Semana del ${weekStart.getDate()} ${months[weekStart.getMonth()]} - ${weekEnd.getDate()} ${months[weekEnd.getMonth()]} ${weekEnd.getFullYear()}`;
    } else if (groupBy === 'month') {
        const [year, month] = dateKey.split('-');
        return `${months[parseInt(month) - 1]} ${year}`;
    }
}

// Create a single changelog item
function createChangelogItem(item) {
    const priorityLabels = {
        'critical': 'CRÍTICA',
        'high': 'ALTA',
        'medium': 'MEDIA',
        'low': 'BAJA'
    };

    const completedDate = new Date(item.completed_at);
    const formattedDate = completedDate.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });

    return `
        <div class="changelog-item">
            <div class="changelog-item-header">
                <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                    <span class="priority-badge priority-${item.priority}">
                        ${priorityLabels[item.priority]}
                    </span>
                    <h4 class="changelog-item-title">${escapeHtml(item.app_name)} - ${escapeHtml(item.title)}</h4>
                </div>
            </div>
            ${item.description ? `
                <p class="changelog-item-description">${escapeHtml(item.description)}</p>
            ` : ''}
            <div class="changelog-item-meta">
                <span class="text-small text-muted">
                    <i class="iconoir-check-circle"></i>
                    Completado: ${formattedDate}
                </span>
                ${item.requester_name ? `
                    <span class="text-small text-muted">
                        <i class="iconoir-user"></i>
                        Solicitado por: ${escapeHtml(item.requester_name)}
                    </span>
                ` : ''}
            </div>
        </div>
    `;
}

// Handle period filter change
function handlePeriodChange() {
    const period = document.getElementById('period-filter').value;
    const customDatesGroup = document.getElementById('custom-dates-group');
    const customDatesGroupTo = document.getElementById('custom-dates-group-to');

    if (period === 'custom') {
        customDatesGroup.style.display = 'block';
        customDatesGroupTo.style.display = 'block';
    } else {
        customDatesGroup.style.display = 'none';
        customDatesGroupTo.style.display = 'none';
        loadChangelog();
    }
}

// Export to Markdown
function exportToMarkdown() {
    const markdown = generateMarkdown();

    // Create download
    const blob = new Blob([markdown], { type: 'text/markdown' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;

    const appFilter = document.getElementById('app-filter');
    const appName = appFilter.selectedIndex > 0
        ? apps.find(a => a.id == appFilter.value)?.name
        : 'Todas';

    const today = new Date().toISOString().split('T')[0];
    a.download = `changelog-${appName.replace(/\s+/g, '_')}-${today}.md`;

    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    showToast({
        title: 'Exportado',
        message: 'Changelog descargado como Markdown',
        icon: 'iconoir-check-circle'
    }, 'toast-completed');
}

// Copy to clipboard
async function copyToClipboard() {
    const markdown = generateMarkdown();

    try {
        await navigator.clipboard.writeText(markdown);
        showToast({
            title: 'Copiado',
            message: 'Changelog copiado al portapapeles',
            icon: 'iconoir-check-circle'
        }, 'toast-completed');
    } catch (err) {
        console.error('Error copying to clipboard:', err);
        showToast({
            title: 'Error',
            message: 'No se pudo copiar al portapapeles',
            icon: 'iconoir-xmark'
        }, 'toast-discarded');
    }
}

// Generate Markdown content
function generateMarkdown() {
    const appFilter = document.getElementById('app-filter');
    const appName = appFilter.selectedIndex > 0
        ? apps.find(a => a.id == appFilter.value)?.name
        : 'Todas las aplicaciones';

    const period = document.getElementById('period-filter').value;
    const groupBy = document.getElementById('group-by').value;
    const searchTerm = document.getElementById('search-input').value;

    // Filter data
    let filteredData = changelogData;
    if (searchTerm) {
        const searchLower = searchTerm.toLowerCase();
        filteredData = changelogData.filter(item =>
            item.title.toLowerCase().includes(searchLower) ||
            (item.description && item.description.toLowerCase().includes(searchLower)) ||
            item.app_name.toLowerCase().includes(searchLower)
        );
    }

    // Generate header
    let markdown = `# Changelog - ${appName}\n\n`;

    if (period === 'custom') {
        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;
        markdown += `**Período:** ${formatDate(dateFrom)} - ${formatDate(dateTo)}\n\n`;
    } else {
        const periodLabels = {
            '7': 'Última semana',
            '30': 'Último mes',
            '90': 'Último trimestre',
            '365': 'Último año'
        };
        markdown += `**Período:** ${periodLabels[period]}\n\n`;
    }

    markdown += `**Total de cambios:** ${filteredData.length}\n\n`;
    markdown += `---\n\n`;

    // Group and format data
    const grouped = groupDataByPeriod(filteredData, groupBy);

    Object.keys(grouped).forEach(dateKey => {
        const items = grouped[dateKey];

        markdown += `## ${formatGroupDate(dateKey, groupBy)}\n\n`;

        items.forEach(item => {
            const priorityLabels = {
                'critical': 'CRÍTICA',
                'high': 'ALTA',
                'medium': 'MEDIA',
                'low': 'BAJA'
            };

            markdown += `- **[${priorityLabels[item.priority]}]** ${item.title}`;

            if (appFilter.value === '') {
                markdown += ` _(${item.app_name})_`;
            }

            markdown += `\n`;

            if (item.description) {
                markdown += `  - ${item.description.replace(/\n/g, '\n  ')}\n`;
            }

            if (item.requester_name) {
                markdown += `  - Solicitado por: ${item.requester_name}\n`;
            }

            markdown += `\n`;
        });

        markdown += `\n`;
    });

    return markdown;
}

// Format date for display
function formatDate(dateStr) {
    const date = new Date(dateStr);
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Show error
function showError(message) {
    const container = document.getElementById('changelog-container');
    container.innerHTML = `
        <div style="text-align: center; padding: 3rem; color: var(--error-color);">
            <i class="iconoir-warning-circle" style="font-size: 3rem;"></i>
            <h3>Error</h3>
            <p>${escapeHtml(message)}</p>
        </div>
    `;
}

// Toast notification system (reused from main.js)
function showToast(config, type = '') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    toast.innerHTML = `
        <div class="toast-icon">
            <i class="${config.icon}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${config.title}</div>
            <div class="toast-message">${config.message}</div>
        </div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('toast-hide');
        setTimeout(() => {
            container.removeChild(toast);
        }, 300);
    }, 3000);
}

// Profile modal functions (reused from main.js)
function openProfileModal() {
    fetch('/api/profile.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const profile = data.data;
                document.getElementById('profile-username').value = profile.username;
                document.getElementById('profile-fullname').value = profile.full_name || '';
                document.getElementById('profile-email').value = profile.email || '';
                document.getElementById('profile-password').value = '';

                const roleLabels = {
                    'superadmin': 'Superadministrador',
                    'admin': 'Administrador',
                    'user': 'Usuario'
                };
                document.getElementById('profile-role').textContent = roleLabels[profile.role] || profile.role;
                document.getElementById('profile-company').textContent = profile.company_name || 'Sin asignar';

                document.getElementById('profile-modal').classList.add('active');
            }
        })
        .catch(err => console.error('Error loading profile:', err));
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function submitProfile(event) {
    event.preventDefault();

    const data = {
        username: document.getElementById('profile-username').value,
        full_name: document.getElementById('profile-fullname').value,
        email: document.getElementById('profile-email').value
    };

    const password = document.getElementById('profile-password').value;
    if (password) {
        data.password = password;
    }

    fetch('/api/profile.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                closeModal('profile-modal');
                location.reload();
            } else {
                alert(result.error || 'Error al actualizar el perfil');
            }
        })
        .catch(err => {
            console.error('Error updating profile:', err);
            alert('Error al actualizar el perfil');
        });
}

// Close modal on outside click
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

// Prisma Manage Apps - JavaScript

let apps = [];
let editingAppId = null;

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    loadUserApps(); // Load apps for sidebar
    loadApps();
});

// Load user apps for sidebar navigation
async function loadUserApps() {
    try {
        const response = await fetch('/api/apps.php');
        const data = await response.json();

        if (data.success) {
            renderAppsNav(data.data);
        }
    } catch (error) {
        console.error('Error loading user apps:', error);
    }
}

// Render apps in sidebar navigation
function renderAppsNav(userApps) {
    const appsNav = document.getElementById('apps-nav');
    if (!appsNav) return;

    const navItems = userApps.map(app => `
        <a href="/index.php?app=${app.id}" class="nav-item">
            <i class="iconoir-app-window"></i>
            <span>${escapeHtml(app.name)}</span>
        </a>
    `).join('');

    appsNav.innerHTML = `
        <div class="nav-section-title">Aplicaciones</div>
        ${navItems}
    `;
}

// Load apps for tablell apps
async function loadApps() {
    try {
        const response = await fetch('/api/apps.php');
        const data = await response.json();

        if (data.success) {
            apps = data.data;
            renderApps();
        }
    } catch (error) {
        console.error('Error loading apps:', error);
    }
}

// Render apps grid
function renderApps() {
    const grid = document.getElementById('apps-grid');

    if (apps.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                <h3>No hay aplicaciones aún</h3>
                <p>Crea la primera aplicación usando el botón de arriba.</p>
            </div>
        `;
        return;
    }

    const companyName = document.body.dataset.companyName || 'tu-empresa';

    grid.innerHTML = apps.map(app => {
        const appUrl = `${window.location.origin}/solicitud.php?empresa=${encodeURIComponent(companyName)}&app_id=${app.id}`;
        
        return `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">${escapeHtml(app.name)}</h3>
                    <div style="display: flex; gap: var(--spacing-xs);">
                        <button class="btn btn-sm btn-outline" onclick="copyAppUrl('${appUrl}')" title="Copiar enlace público">
                            <i class="iconoir-share-ios"></i>
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="openEditAppModal(${app.id})">
                            Editar
                        </button>
                    </div>
                </div>
                
                ${app.description ? `
                    <p class="card-description">${escapeHtml(app.description)}</p>
                ` : `
                    <p class="card-description text-muted">Sin descripción</p>
                `}
                
                <div class="card-footer">
                    <span class="text-small text-muted">
                        Creada: ${new Date(app.created_at).toLocaleDateString('es-ES')}
                    </span>
                </div>
            </div>
        `;
    }).join('');
}

function copyAppUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        if (typeof showToast === 'function') {
            showToast({
                title: 'Enlace copiado',
                message: 'El enlace específico de la aplicación se ha copiado al portapapeles',
                icon: 'iconoir-check'
            }, 'toast-completed');
        } else {
            alert('Enlace copiado al portapapeles');
        }
    }).catch(err => {
        console.error('Error copying:', err);
        alert('No se pudo copiar el enlace.');
    });
}

// Open new app modal
function openNewAppModal() {
    document.getElementById('new-app-modal').classList.add('active');
}

// Open edit app modal
function openEditAppModal(appId) {
    const app = apps.find(a => a.id === appId);
    if (!app) return;

    document.getElementById('edit-app-id').value = app.id;
    document.getElementById('edit-app-name').value = app.name;
    document.getElementById('edit-app-description').value = app.description || '';

    document.getElementById('edit-app-modal').classList.add('active');
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    if (modalId === 'new-app-modal') {
        document.getElementById('new-app-form').reset();
    }
}

// Submit new app
async function submitNewApp(event) {
    event.preventDefault();

    const name = document.getElementById('app-name').value;
    const description = document.getElementById('app-description').value;

    try {
        const response = await fetch('/api/apps.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: name,
                description: description
            })
        });

        const data = await response.json();

        if (data.success) {
            closeModal('new-app-modal');
            loadApps();
        } else {
            alert(data.error || 'Error al crear la aplicación');
        }
    } catch (error) {
        console.error('Error creating app:', error);
        alert('Error al crear la aplicación');
    }
}

// Submit edit app
async function submitEditApp(event) {
    event.preventDefault();

    const id = document.getElementById('edit-app-id').value;
    const name = document.getElementById('edit-app-name').value;
    const description = document.getElementById('edit-app-description').value;

    try {
        const response = await fetch('/api/apps.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: parseInt(id),
                name: name,
                description: description
            })
        });

        const data = await response.json();

        if (data.success) {
            closeModal('edit-app-modal');
            loadApps();
        } else {
            alert(data.error || 'Error al actualizar la aplicación');
        }
    } catch (error) {
        console.error('Error updating app:', error);
        alert('Error al actualizar la aplicación');
    }
}

// Delete app
async function deleteApp() {
    const id = document.getElementById('edit-app-id').value;

    if (!confirm('¿Estás seguro de que quieres eliminar esta aplicación? Esto eliminará todas las peticiones asociadas.')) {
        return;
    }

    try {
        const response = await fetch('/api/apps.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id) })
        });

        const data = await response.json();

        if (data.success) {
            closeModal('edit-app-modal');
            loadApps();
        } else {
            alert(data.error || 'Error al eliminar la aplicación');
        }
    } catch (error) {
        console.error('Error deleting app:', error);
        alert('Error al eliminar la aplicación');
    }
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal on outside click
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

// Prisma Admin Panel - JavaScript

let companies = [];
let users = [];
let apps = [];
let editingCompanyId = null;
let editingUserId = null;

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    loadCompanies();
    loadUsers();
    loadApps();
});

// Tab switching
function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    event.target.closest('.tab').classList.add('active');

    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(tabName + '-tab').classList.add('active');
}

// ========== COMPANIES ==========

async function loadCompanies() {
    try {
        const response = await fetch('/api/companies.php');
        const data = await response.json();

        if (data.success) {
            companies = data.data;
            renderCompanies();
            populateCompanySelects();
        }
    } catch (error) {
        console.error('Error loading companies:', error);
    }
}

function renderCompanies() {
    const tbody = document.querySelector('#companies-table tbody');

    if (companies.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">No hay empresas registradas</td></tr>';
        return;
    }

    tbody.innerHTML = companies.map(company => `
        <tr>
            <td style="font-weight: var(--font-weight-semibold); color: var(--text-primary);">
                ${escapeHtml(company.name)}
            </td>
            <td>${escapeHtml(company.description || '-')}</td>
            <td>
                <span class="badge badge-user">${company.user_count} usuarios</span>
            </td>
            <td>
                <span class="badge badge-admin">${company.admin_count} admins</span>
            </td>
            <td>
                <div class="actions-cell">
                    <button class="btn btn-sm btn-outline" onclick="editCompany(${company.id})" title="Editar">
                        <i class="iconoir-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="deleteCompany(${company.id})" title="Eliminar" style="color: var(--secondary);">
                        <i class="iconoir-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function openNewCompanyModal() {
    editingCompanyId = null;
    document.getElementById('company-modal-title').textContent = 'Nueva Empresa';
    document.getElementById('company-form').reset();
    document.getElementById('company-id').value = '';
    document.getElementById('company-modal').classList.add('active');
}

function editCompany(id) {
    const company = companies.find(c => c.id == id);
    if (!company) return;

    editingCompanyId = id;
    document.getElementById('company-modal-title').textContent = 'Editar Empresa';
    document.getElementById('company-id').value = company.id;
    document.getElementById('company-name').value = company.name;
    document.getElementById('company-description').value = company.description || '';
    document.getElementById('company-modal').classList.add('active');
}

async function submitCompany(event) {
    event.preventDefault();

    const id = document.getElementById('company-id').value;
    const data = {
        name: document.getElementById('company-name').value,
        description: document.getElementById('company-description').value
    };

    const url = '/api/companies.php';
    const method = id ? 'PUT' : 'POST';

    if (id) {
        data.id = parseInt(id);
    }

    try {
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            closeModal('company-modal');
            loadCompanies();
        } else {
            alert(result.error || 'Error al guardar la empresa');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar la empresa');
    }
}

async function deleteCompany(id) {
    if (!confirm('¿Estás seguro? Esto eliminará todos los usuarios de esta empresa.')) {
        return;
    }

    try {
        const response = await fetch('/api/companies.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const result = await response.json();

        if (result.success) {
            loadCompanies();
        } else {
            alert(result.error || 'Error al eliminar la empresa');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar la empresa');
    }
}

// ========== USERS ==========

async function loadUsers() {
    try {
        const response = await fetch('/api/users.php');
        const data = await response.json();

        if (data.success) {
            users = data.data;
            renderUsers();
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

function renderUsers() {
    const tbody = document.querySelector('#users-table tbody');

    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">No hay usuarios registrados</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(user => `
        <tr>
            <td style="font-weight: var(--font-weight-semibold); color: var(--text-primary);">
                ${escapeHtml(user.username)}
            </td>
            <td>${escapeHtml(user.full_name || '-')}</td>
            <td>${escapeHtml(user.email || '-')}</td>
            <td>${escapeHtml(user.company_name || 'Sin empresa')}</td>
            <td>
                <span class="badge badge-${user.role}">${getRoleLabel(user.role)}</span>
            </td>
            <td>
                <span class="badge badge-${user.is_active ? 'active' : 'inactive'}">
                    ${user.is_active ? 'Activo' : 'Inactivo'}
                </span>
            </td>
            <td>
                <div class="actions-cell">
                    <button class="btn btn-sm btn-outline" onclick="editUser(${user.id})" title="Editar">
                        <i class="iconoir-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="deleteUser(${user.id})" title="Eliminar" style="color: var(--secondary);">
                        <i class="iconoir-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function openNewUserModal() {
    editingUserId = null;
    document.getElementById('user-modal-title').textContent = 'Nuevo Usuario';
    document.getElementById('user-form').reset();
    document.getElementById('user-id').value = '';
    document.getElementById('user-password').required = true;
    document.getElementById('password-optional').style.display = 'none';
    document.getElementById('user-modal').classList.add('active');
}

function editUser(id) {
    const user = users.find(u => u.id == id);
    if (!user) return;

    editingUserId = id;
    document.getElementById('user-modal-title').textContent = 'Editar Usuario';
    document.getElementById('user-id').value = user.id;
    document.getElementById('user-username').value = user.username;
    document.getElementById('user-fullname').value = user.full_name || '';
    document.getElementById('user-email').value = user.email || '';
    document.getElementById('user-company').value = user.company_id || '';
    document.getElementById('user-role').value = user.role;
    document.getElementById('user-active').checked = user.is_active == 1;
    document.getElementById('user-password').value = '';
    document.getElementById('user-password').required = false;
    document.getElementById('password-optional').style.display = 'inline';
    document.getElementById('user-modal').classList.add('active');
}

async function submitUser(event) {
    event.preventDefault();

    const id = document.getElementById('user-id').value;
    const data = {
        username: document.getElementById('user-username').value,
        full_name: document.getElementById('user-fullname').value,
        email: document.getElementById('user-email').value,
        company_id: parseInt(document.getElementById('user-company').value),
        role: document.getElementById('user-role').value,
        is_active: document.getElementById('user-active').checked
    };

    const password = document.getElementById('user-password').value;
    if (password) {
        data.password = password;
    }

    const url = '/api/users.php';
    const method = id ? 'PUT' : 'POST';

    if (id) {
        data.id = parseInt(id);
    }

    try {
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            closeModal('user-modal');
            loadUsers();
        } else {
            alert(result.error || 'Error al guardar el usuario');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar el usuario');
    }
}

async function deleteUser(id) {
    if (!confirm('¿Estás seguro de que quieres eliminar este usuario?')) {
        return;
    }

    try {
        const response = await fetch('/api/users.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const result = await response.json();

        if (result.success) {
            loadUsers();
        } else {
            alert(result.error || 'Error al eliminar el usuario');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar el usuario');
    }
}

// ========== APPS ==========

async function loadApps() {
    try {
        const response = await fetch('/api/apps.php?with_company=1');
        const data = await response.json();

        if (data.success) {
            apps = data.data;
            renderApps();
        }
    } catch (error) {
        console.error('Error loading apps:', error);
    }
}

function renderApps() {
    const tbody = document.querySelector('#apps-table tbody');

    if (apps.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">No hay aplicaciones registradas</td></tr>';
        return;
    }

    tbody.innerHTML = apps.map(app => {
        const company = companies.find(c => c.id == app.company_id);
        return `
            <tr>
                <td style="font-weight: var(--font-weight-semibold); color: var(--text-primary);">
                    <i class="iconoir-app-window" style="margin-right: 0.5rem;"></i>
                    ${escapeHtml(app.name)}
                </td>
                <td>
                    ${company ? `<span class="badge badge-admin">${escapeHtml(company.name)}</span>` : '<span class="badge badge-inactive">Sin asignar</span>'}
                </td>
                <td>${escapeHtml(app.description || '-')}</td>
                <td>
                    <span class="badge ${app.is_active ? 'badge-active' : 'badge-inactive'}">
                        ${app.is_active ? 'Activa' : 'Inactiva'}
                    </span>
                </td>
                <td>${new Date(app.created_at).toLocaleDateString('es-ES')}</td>
                <td>
                    <div class="actions-cell">
                        <button class="btn btn-sm btn-outline" onclick="editApp(${app.id})" title="Editar">
                            <i class="iconoir-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="deleteApp(${app.id})" title="Eliminar" style="color: var(--secondary);">
                            <i class="iconoir-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function openNewAppModal() {
    document.getElementById('app-modal-title').textContent = 'Nueva Aplicación';
    document.getElementById('app-form').reset();
    document.getElementById('app-id').value = '';
    document.getElementById('app-active').checked = true;
    populateAppCompanySelect();
    document.getElementById('app-modal').classList.add('active');
}

function editApp(id) {
    const app = apps.find(a => a.id == id);
    if (!app) return;

    document.getElementById('app-modal-title').textContent = 'Editar Aplicación';
    document.getElementById('app-id').value = app.id;
    document.getElementById('app-name').value = app.name;
    document.getElementById('app-description').value = app.description || '';
    document.getElementById('app-company').value = app.company_id || '';
    document.getElementById('app-active').checked = app.is_active == 1;
    populateAppCompanySelect();
    document.getElementById('app-modal').classList.add('active');
}

async function submitApp(event) {
    event.preventDefault();

    const id = document.getElementById('app-id').value;
    const data = {
        name: document.getElementById('app-name').value,
        description: document.getElementById('app-description').value,
        company_id: parseInt(document.getElementById('app-company').value),
        is_active: document.getElementById('app-active').checked
    };

    const url = '/api/apps.php';
    const method = id ? 'PUT' : 'POST';

    if (id) {
        data.id = parseInt(id);
    }

    try {
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            closeModal('app-modal');
            loadApps();
        } else {
            alert(result.error || 'Error al guardar la aplicación');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar la aplicación');
    }
}

async function deleteApp(id) {
    if (!confirm('¿Estás seguro? Esto eliminará todas las peticiones asociadas a esta app.')) {
        return;
    }

    try {
        const response = await fetch('/api/apps.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const result = await response.json();

        if (result.success) {
            loadApps();
        } else {
            alert(result.error || 'Error al eliminar la aplicación');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar la aplicación');
    }
}

function populateAppCompanySelect() {
    const select = document.getElementById('app-company');
    select.innerHTML = '<option value="">Selecciona una empresa</option>' +
        companies.map(company => `<option value="${company.id}">${escapeHtml(company.name)}</option>`).join('');
}

// ========== HELPERS ==========

function populateCompanySelects() {
    // For users
    const userSelect = document.getElementById('user-company');
    userSelect.innerHTML = '<option value="">Selecciona una empresa</option>' +
        companies.map(company => `<option value="${company.id}">${escapeHtml(company.name)}</option>`).join('');

    // For apps
    populateAppCompanySelect();
}

function getRoleLabel(role) {
    const labels = {
        'superadmin': 'Superadmin',
        'admin': 'Admin',
        'user': 'Usuario'
    };
    return labels[role] || role;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modal on outside click
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

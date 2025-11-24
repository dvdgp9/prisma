// Prisma Dashboard - Main JavaScript

let currentView = 'global';
let currentAppId = null;
let apps = [];
let selectedFiles = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    loadApps();
    loadRequests();
    setupFileUpload();
});

// Load all apps
async function loadApps() {
    try {
        const response = await fetch('/api/apps.php');
        const data = await response.json();

        if (data.success) {
            apps = data.data;
            renderAppsNav();
            populateAppSelects();
        }
    } catch (error) {
        console.error('Error loading apps:', error);
    }
}

// Render apps in sidebar navigation
function renderAppsNav() {
    const appsNav = document.getElementById('apps-nav');
    const navItems = apps.map(app => `
        <a href="javascript:void(0)" class="nav-item" onclick="loadView('app', ${app.id})">
            <i class="iconoir-app-window"></i>
            <span>${escapeHtml(app.name)}</span>
        </a>
    `).join('');

    appsNav.innerHTML = `
        <div class="nav-section-title">Aplicaciones</div>
        ${navItems}
    `;
}

// Populate app select dropdowns
function populateAppSelects() {
    const selects = document.querySelectorAll('#request-app');
    selects.forEach(select => {
        select.innerHTML = '<option value="">Selecciona una app</option>' +
            apps.map(app => `<option value="${app.id}">${escapeHtml(app.name)}</option>`).join('');
    });
}

// Switch view (global or specific app)
function loadView(type, appId = null) {
    currentView = type;
    currentAppId = appId;

    // Update page title
    const pageTitle = document.getElementById('page-title');
    if (type === 'global') {
        pageTitle.textContent = 'Vista Global';
    } else {
        const app = apps.find(a => a.id == appId);
        pageTitle.textContent = app ? app.name : 'App';
    }

    // Update active nav item
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    event.target.closest('.nav-item').classList.add('active');

    // Reload requests
    loadRequests();
}

// Load requests with filters
async function loadRequests() {
    const sort = document.getElementById('sort-select').value;
    const priority = document.getElementById('priority-filter').value;
    const status = document.getElementById('status-filter').value;

    let url = '/api/requests.php?sort=' + sort;

    if (currentView === 'app' && currentAppId) {
        url += '&app_id=' + currentAppId;
    }

    if (priority) {
        url += '&priority=' + priority;
    }

    if (status) {
        url += '&status=' + status;
    }

    try {
        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            renderRequests(data.data);
        }
    } catch (error) {
        console.error('Error loading requests:', error);
    }
}

// Render request cards
function renderRequests(requests) {
    const grid = document.getElementById('requests-grid');

    if (requests.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                <h3>No hay peticiones aún</h3>
                <p>Crea la primera petición usando el botón de arriba.</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = requests.map(request => createRequestCard(request)).join('');
}

// Create a single request card
function createRequestCard(request) {
    const priorityClass = `priority-${request.priority}`;
    const statusClass = `status-${request.status}`;
    const date = new Date(request.created_at).toLocaleDateString('es-ES', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });

    // Check if current user is admin (passed from PHP - we'll add this)
    const isAdmin = document.body.dataset.userRole === 'admin' || document.body.dataset.userRole === 'superadmin';

    return `
        <div class="card" data-request-id="${request.id}">
            <div class="card-header">
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <span class="priority-badge ${priorityClass}">${getPriorityLabel(request.priority)}</span>
                    <span class="status-badge ${statusClass}">${getStatusLabel(request.status)}</span>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span class="text-small text-muted">${date}</span>
                    ${isAdmin ? `<button class="btn btn-sm btn-outline" onclick="event.stopPropagation(); openEditRequestModal(${request.id})" style="padding: 0.25rem 0.5rem;"><i class="iconoir-edit"></i></button>` : ''}
                </div>
            </div>
            
            <h3 class="card-title">${escapeHtml(request.title)}</h3>
            
            ${request.description ? `
                <p class="card-description">${escapeHtml(request.description)}</p>
            ` : ''}
            
            <div class="card-footer">
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <span class="text-small text-muted">
                        <i class="iconoir-app-window" style="font-size: 0.875rem;"></i>
                        ${escapeHtml(request.app_name)}
                    </span>
                    <span class="text-small text-muted">
                        <i class="iconoir-user" style="font-size: 0.875rem;"></i>
                        ${escapeHtml(request.creator_name || request.creator_username)}
                    </span>
                    ${request.attachment_count > 0 ? `
                        <span class="text-small text-muted">
                            <i class="iconoir-attachment" style="font-size: 0.875rem;"></i>
                            ${request.attachment_count} adjunto(s)
                        </span>
                    ` : ''}
                </div>
                
                <div class="vote-section">
                    <button class="vote-btn" onclick="event.stopPropagation(); vote(${request.id}, 'up')" title="Votar">
                        <i class="iconoir-arrow-up"></i>
                    </button>
                    <span class="vote-count">${request.vote_count || 0}</span>
                </div>
            </div>
        </div>
    `;
}

// Vote on a request
async function vote(requestId, action) {
    try {
        const response = await fetch('/api/votes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId, action: action })
        });

        const data = await response.json();

        if (data.success) {
            loadRequests(); // Reload to show updated vote count
        } else {
            alert(data.error || 'Error al votar');
        }
    } catch (error) {
        console.error('Error voting:', error);
        alert('Error al votar');
    }
}

// Open new request modal
function openNewRequestModal() {
    document.getElementById('new-request-modal').classList.add('active');

    // Pre-select current app if viewing specific app
    if (currentView === 'app' && currentAppId) {
        const appSelect = document.getElementById('request-app');
        if (appSelect) {
            appSelect.value = currentAppId;
        }
    }
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    if (modalId === 'new-request-modal') {
        document.getElementById('new-request-form').reset();
        selectedFiles = [];
        document.getElementById('file-list').innerHTML = '';
    }
}

// Submit new request
async function submitNewRequest(event) {
    event.preventDefault();

    const appId = document.getElementById('request-app').value;
    const title = document.getElementById('request-title').value;
    const description = document.getElementById('request-description').value;
    const priority = document.getElementById('request-priority').value;

    try {
        // Create request
        const response = await fetch('/api/requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                app_id: appId,
                title: title,
                description: description,
                priority: priority
            })
        });

        const data = await response.json();

        if (data.success) {
            const requestId = data.data.id;

            // Upload files if any
            if (selectedFiles.length > 0) {
                for (const file of selectedFiles) {
                    await uploadFile(requestId, file);
                }
            }

            closeModal('new-request-modal');
            loadRequests();
        } else {
            alert(data.error || 'Error al crear la petición');
        }
    } catch (error) {
        console.error('Error creating request:', error);
        alert('Error al crear la petición');
    }
}

// Upload file
async function uploadFile(requestId, file) {
    const formData = new FormData();
    formData.append('request_id', requestId);
    formData.append('file', file);

    try {
        await fetch('/api/upload.php', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('Error uploading file:', error);
    }
}

// Setup file upload
function setupFileUpload() {
    const uploadArea = document.getElementById('file-upload-area');
    const fileInput = document.getElementById('file-input');
    const fileList = document.getElementById('file-list');

    uploadArea.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });
}

// Handle file selection
function handleFiles(files) {
    const fileList = document.getElementById('file-list');
    selectedFiles = Array.from(files);

    fileList.innerHTML = selectedFiles.map((file, index) => `
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: var(--bg-secondary); border-radius: var(--radius-sm); margin-bottom: 0.5rem;">
            <span class="text-small">
                <i class="iconoir-attachment"></i>
                ${escapeHtml(file.name)} (${formatFileSize(file.size)})
            </span>
            <button type="button" class="btn btn-sm" onclick="removeFile(${index})" style="padding: 0.25rem 0.5rem;">×</button>
        </div>
    `).join('');
}

// Remove file from selection
function removeFile(index) {
    selectedFiles.splice(index, 1);
    handleFiles(selectedFiles);
}

// Format file size
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// Get priority label
function getPriorityLabel(priority) {
    const labels = {
        'critical': 'Crítica',
        'high': 'Alta',
        'medium': 'Media',
        'low': 'Baja'
    };
    return labels[priority] || priority;
}

// Get status label
function getStatusLabel(status) {
    const labels = {
        'pending': 'Pendiente',
        'in_progress': 'En Progreso',
        'completed': 'Completado',
        'discarded': 'Descartado'
    };
    return labels[status] || status;
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Open edit request modal
async function openEditRequestModal(requestId) {
    try {
        // Fetch request details
        const response = await fetch(`/api/requests.php?id=${requestId}`);
        const data = await response.json();

        if (data.success && data.data.length > 0) {
            const request = data.data[0];

            // Populate form
            document.getElementById('edit-request-id').value = request.id;
            document.getElementById('edit-request-title').value = request.title;
            document.getElementById('edit-request-description').value = request.description || '';

            // Set priority and status if fields exist (admin only)
            const priorityField = document.getElementById('edit-request-priority');
            const statusField = document.getElementById('edit-request-status');
            if (priorityField) priorityField.value = request.priority;
            if (statusField) statusField.value = request.status;

            // Open modal
            document.getElementById('edit-request-modal').classList.add('active');
        }
    } catch (error) {
        console.error('Error loading request:', error);
        alert('Error al cargar la petición');
    }
}

// Submit edit request
async function submitEditRequest(event) {
    event.preventDefault();

    const requestId = document.getElementById('edit-request-id').value;
    const title = document.getElementById('edit-request-title').value;
    const description = document.getElementById('edit-request-description').value;

    const payload = {
        id: parseInt(requestId),
        title: title,
        description: description
    };

    // Add priority and status if user is admin
    const priorityField = document.getElementById('edit-request-priority');
    const statusField = document.getElementById('edit-request-status');
    if (priorityField) payload.priority = priorityField.value;
    if (statusField) payload.status = statusField.value;

    try {
        const response = await fetch('/api/requests.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (data.success) {
            closeModal('edit-request-modal');
            loadRequests();
        } else {
            alert(data.error || 'Error al actualizar la petición');
        }
    } catch (error) {
        console.error('Error updating request:', error);
        alert('Error al actualizar la petición');
    }
}

// Delete request (superadmin only)
async function deleteRequest() {
    const requestId = document.getElementById('edit-request-id').value;

    if (!confirm('¿Estás seguro de que quieres eliminar esta petición?')) {
        return;
    }

    try {
        const response = await fetch('/api/requests.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(requestId) })
        });

        const data = await response.json();

        if (data.success) {
            closeModal('edit-request-modal');
            loadRequests();
        } else {
            alert(data.error || 'Error al eliminar la petición');
        }
    } catch (error) {
        console.error('Error deleting request:', error);
        alert('Error al eliminar la petición');
    }
}

// Close modal on outside click
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

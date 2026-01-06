// Prisma Dashboard - Main JavaScript

let apps = [];
let requests = [];
let currentView = 'global';
let currentAppId = null;
let selectedFiles = [];
let showFinished = false;

const userRole = document.body.dataset.userRole;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    loadApps();
    loadApps();

    // Check for app_id in URL
    const urlParams = new URLSearchParams(window.location.search);
    const appIdParam = urlParams.get('app_id');

    if (appIdParam) {
        // We need to wait for apps to load to get the app name, 
        // but we can start loading requests immediately
        currentView = 'app';
        currentAppId = appIdParam;
        loadRequests();

        // Update UI once apps are loaded
        // This is handled in loadApps -> renderAppsNav -> but we need to set active state
        // We'll add a small check in renderAppsNav or just wait a bit
        setTimeout(() => {
            const navItem = document.querySelector(`a[onclick*="loadView('app', ${appIdParam})"]`);
            if (navItem) {
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                navItem.classList.add('active');

                const app = apps.find(a => a.id == appIdParam);
                if (app) {
                    document.getElementById('page-title').textContent = app.name;
                }
            }
        }, 500); // Small delay to ensure apps are rendered
    } else {
        loadRequests();
    }
    setupFileUpload();

    // Cmd/Ctrl + Enter to submit new request form
    const newRequestForm = document.getElementById('new-request-form');
    if (newRequestForm) {
        newRequestForm.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
                e.preventDefault();
                newRequestForm.requestSubmit();
            }
        });
    }

    // Update pending count for admins
    if (userRole === 'admin' || userRole === 'superadmin') {
        updatePendingCount();
    }
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
    try {
        const sortBy = document.getElementById('sort-select')?.value || 'date_desc';
        const priority = document.getElementById('priority-filter')?.value || 'all';
        const status = document.getElementById('status-filter')?.value || 'all';

        let url = '/api/requests.php?';
        const params = [];

        if (currentView === 'app' && currentAppId) {
            params.push(`app_id=${currentAppId}`);
        }

        if (priority !== 'all') params.push(`priority=${priority}`);
        if (status !== 'all') params.push(`status=${status}`);
        params.push(`sort=${sortBy}`);

        url += params.join('&');

        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            requests = data.data;
            renderRequests();
        }
    } catch (error) {
        console.error('Error loading requests:', error);
    }
}

// Render requests
function renderRequests() {
    const grid = document.getElementById('requests-grid');

    if (requests.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                <h3>No hay mejoras aún</h3>
                <p>Crea la primera mejora usando el botón de arriba.</p>
            </div>
        `;
        return;
    }

    // Separate active and finished requests
    const activeRequests = requests.filter(r =>
        r.status === 'pending' || r.status === 'in_progress'
    );
    const finishedRequests = requests.filter(r =>
        r.status === 'completed' || r.status === 'discarded'
    );

    grid.innerHTML = '';

    // Render active requests
    activeRequests.forEach(request => {
        const card = createRequestCard(request);
        grid.appendChild(card);
    });

    // Add separator if there are finished requests
    if (finishedRequests.length > 0) {
        const separator = document.createElement('div');
        separator.className = `requests-separator ${showFinished ? 'active' : ''}`;
        separator.onclick = toggleFinishedRequests;
        separator.innerHTML = `
            <div class="separator-line"></div>
            <span class="separator-text">
                <i class="iconoir-check-circle"></i>
                Finalizadas (${finishedRequests.length})
                <i class="iconoir-nav-arrow-down toggle-icon" style="margin-left: 4px;"></i>
            </span>
            <div class="separator-line"></div>
        `;
        grid.appendChild(separator);

        const finishedWrapper = document.createElement('div');
        finishedWrapper.className = `finished-requests-wrapper ${showFinished ? 'show' : ''}`;
        
        // Render finished requests with subtle style
        finishedRequests.forEach(request => {
            const card = createRequestCard(request, true);
            card.classList.add('card-finished');
            finishedWrapper.appendChild(card);
        });
        
        grid.appendChild(finishedWrapper);
    }
}

// Toggle visibility of finished requests
function toggleFinishedRequests() {
    showFinished = !showFinished;
    const separator = document.querySelector('.requests-separator');
    const wrapper = document.querySelector('.finished-requests-wrapper');
    
    if (separator && wrapper) {
        separator.classList.toggle('active');
        wrapper.classList.toggle('show');
    }
}

// Create a single request card
function createRequestCard(request, isFinished = false) {
    const priorityClass = `priority-${request.priority}`;
    const statusClass = `status-${request.status}`;
    const date = new Date(request.created_at).toLocaleDateString('es-ES', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });

    // Check if current user is admin (passed from PHP - we'll add this)
    const card = document.createElement('div');
    card.className = 'card';
    card.dataset.requestId = request.id;

    const priorityLabels = {
        'critical': 'CRÍTICA',
        'high': 'ALTA',
        'medium': 'MEDIA',
        'low': 'BAJA'
    };

    const statusLabels = {
        'pending': 'Pendiente',
        'in_progress': 'En Progreso',
        'completed': 'Completado',
        'discarded': 'Descartado'
    };

    const userRole = document.body.dataset.userRole;
    const isAdminOrSuperadmin = ['admin', 'superadmin'].includes(userRole);

    card.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--spacing-md); margin-bottom: var(--spacing-sm);">
            <div style="display: flex; align-items: center; gap: var(--spacing-md); flex: 1; min-width: 0;">
                <div class="priority-badge priority-${request.priority}" 
                     ${isAdminOrSuperadmin ? `onclick="toggleBadgeDropdown(event, ${request.id}, 'priority')"` : ''}
                     style="flex-shrink: 0;">
                    ${priorityLabels[request.priority] || request.priority.toUpperCase()}
                    ${isAdminOrSuperadmin ? '<i class="iconoir-nav-arrow-down" style="font-size: 0.625rem;"></i>' : ''}
                    ${isAdminOrSuperadmin ? createPriorityDropdown(request.id, request.priority) : ''}
                </div>
                
                <h3 class="card-title" style="margin: 0; flex: 1; min-width: 0;">
                    ${(currentView === 'global' && request.app_name) ? escapeHtml(request.app_name) + ' - ' : ''}${escapeHtml(request.title)}
                </h3>
            </div>
            
            ${isAdminOrSuperadmin ? `
                <div style="display: flex; align-items: center; gap: var(--spacing-sm); flex-shrink: 0;">
                    <div class="difficulty-indicator" title="Dificultad">
                        <button class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 1 ? 'active' : ''}" 
                                onclick="setDifficulty(${request.id}, 'low', event)" title="Baja"></button>
                        <button class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 2 ? 'active' : ''}" 
                                onclick="setDifficulty(${request.id}, 'medium', event)" title="Media"></button>
                        <button class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 3 ? 'active' : ''}" 
                                onclick="setDifficulty(${request.id}, 'high', event)" title="Alta"></button>
                    </div>
                    <div class="status-actions">
                        <button class="status-action-btn ${request.status === 'pending' ? 'active' : ''}" 
                                onclick="quickUpdateRequest(${request.id}, 'status', 'pending', event)"
                                title="Pausar">
                            <i class="iconoir-pause"></i>
                        </button>
                        <button class="status-action-btn ${request.status === 'in_progress' ? 'active' : ''}" 
                                onclick="quickUpdateRequest(${request.id}, 'status', 'in_progress', event)"
                                title="En progreso">
                            <i class="iconoir-play"></i>
                        </button>
                        <button class="status-action-btn ${request.status === 'completed' ? 'active' : ''}" 
                                onclick="quickUpdateRequest(${request.id}, 'status', 'completed', event)"
                                title="Completar">
                            <i class="iconoir-check"></i>
                        </button>
                        <button class="status-action-btn ${request.status === 'discarded' ? 'active' : ''}" 
                                onclick="quickUpdateRequest(${request.id}, 'status', 'discarded', event)"
                                title="Descartar">
                            <i class="iconoir-xmark"></i>
                        </button>
                    </div>
                </div>
            ` : `
                ${request.difficulty ? `
                    <div class="difficulty-display" style="flex-shrink: 0;" title="Dificultad: ${getDifficultyLabel(request.difficulty)}">
                        <div class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 1 ? 'active' : ''}"></div>
                        <div class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 2 ? 'active' : ''}"></div>
                        <div class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 3 ? 'active' : ''}"></div>
                    </div>
                ` : ''}
                <div class="status-badge-display status-${request.status}" style="flex-shrink: 0;">
                    ${statusLabels[request.status] || request.status}
                </div>
            `}
        </div>

        <p class="card-description">${escapeHtml(request.description)}</p>

        ${request.files && request.files.length > 0 ? `
            <div style="margin-bottom: var(--spacing-md);">
                <div style="font-size: 0.875rem; font-weight: 600; color: var(--text-secondary); margin-bottom: var(--spacing-xs);">
                    <i class="iconoir-attachment"></i> Archivos adjuntos:
                </div>
                <ul style="list-style: none; padding: 0;">
                    ${request.files.map(file => `
                        <li style="margin-bottom: var(--spacing-xs);">
                            <a href="${escapeHtml(file)}" target="_blank" 
                               style="color: var(--primary-color); text-decoration: none; font-size: 0.875rem;">
                                <i class="iconoir-download"></i> ${escapeHtml(file.split('/').pop())}
                            </a>
                        </li>
                    `).join('')}
                </ul>
            </div>
        ` : ''}

        <div class="card-footer">
            <div style="display: flex; align-items: center; gap: var(--spacing-md); flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: var(--spacing-sm);">
                    <i class="iconoir-user" style="color: var(--text-muted); font-size: 0.875rem;"></i>
                    <span class="text-small text-muted">${escapeHtml(request.creator_username || request.created_by)}</span>
                </div>

                ${isAdminOrSuperadmin ? `
                    <div style="display: flex; align-items: center; gap: var(--spacing-xs); margin-left: auto;">
                        <button class="quick-action-btn edit" onclick="openEditRequestModal(${request.id})" title="Editar">
                            <i class="iconoir-edit"></i>
                        </button>
                        <button class="quick-action-btn delete" onclick="deleteRequest(${request.id})" title="Eliminar" style="color: var(--color-red);">
                            <i class="iconoir-xmark"></i>
                        </button>
                    </div>
                ` : ''}
            </div>
            <div class="vote-section">
                <button class="vote-btn ${request.user_voted && !isAdminOrSuperadmin ? 'voted' : ''}" 
                        onclick="vote(${request.id}, 'up')"
                        title="${isAdminOrSuperadmin ? 'Aumentar votos' : (request.user_voted ? 'Quitar voto' : 'Votar')}">
                    <i class="iconoir-arrow-up"></i>
                </button>
                <span class="vote-count">${request.vote_count || 0}</span>
                ${isAdminOrSuperadmin ? `
                    <button class="vote-btn vote-down" 
                            onclick="vote(${request.id}, 'down')"
                            title="Reducir votos">
                        <i class="iconoir-arrow-down"></i>
                    </button>
                ` : ''}
            </div>
        </div>
    `;

    return card;
}

// Create priority dropdown
function createPriorityDropdown(requestId, currentPriority) {
    const priorities = [
        { value: 'low', label: 'BAJA' },
        { value: 'medium', label: 'MEDIA' },
        { value: 'high', label: 'ALTA' },
        { value: 'critical', label: 'CRÍTICA' }
    ];

    return `
        <div class="badge-dropdown" data-dropdown="priority-${requestId}">
            ${priorities.map(p => `
                <div class="badge-dropdown-item ${p.value === currentPriority ? 'selected' : ''}"
                     onclick="quickUpdateRequest(${requestId}, 'priority', '${p.value}', event)">
                    <span class="priority-badge priority-${p.value}" style="cursor: default; padding: 2px 8px;">
                        ${p.label}
                    </span>
                </div>
            `).join('')}
        </div>
    `;
}

// Create status dropdown
function createStatusDropdown(requestId, currentStatus) {
    const statuses = [
        { value: 'pending', label: 'Pendiente' },
        { value: 'in_progress', label: 'En Progreso', color: 'var(--color-blue)' },
        { value: 'completed', label: 'Completado', color: 'var(--color-green)' },
        { value: 'discarded', label: 'Descartado', color: 'var(--color-red)' }
    ];

    return `
        <div class="badge-dropdown" data-dropdown="status-${requestId}">
            ${statuses.map(s => `
                <div class="badge-dropdown-item ${s.value === currentStatus ? 'selected' : ''}"
                     onclick="quickUpdateRequest(${requestId}, 'status', '${s.value}', event)">
                    <span class="status-badge status-${s.value}" style="cursor: default; padding: 2px 8px;">
                        ${s.label}
                    </span>
                </div>
            `).join('')}
        </div>
    `;
}

// Toggle badge dropdown
function toggleBadgeDropdown(event, requestId, type) {
    event.stopPropagation();

    const dropdownId = `${type}-${requestId}`;
    const dropdown = document.querySelector(`[data-dropdown="${dropdownId}"]`);

    // Close all other dropdowns
    document.querySelectorAll('.badge-dropdown.active').forEach(d => {
        if (d !== dropdown) d.classList.remove('active');
    });

    if (dropdown) {
        dropdown.classList.toggle('active');
    }
}

// Quick update request (priority or status)
async function quickUpdateRequest(requestId, field, value, event) {
    event.stopPropagation();

    const data = { id: requestId };
    data[field] = value;

    try {
        const response = await fetch('/api/requests.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            // Close dropdown
            document.querySelectorAll('.badge-dropdown.active').forEach(d => {
                d.classList.remove('active');
            });

            // Show toast notification for status changes
            if (field === 'status') {
                const statusMessages = {
                    'pending': { title: 'Pausada', message: 'La mejora está en espera', icon: 'iconoir-pause' },
                    'in_progress': { title: 'En progreso', message: 'Trabajando en la mejora', icon: 'iconoir-play' },
                    'completed': { title: 'Completada', message: 'La mejora está finalizada', icon: 'iconoir-check' },
                    'discarded': { title: 'Descartada', message: 'La mejora ha sido descartada', icon: 'iconoir-xmark' }
                };
                showToast(statusMessages[value], `toast-${value}`);
            }

            // Reload requests to show updated state
            await loadRequests();
        } else {
            alert(result.error || 'Error al actualizar');
        }
    } catch (error) {
        console.error('Error updating request:', error);
        alert('Error al actualizar');
    }
}

// Toast notification system
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

    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.classList.add('toast-hide');
        setTimeout(() => {
            container.removeChild(toast);
        }, 300);
    }, 3000);
}

// Close dropdowns when clicking outside
document.addEventListener('click', () => {
    document.querySelectorAll('.badge-dropdown.active').forEach(d => {
        d.classList.remove('active');
    });
});

// Vote on a request
async function vote(requestId, action = 'up') {
    try {
        const response = await fetch('/api/votes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_id: requestId,
                action: action
            })
        });

        const data = await response.json();

        if (data.success) {
            // Reload requests to update vote count
            await loadRequests();
        } else {
            alert(data.error || 'Error al procesar el voto');
        }
    } catch (error) {
        console.error('Error voting:', error);
        alert('Error al procesar el voto');
    }
}

// Open new request modal
function openNewRequestModal() {
    // Reset form
    document.getElementById('new-request-form').reset();

    // Pre-select current app if viewing a specific app
    if (currentView === 'app' && currentAppId) {
        const appSelect = document.getElementById('request-app'); // Keep original ID for now, assuming 'new-request-app' is a typo in instruction
        if (appSelect) {
            appSelect.value = currentAppId;
        }
    }

    // Open modal
    document.getElementById('new-request-modal').classList.add('active');

    // Auto-focus on title field for immediate typing
    setTimeout(() => {
        document.getElementById('request-title').focus(); // Assuming 'request-title' is the ID for the title field in the new request modal
    }, 100);
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
    const difficulty = document.getElementById('request-difficulty').value;
    const requesterName = document.getElementById('request-requester-name').value;
    const requesterEmail = document.getElementById('request-requester-email').value;

    try {
        // Create request
        const requestData = {
            app_id: appId,
            title: title,
            description: description,
            priority: priority,
            difficulty: difficulty || null
        };

        // Add optional requester info if provided
        if (requesterName) {
            requestData.requester_name = requesterName;
        }
        if (requesterEmail) {
            requestData.requester_email = requesterEmail;
        }

        const response = await fetch('/api/requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
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

            // Reset form
            document.getElementById('new-request-form').reset();
            selectedFiles = [];
            document.getElementById('file-list').innerHTML = '';

            closeModal('new-request-modal');
            loadRequests();

            showToast({
                title: 'Mejora creada',
                message: 'La solicitud se ha creado correctamente',
                icon: 'iconoir-check'
            }, 'toast-completed');
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

// Get difficulty level (1, 2, 3) for bar display
function getDifficultyLevel(difficulty) {
    const levels = {
        'low': 1,
        'medium': 2,
        'high': 3
    };
    return levels[difficulty] || 0;
}

// Get difficulty label
function getDifficultyLabel(difficulty) {
    const labels = {
        'low': 'Baja',
        'medium': 'Media',
        'high': 'Alta'
    };
    return labels[difficulty] || 'Sin definir';
}

// Set difficulty for a request
async function setDifficulty(requestId, difficulty, event) {
    event.stopPropagation();
    
    try {
        const response = await fetch('/api/requests.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: requestId, difficulty: difficulty })
        });

        const result = await response.json();

        if (result.success) {
            await loadRequests();
        } else {
            alert(result.error || 'Error al actualizar la dificultad');
        }
    } catch (error) {
        console.error('Error updating difficulty:', error);
        alert('Error al actualizar la dificultad');
    }
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
            const difficultyField = document.getElementById('edit-request-difficulty');
            if (priorityField) priorityField.value = request.priority;
            if (statusField) statusField.value = request.status;
            if (difficultyField) difficultyField.value = request.difficulty || '';

            // Set requester info if available
            const requesterNameField = document.getElementById('edit-request-requester-name');
            const requesterEmailField = document.getElementById('edit-request-requester-email');
            if (requesterNameField) requesterNameField.value = request.requester_name || '';
            if (requesterEmailField) requesterEmailField.value = request.requester_email || '';

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
    const difficultyField = document.getElementById('edit-request-difficulty');
    if (priorityField) payload.priority = priorityField.value;
    if (statusField) payload.status = statusField.value;
    if (difficultyField) payload.difficulty = difficultyField.value || null;

    // Add requester info if provided
    const requesterNameField = document.getElementById('edit-request-requester-name');
    const requesterEmailField = document.getElementById('edit-request-requester-email');
    if (requesterNameField && requesterNameField.value) {
        payload.requester_name = requesterNameField.value;
    }
    if (requesterEmailField && requesterEmailField.value) {
        payload.requester_email = requesterEmailField.value;
    }

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

// Delete request
async function deleteRequest(requestId) {
    if (!requestId) {
        requestId = document.getElementById('edit-request-id').value;
    }

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
            // If we are in the edit modal, close it
            const editModal = document.getElementById('edit-request-modal');
            if (editModal && editModal.classList.contains('active')) {
                closeModal('edit-request-modal');
            }
            loadRequests();
            
            showToast({
                title: 'Mejora eliminada',
                message: 'La solicitud se ha eliminado correctamente',
                icon: 'iconoir-trash'
            }, 'toast-discarded');
        } else {
            alert(data.error || 'Error al eliminar la petición');
        }
    } catch (error) {
        console.error('Error deleting request:', error);
        alert('Error al eliminar la petición');
    }
}

// ========== PROFILE ==========

// Open profile modal
async function openProfileModal() {
    try {
        const response = await fetch('/api/profile.php');
        const data = await response.json();

        if (data.success) {
            const profile = data.data;

            // Populate form
            document.getElementById('profile-username').value = profile.username;
            document.getElementById('profile-fullname').value = profile.full_name || '';
            document.getElementById('profile-email').value = profile.email || '';
            document.getElementById('profile-password').value = '';

            // Show read-only fields
            const roleLabels = {
                'superadmin': 'Superadministrador',
                'admin': 'Administrador',
                'user': 'Usuario'
            };
            document.getElementById('profile-role').textContent = roleLabels[profile.role] || profile.role;
            document.getElementById('profile-company').textContent = profile.company_name || 'Sin asignar';

            // Open modal
            document.getElementById('profile-modal').classList.add('active');
        }
    } catch (error) {
        console.error('Error loading profile:', error);
        alert('Error al cargar el perfil');
    }
}

// Submit profile update
async function submitProfile(event) {
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

    try {
        const response = await fetch('/api/profile.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            closeModal('profile-modal');
            // Reload page to update sidebar
            location.reload();
        } else {
            alert(result.error || 'Error al actualizar el perfil');
        }
    } catch (error) {
        console.error('Error updating profile:', error);
        alert('Error al actualizar el perfil');
    }
}

// Close modal on outside click
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

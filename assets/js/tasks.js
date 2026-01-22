/**
 * Tasks Page JavaScript
 */

let currentTaskId = null;
let teamMembers = [];
let selectedShareUsers = [];
let shareWithAll = false;

document.addEventListener('DOMContentLoaded', () => {
    loadTasks();
    setupQuickAdd();
    setupTaskModal();
    loadTeamMembers();
    setupShareDropdown();
});

// Setup Quick Add functionality
function setupQuickAdd() {
    const input = document.getElementById('quick-add-input');
    const expandBtn = document.getElementById('expand-btn');
    const expanded = document.getElementById('quick-add-expanded');
    
    // Enter to create task
    input.addEventListener('keydown', async (e) => {
        if (e.key === 'Enter' && input.value.trim()) {
            e.preventDefault();
            await createQuickTask();
        }
        if (e.key === 'Escape') {
            input.value = '';
            input.blur();
            expanded.classList.remove('visible');
            expandBtn.classList.remove('active');
        }
    });
    
    // Toggle expanded options
    expandBtn.addEventListener('click', () => {
        expanded.classList.toggle('visible');
        expandBtn.classList.toggle('active');
    });
}

// Create task from quick add
async function createQuickTask() {
    const input = document.getElementById('quick-add-input');
    const title = input.value.trim();
    
    if (!title) return;
    
    const expanded = document.getElementById('quick-add-expanded');
    const isExpanded = expanded.classList.contains('visible');
    
    const taskData = {
        title: title
    };
    
    // Get expanded options if visible
    if (isExpanded) {
        const appId = document.getElementById('quick-add-app').value;
        const description = document.getElementById('quick-add-description').value.trim();
        const dueDate = document.getElementById('quick-add-due-date').value;
        
        if (appId) taskData.app_id = parseInt(appId);
        if (description) taskData.description = description;
        if (dueDate) taskData.due_date = dueDate;
    }
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(taskData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Reset form
            input.value = '';
            document.getElementById('quick-add-description').value = '';
            document.getElementById('quick-add-due-date').value = '';
            
            // Reload tasks
            await loadTasks();
            
            // Focus back to input for continuous adding
            input.focus();
        } else {
            showToast({ title: 'Error', message: data.error, icon: 'iconoir-warning-circle' }, 'toast-error');
        }
    } catch (error) {
        console.error('Error creating task:', error);
        showToast({ title: 'Error', message: 'No se pudo crear la tarea', icon: 'iconoir-warning-circle' }, 'toast-error');
    }
}

// Load tasks
async function loadTasks() {
    const showCompleted = document.getElementById('show-completed').checked;
    const showShared = document.getElementById('show-shared').checked;
    const appId = document.getElementById('app-filter').value;
    
    let url = `/api/tasks.php?completed=${showCompleted ? '1' : '0'}&shared=${showShared ? '1' : '0'}`;
    if (appId) url += `&app_id=${appId}`;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            renderTasks(data.data);
        }
    } catch (error) {
        console.error('Error loading tasks:', error);
    }
}

// Render tasks list
function renderTasks(tasks) {
    const container = document.getElementById('tasks-list');
    const emptyState = document.getElementById('empty-state');
    
    if (tasks.length === 0) {
        container.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    container.innerHTML = tasks.map(task => {
        let dueDateHtml = '';
        let dueDateClass = '';
        
        if (task.due_date && !task.is_completed) {
            const dueDate = new Date(task.due_date);
            const diffDays = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));
            
            if (diffDays < 0) {
                dueDateClass = 'overdue';
                dueDateHtml = `<span class="task-due-date overdue" title="Vencida">
                    <i class="iconoir-warning-triangle"></i>
                    ${formatDueDate(task.due_date)}
                </span>`;
            } else if (diffDays === 0) {
                dueDateClass = 'today';
                dueDateHtml = `<span class="task-due-date today" title="Hoy">
                    <i class="iconoir-clock"></i>
                    Hoy
                </span>`;
            } else if (diffDays <= 3) {
                dueDateClass = 'soon';
                dueDateHtml = `<span class="task-due-date soon" title="Próximamente">
                    <i class="iconoir-calendar"></i>
                    ${formatDueDate(task.due_date)}
                </span>`;
            } else {
                dueDateHtml = `<span class="task-due-date" title="Fecha límite">
                    <i class="iconoir-calendar"></i>
                    ${formatDueDate(task.due_date)}
                </span>`;
            }
        }
        
        return `
        <div class="task-item ${task.is_completed ? 'completed' : ''} ${dueDateClass}" data-id="${task.id}">
            <div class="task-checkbox ${task.is_completed ? 'checked' : ''}" 
                 onclick="toggleTask(${task.id}, ${task.is_completed ? 'false' : 'true'}, event)">
            </div>
            <div class="task-content" onclick="openTaskModal(${task.id})">
                <div class="task-title">${escapeHtml(task.title)}</div>
                <div class="task-meta">
                    ${dueDateHtml}
                    ${task.app_name ? `
                        <span class="task-meta-item">
                            <i class="iconoir-app-window"></i>
                            ${escapeHtml(task.app_name)}
                        </span>
                    ` : ''}
                    ${task.is_shared ? `
                        <span class="task-badge shared">
                            <i class="iconoir-group"></i>
                            Equipo
                        </span>
                    ` : task.share_count > 0 ? `
                        <span class="task-badge shared">
                            <i class="iconoir-user"></i>
                            ${task.share_count}
                        </span>
                    ` : ''}
                    ${task.attachment_count > 0 ? `
                        <span class="task-meta-item">
                            <i class="iconoir-attachment"></i>
                            ${task.attachment_count}
                        </span>
                    ` : ''}
                    ${task.description ? `
                        <span class="task-meta-item">
                            <i class="iconoir-notes"></i>
                        </span>
                    ` : ''}
                </div>
            </div>
            <div class="task-actions">
                <button class="task-action-btn" onclick="openTaskModal(${task.id})" title="Editar">
                    <i class="iconoir-edit"></i>
                </button>
                <button class="task-action-btn delete" onclick="deleteTaskDirect(${task.id}, event)" title="Eliminar">
                    <i class="iconoir-trash"></i>
                </button>
            </div>
        </div>
    `;
    }).join('');
}

// Toggle task completion
async function toggleTask(taskId, complete, event) {
    event.stopPropagation();
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId, is_completed: complete })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadTasks();
        }
    } catch (error) {
        console.error('Error toggling task:', error);
    }
}

// Delete task directly from list
async function deleteTaskDirect(taskId, event) {
    event.stopPropagation();
    
    if (!confirm('¿Eliminar esta tarea?')) return;
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadTasks();
            showToast({ title: 'Eliminada', message: 'Tarea eliminada', icon: 'iconoir-check' }, 'toast-completed');
        }
    } catch (error) {
        console.error('Error deleting task:', error);
    }
}

// Setup task modal
function setupTaskModal() {
    const fileUpload = document.getElementById('task-file-upload');
    const fileInput = document.getElementById('task-file-input');
    
    fileUpload.addEventListener('click', () => fileInput.click());
    fileUpload.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileUpload.style.borderColor = 'var(--primary-color)';
    });
    fileUpload.addEventListener('dragleave', () => {
        fileUpload.style.borderColor = 'var(--border-color)';
    });
    fileUpload.addEventListener('drop', (e) => {
        e.preventDefault();
        fileUpload.style.borderColor = 'var(--border-color)';
        handleTaskFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', () => {
        handleTaskFiles(fileInput.files);
        fileInput.value = '';
    });
}

// Handle file upload for task
async function handleTaskFiles(files) {
    if (!currentTaskId || files.length === 0) return;
    
    const progressContainer = document.getElementById('upload-progress-container');
    if (progressContainer) progressContainer.style.display = 'flex';
    
    try {
        for (const file of files) {
            const formData = new FormData();
            formData.append('task_id', currentTaskId);
            formData.append('file', file);
            
            try {
                const response = await fetch('/api/task-attachments.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await loadTaskAttachments(currentTaskId);
                } else {
                    showToast({ title: 'Error', message: data.error, icon: 'iconoir-warning-circle' }, 'toast-error');
                }
            } catch (error) {
                console.error('Error uploading file:', error);
                showToast({ title: 'Error', message: 'Error de conexión al subir el archivo', icon: 'iconoir-warning-circle' }, 'toast-error');
            }
        }
    } finally {
        if (progressContainer) progressContainer.style.display = 'none';
    }
}

// Open task modal
async function openTaskModal(taskId) {
    currentTaskId = taskId;
    
    try {
        const response = await fetch(`/api/tasks.php?`);
        const data = await response.json();
        
        if (data.success) {
            const task = data.data.find(t => t.id == taskId);
            
            if (task) {
                document.getElementById('task-id').value = task.id;
                document.getElementById('task-title').value = task.title;
                document.getElementById('task-description').value = task.description || '';
                document.getElementById('task-app').value = task.app_id || '';
                document.getElementById('task-due-date').value = task.due_date || '';
                
                // Load share settings
                await loadTaskShares(taskId, task.is_shared == 1);
                
                await loadTaskAttachments(taskId);
                
                document.getElementById('task-modal').classList.add('active');
            }
        }
    } catch (error) {
        console.error('Error loading task:', error);
    }
}

// Load task attachments
async function loadTaskAttachments(taskId) {
    const container = document.getElementById('task-attachments');
    
    try {
        const response = await fetch(`/api/task-attachments.php?task_id=${taskId}`);
        const data = await response.json();
        
        if (data.success) {
            if (data.data.length === 0) {
                container.innerHTML = '<p class="text-muted text-small">Sin archivos adjuntos</p>';
            } else {
                container.innerHTML = data.data.map(att => `
                    <div class="task-attachment-item">
                        <a href="/${att.file_path}" target="_blank">
                            <i class="${getFileIcon(att.mime_type)}"></i>
                            <span>${escapeHtml(att.original_filename)}</span>
                        </a>
                        <button type="button" class="task-action-btn delete" onclick="deleteTaskAttachment(${att.id})">
                            <i class="iconoir-trash"></i>
                        </button>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Error loading attachments:', error);
    }
}

// Delete task attachment
async function deleteTaskAttachment(attachmentId) {
    try {
        const response = await fetch('/api/task-attachments.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: attachmentId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadTaskAttachments(currentTaskId);
        }
    } catch (error) {
        console.error('Error deleting attachment:', error);
    }
}

// Save task
async function saveTask(event) {
    event.preventDefault();
    
    const taskId = parseInt(document.getElementById('task-id').value);
    
    const taskData = {
        id: taskId,
        title: document.getElementById('task-title').value.trim(),
        description: document.getElementById('task-description').value.trim() || null,
        app_id: document.getElementById('task-app').value || null,
        due_date: document.getElementById('task-due-date').value || null
    };
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(taskData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Save share settings
            await saveTaskShares(taskId);
            
            closeTaskModal();
            await loadTasks();
            showToast({ title: 'Guardado', message: 'Tarea actualizada', icon: 'iconoir-check' }, 'toast-completed');
        } else {
            showToast({ title: 'Error', message: data.error, icon: 'iconoir-warning-circle' }, 'toast-error');
        }
    } catch (error) {
        console.error('Error saving task:', error);
    }
}

// Delete task from modal
async function deleteTask() {
    if (!confirm('¿Eliminar esta tarea?')) return;
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentTaskId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeTaskModal();
            await loadTasks();
            showToast({ title: 'Eliminada', message: 'Tarea eliminada', icon: 'iconoir-check' }, 'toast-completed');
        }
    } catch (error) {
        console.error('Error deleting task:', error);
    }
}

// Close task modal
function closeTaskModal() {
    document.getElementById('task-modal').classList.remove('active');
    document.getElementById('share-selector').classList.remove('open');
    currentTaskId = null;
    selectedShareUsers = [];
    shareWithAll = false;
}

// Format due date for display
function formatDueDate(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const diffDays = Math.ceil((date - today) / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return 'Hoy';
    if (diffDays === 1) return 'Mañana';
    if (diffDays === -1) return 'Ayer';
    
    const options = { day: 'numeric', month: 'short' };
    return date.toLocaleDateString('es-ES', options);
}

// Escape HTML helper
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Utility functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getFileIcon(mimeType) {
    if (!mimeType) return 'iconoir-attachment';
    if (mimeType.startsWith('image/')) return 'iconoir-media-image';
    if (mimeType === 'application/pdf') return 'iconoir-page';
    if (mimeType.includes('word')) return 'iconoir-page';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'iconoir-table-2-columns';
    return 'iconoir-attachment';
}

// ========== Share Functions ==========

// Load team members for sharing
async function loadTeamMembers() {
    try {
        const response = await fetch('/api/task-shares.php');
        const data = await response.json();
        if (data.success) {
            teamMembers = data.data;
            renderShareUsersList();
        }
    } catch (error) {
        console.error('Error loading team members:', error);
    }
}

// Render users list in dropdown
function renderShareUsersList() {
    const container = document.getElementById('share-users-list');
    if (!container) return;
    
    if (teamMembers.length === 0) {
        container.innerHTML = '<div class="share-option" style="color: var(--text-muted); font-size: 0.8rem; text-align: center; padding: 1rem;">No hay otros miembros en el equipo</div>';
        return;
    }
    
    container.innerHTML = teamMembers.map(user => {
        const initials = getInitials(user.full_name || user.username);
        const isChecked = selectedShareUsers.includes(user.id);
        return `
            <div class="share-option">
                <label>
                    <div class="share-user-item">
                        <div class="share-user-avatar">${initials}</div>
                        <span class="share-user-name">${escapeHtml(user.full_name || user.username)}</span>
                    </div>
                    <input type="checkbox" 
                           value="${user.id}" 
                           ${isChecked ? 'checked' : ''}
                           ${shareWithAll ? 'disabled' : ''}
                           onchange="toggleShareUser(${user.id})">
                </label>
            </div>
        `;
    }).join('');
}

// Get initials from name
function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
}

// Toggle share dropdown
function toggleShareDropdown() {
    const selector = document.getElementById('share-selector');
    selector.classList.toggle('open');
}

// Setup share dropdown (close on outside click)
function setupShareDropdown() {
    document.addEventListener('click', (e) => {
        const selector = document.getElementById('share-selector');
        if (selector && !selector.contains(e.target)) {
            selector.classList.remove('open');
        }
    });
}

// Toggle share with all
function toggleShareAll() {
    shareWithAll = document.getElementById('share-with-all').checked;
    if (shareWithAll) {
        selectedShareUsers = [];
    }
    renderShareUsersList();
    updateShareStatus();
}

// Toggle individual user
function toggleShareUser(userId) {
    const index = selectedShareUsers.indexOf(userId);
    if (index > -1) {
        selectedShareUsers.splice(index, 1);
    } else {
        selectedShareUsers.push(userId);
    }
    updateShareStatus();
}

// Update share status display
function updateShareStatus() {
    const statusEl = document.getElementById('share-status');
    
    if (shareWithAll) {
        statusEl.innerHTML = '<span class="user-chip all"><i class="iconoir-group"></i> Todo el equipo</span>';
    } else if (selectedShareUsers.length === 0) {
        statusEl.textContent = 'Privada';
    } else {
        const chips = selectedShareUsers.slice(0, 3).map(userId => {
            const user = teamMembers.find(u => u.id === userId);
            if (user) {
                const initials = getInitials(user.full_name || user.username);
                return `<span class="user-chip">${initials}</span>`;
            }
            return '';
        }).join('');
        
        const extra = selectedShareUsers.length > 3 ? `<span class="user-chip">+${selectedShareUsers.length - 3}</span>` : '';
        statusEl.innerHTML = chips + extra;
    }
}

// Load task shares when opening modal
async function loadTaskShares(taskId, isSharedWithAll) {
    shareWithAll = isSharedWithAll;
    selectedShareUsers = [];
    
    document.getElementById('share-with-all').checked = isSharedWithAll;
    
    if (!isSharedWithAll) {
        try {
            const response = await fetch(`/api/task-shares.php?task_id=${taskId}`);
            const data = await response.json();
            if (data.success) {
                selectedShareUsers = data.data.map(u => u.id);
            }
        } catch (error) {
            console.error('Error loading task shares:', error);
        }
    }
    
    renderShareUsersList();
    updateShareStatus();
}

// Save share settings (called from saveTask)
async function saveTaskShares(taskId) {
    try {
        await fetch('/api/task-shares.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: taskId,
                user_ids: selectedShareUsers,
                share_with_all: shareWithAll
            })
        });
    } catch (error) {
        console.error('Error saving shares:', error);
    }
}

function showToast(config, className = '') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${className}`;
    toast.innerHTML = `
        <div class="toast-icon"><i class="${config.icon || 'iconoir-check'}"></i></div>
        <div class="toast-content">
            <div class="toast-title">${config.title || ''}</div>
            <div class="toast-message">${config.message || ''}</div>
        </div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Tasks Page JavaScript
 */

let currentTaskId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadTasks();
    setupQuickAdd();
    setupTaskModal();
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
        const isShared = document.getElementById('quick-add-shared').checked;
        const description = document.getElementById('quick-add-description').value.trim();
        const dueDate = document.getElementById('quick-add-due-date').value;
        
        if (appId) taskData.app_id = parseInt(appId);
        if (isShared) taskData.is_shared = true;
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
            document.getElementById('quick-add-shared').checked = false;
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
    if (!currentTaskId) return;
    
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
        }
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
                document.getElementById('task-shared').checked = task.is_shared == 1;
                
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
    
    const taskData = {
        id: parseInt(document.getElementById('task-id').value),
        title: document.getElementById('task-title').value.trim(),
        description: document.getElementById('task-description').value.trim() || null,
        app_id: document.getElementById('task-app').value || null,
        due_date: document.getElementById('task-due-date').value || null,
        is_shared: document.getElementById('task-shared').checked
    };
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(taskData)
        });
        
        const data = await response.json();
        
        if (data.success) {
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
    currentTaskId = null;
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

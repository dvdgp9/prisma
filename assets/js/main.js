// Prisma Dashboard - Main JavaScript

let apps = [];
let requests = [];
let currentView = 'global';
let currentAppId = null;
let currentCompanyId = null;
let selectedFiles = [];
let showFinished = false;
let requestSearchTerm = '';
let currentQuickView = 'all';
let currentChecklistItems = [];
let currentRequestsViewMode = localStorage.getItem('prisma_requests_view_mode') || 'cards';
let currentTableSort = {
    field: null,
    direction: 'asc'
};

const userRole = document.body.dataset.userRole;

// Initialize on page load
document.addEventListener('DOMContentLoaded', async function () {
    // Check for app/company in URL (supports both 'app' and 'app_id' params)
    const urlParams = new URLSearchParams(window.location.search);
    const appIdParam = urlParams.get('app') || urlParams.get('app_id');
    const companyIdParam = urlParams.get('company');
    const openRequestId = urlParams.get('open_request');
    const isPending = window.location.hash === '#pending';

    // Set initial state based on URL
    if (isPending && (userRole === 'admin' || userRole === 'superadmin')) {
        currentView = 'pending';
        document.getElementById('page-title').textContent = 'Solicitudes Pendientes de Aprobar';
    } else if (companyIdParam) {
        currentView = 'company';
        currentCompanyId = parseInt(companyIdParam);
    } else if (appIdParam) {
        currentView = 'app';
        currentAppId = parseInt(appIdParam);
    }

    // Load apps first, then update UI
    await loadApps();

    // Now update based on current view
    if (currentView === 'pending') {
        loadPendingApprovals();
    } else if (currentView === 'company' && currentCompanyId) {
        const company = appsGrouped.find(g => g.id == currentCompanyId);
        if (company) {
            document.getElementById('page-title').textContent = company.name;
        }
        updateActiveNavItem(null, currentCompanyId);
        loadRequests();
        loadAppResources();
    } else if (currentView === 'app' && currentAppId) {
        // Update title and active state now that apps are loaded
        const app = apps.find(a => a.id == currentAppId);
        if (app) {
            document.getElementById('page-title').textContent = app.name;
        }
        updateActiveNavItem(currentAppId, null);
        loadRequests();
        loadAppResources();
    } else {
        loadRequests();
    }

    // Open request from notification link
    if (openRequestId) {
        setTimeout(() => openEditRequestModal(parseInt(openRequestId)), 500);
    }

    setupFileUpload();
    setupAppFilesUpload();
    setupEditModalFileUpload();
    syncRequestsViewModeUI();

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

    // Update pending count for admins (with slight delay to ensure DOM is ready)
    if (userRole === 'admin' || userRole === 'superadmin') {
        setTimeout(() => {
            if (typeof updatePendingCount === 'function') {
                updatePendingCount();
            }
        }, 100);
    }
});

function isPendingViewActive() {
    return currentView === 'pending';
}

function setTableSort(field) {
    if (currentTableSort.field === field) {
        currentTableSort.direction = currentTableSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
        currentTableSort.field = field;
        currentTableSort.direction = 'asc';
    }

    if (currentRequestsViewMode === 'table') {
        renderRequests();
    }
}

function syncTableSortUI() {
    document.querySelectorAll('.requests-table-sortable').forEach(th => {
        th.classList.remove('is-active', 'is-desc');
        if (th.dataset.sortField === currentTableSort.field) {
            th.classList.add('is-active');
            if (currentTableSort.direction === 'desc') {
                th.classList.add('is-desc');
            }
        }
    });
}

function sortRequestsForTable(requestsToSort) {
    if (!currentTableSort.field) return requestsToSort;

    const priorityRank = { critical: 4, high: 3, medium: 2, low: 1 };
    const statusRank = { pending: 1, in_progress: 2, completed: 3, discarded: 4 };
    const direction = currentTableSort.direction === 'asc' ? 1 : -1;

    return [...requestsToSort].sort((a, b) => {
        let aValue;
        let bValue;

        switch (currentTableSort.field) {
            case 'priority':
                aValue = priorityRank[a.priority] || 0;
                bValue = priorityRank[b.priority] || 0;
                break;
            case 'status':
                aValue = statusRank[a.status] || 0;
                bValue = statusRank[b.status] || 0;
                break;
            case 'title':
                aValue = (a.title || '').toLowerCase();
                bValue = (b.title || '').toLowerCase();
                break;
            case 'app_name':
                aValue = (a.app_name || '').toLowerCase();
                bValue = (b.app_name || '').toLowerCase();
                break;
            case 'owner':
                aValue = getPrimaryOwnerLabel(a).toLowerCase();
                bValue = getPrimaryOwnerLabel(b).toLowerCase();
                break;
            case 'assignments_count':
                aValue = (a.assignments || []).length;
                bValue = (b.assignments || []).length;
                break;
            case 'comment_count':
                aValue = parseInt(a.comment_count || 0, 10);
                bValue = parseInt(b.comment_count || 0, 10);
                break;
            case 'checklist': {
                const aChecklist = getChecklistProgress(a);
                const bChecklist = getChecklistProgress(b);
                aValue = aChecklist.total === 0 ? -1 : (aChecklist.completed / aChecklist.total);
                bValue = bChecklist.total === 0 ? -1 : (bChecklist.completed / bChecklist.total);
                break;
            }
            case 'created_at':
                aValue = new Date(a.created_at).getTime();
                bValue = new Date(b.created_at).getTime();
                break;
            default:
                return 0;
        }

        if (aValue < bValue) return -1 * direction;
        if (aValue > bValue) return 1 * direction;
        return 0;
    });
}

function openPendingApprovalsView(event = null) {
    if (event) {
        event.preventDefault();
    }

    currentView = 'pending';
    currentAppId = null;
    currentCompanyId = null;
    document.getElementById('page-title').textContent = 'Solicitudes Pendientes de Aprobar';
    history.pushState({ view: 'pending' }, '', '/index.php#pending');

    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    document.querySelectorAll('.company-nav-item').forEach(item => item.classList.remove('active'));
    document.querySelectorAll('.quick-action-btn').forEach(btn => btn.classList.remove('active'));

    const pendingBtn = document.getElementById('pending-approvals-nav');
    if (pendingBtn) {
        pendingBtn.classList.add('active');
    }

    loadPendingApprovals();
}

function getTablePriorityBadgeMarkup(request, canEdit) {
    if (canEdit) {
        return `
            <button type="button" class="table-inline-badge-button" onclick="toggleBadgeDropdown(event, ${request.id}, 'priority')">
                <span class="priority-badge priority-${request.priority}">
                    ${escapeHtml(getPriorityLabel(request.priority))}
                    <i class="iconoir-nav-arrow-down"></i>
                </span>
                ${createPriorityDropdown(request.id, request.priority)}
            </button>
        `;
    }

    return `<span class="priority-badge priority-${request.priority}">${escapeHtml(getPriorityLabel(request.priority))}</span>`;
}

function getTableStatusBadgeMarkup(request, canEdit) {
    if (canEdit) {
        return `
            <button type="button" class="table-inline-badge-button" onclick="toggleBadgeDropdown(event, ${request.id}, 'status')">
                <span class="status-badge-display status-${request.status}">
                    ${escapeHtml(getStatusLabel(request.status))}
                    <i class="iconoir-nav-arrow-down"></i>
                </span>
                ${createStatusDropdown(request.id, request.status)}
            </button>
        `;
    }

    return `<span class="status-badge-display status-${request.status}">${escapeHtml(getStatusLabel(request.status))}</span>`;
}

async function loadChecklistItems(requestId) {
    const countEl = document.getElementById('edit-checklist-count');
    const progressText = document.getElementById('edit-checklist-progress-text');
    const progressFill = document.getElementById('edit-checklist-progress-fill');

    try {
        const response = await fetch(`/api/request-checklist.php?request_id=${requestId}`);
        const data = await response.json();

        if (data.success) {
            currentChecklistItems = data.data || [];
            renderChecklistItems();

            const total = currentChecklistItems.length;
            const completed = currentChecklistItems.filter(item => parseInt(item.is_completed, 10) === 1).length;
            const percent = total > 0 ? Math.round((completed / total) * 100) : 0;

            if (countEl) countEl.textContent = total > 0 ? `${total}` : '';
            if (progressText) progressText.textContent = `${completed}/${total}`;
            if (progressFill) progressFill.style.width = `${percent}%`;

            const activeRequest = requests.find(r => parseInt(r.id, 10) === parseInt(requestId, 10));
            if (activeRequest) {
                activeRequest.checklist_count = total;
                activeRequest.checklist_completed_count = completed;
                updateEditRequestSummary(activeRequest);
            }
        }
    } catch (error) {
        console.error('Error loading checklist:', error);
    }
}

function renderRequestsTable(filteredRequests, tableBody) {
    const canEdit = ['admin', 'superadmin', 'programador'].includes(document.body.dataset.userRole);
    const canDelete = ['admin', 'superadmin'].includes(document.body.dataset.userRole);

    const sortedRequests = sortRequestsForTable(filteredRequests);

    tableBody.innerHTML = sortedRequests.map(request => {
        const checklistProgress = getChecklistProgress(request);
        const primaryOwner = getPrimaryOwnerLabel(request);
        const assignmentsCount = (request.assignments || []).length;

        return `
            <tr data-request-id="${request.id}" onclick="openEditRequestModal(${request.id})">
                <td>
                    ${getTablePriorityBadgeMarkup(request, canEdit)}
                </td>
                <td>
                    ${getTableStatusBadgeMarkup(request, canEdit)}
                </td>
                <td class="requests-table-title-cell">
                    <strong>${escapeHtml(request.title)}</strong>
                    ${request.description ? `<span class="requests-table-subline">${escapeHtml(request.description)}</span>` : ''}
                </td>
                <td>${escapeHtml(request.app_name || '-')}</td>
                <td>${escapeHtml(primaryOwner)}</td>
                <td>${assignmentsCount}</td>
                <td>${parseInt(request.comment_count || 0, 10)}</td>
                <td>${checklistProgress.total > 0 ? `${checklistProgress.completed}/${checklistProgress.total}` : '-'}</td>
                <td>${escapeHtml(getRelativeAge(request.created_at))}</td>
                <td>
                    <div class="requests-table-actions" onclick="event.stopPropagation()">
                        ${canEdit ? `
                            <button type="button" class="quick-action-btn edit" onclick="openEditRequestModal(${request.id})" title="Editar">
                                <i class="iconoir-edit"></i>
                            </button>
                        ` : ''}
                        ${canDelete ? `
                            <button type="button" class="quick-action-btn delete" onclick="deleteRequest(${request.id})" title="Eliminar">
                                <i class="iconoir-xmark"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    syncTableSortUI();
}

function renderChecklistItems() {
    const container = document.getElementById('edit-checklist-list');
    if (!container) return;

    if (!currentChecklistItems.length) {
        container.innerHTML = '<p class="text-muted text-small">No hay subtareas todavía</p>';
        return;
    }

    container.innerHTML = currentChecklistItems.map(item => `
        <div class="checklist-item ${parseInt(item.is_completed, 10) === 1 ? 'completed' : ''}" data-checklist-id="${item.id}">
            <label class="checklist-item-main">
                <input type="checkbox" ${parseInt(item.is_completed, 10) === 1 ? 'checked' : ''} onchange="toggleChecklistItem(${item.id}, this.checked)">
                <span class="checklist-item-title">${escapeHtml(item.title)}</span>
            </label>
            <div class="checklist-item-actions">
                <button type="button" class="comment-action-btn" onclick="renameChecklistItem(${item.id})" title="Renombrar">
                    <i class="iconoir-edit-pencil"></i>
                </button>
                <button type="button" class="comment-action-btn" onclick="deleteChecklistItem(${item.id})" title="Eliminar">
                    <i class="iconoir-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function handleChecklistKeydown(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        addChecklistItem();
    }
}

async function addChecklistItem() {
    const requestId = parseInt(document.getElementById('edit-request-id')?.value, 10);
    const input = document.getElementById('edit-checklist-input');
    const title = input?.value?.trim();

    if (!requestId || !title) return;

    try {
        const response = await fetch('/api/request-checklist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId, title })
        });
        const data = await response.json();

        if (data.success) {
            input.value = '';
            await loadChecklistItems(requestId);
            await loadRequests();
        } else {
            alert(data.error || 'Error al añadir subtarea');
        }
    } catch (error) {
        console.error('Error adding checklist item:', error);
    }
}

async function toggleChecklistItem(itemId, isCompleted) {
    const requestId = parseInt(document.getElementById('edit-request-id')?.value, 10);

    try {
        const response = await fetch('/api/request-checklist.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: itemId, is_completed: isCompleted })
        });
        const data = await response.json();

        if (data.success) {
            await loadChecklistItems(requestId);
            await loadRequests();
        } else {
            alert(data.error || 'Error al actualizar subtarea');
        }
    } catch (error) {
        console.error('Error toggling checklist item:', error);
    }
}

async function renameChecklistItem(itemId) {
    const item = currentChecklistItems.find(entry => parseInt(entry.id, 10) === parseInt(itemId, 10));
    if (!item) return;

    const newTitle = prompt('Nuevo texto de la subtarea', item.title);
    if (newTitle === null) return;

    const trimmed = newTitle.trim();
    if (!trimmed) return;

    const requestId = parseInt(document.getElementById('edit-request-id')?.value, 10);

    try {
        const response = await fetch('/api/request-checklist.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: itemId, title: trimmed })
        });
        const data = await response.json();

        if (data.success) {
            await loadChecklistItems(requestId);
        } else {
            alert(data.error || 'Error al renombrar subtarea');
        }
    } catch (error) {
        console.error('Error renaming checklist item:', error);
    }
}

async function deleteChecklistItem(itemId) {
    if (!confirm('¿Eliminar esta subtarea?')) return;

    const requestId = parseInt(document.getElementById('edit-request-id')?.value, 10);

    try {
        const response = await fetch('/api/request-checklist.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: itemId })
        });
        const data = await response.json();

        if (data.success) {
            await loadChecklistItems(requestId);
            await loadRequests();
        } else {
            alert(data.error || 'Error al eliminar subtarea');
        }
    } catch (error) {
        console.error('Error deleting checklist item:', error);
    }
}

function handleSearchInput() {
    requestSearchTerm = document.getElementById('request-search')?.value?.trim().toLowerCase() || '';
    renderRequests();
}

function setRequestsViewMode(mode, event) {
    currentRequestsViewMode = mode;
    localStorage.setItem('prisma_requests_view_mode', mode);
    syncRequestsViewModeUI();
    renderRequests();
}

function syncRequestsViewModeUI() {
    document.querySelectorAll('.view-toggle-btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.querySelector(`.view-toggle-btn[data-view-mode="${currentRequestsViewMode}"]`);
    if (activeBtn) activeBtn.classList.add('active');

    const grid = document.getElementById('requests-grid');
    const tableWrapper = document.getElementById('requests-table-wrapper');
    const toolbarShell = document.getElementById('requests-toolbar-shell');
    const sortingBar = document.getElementById('sorting-bar');
    if (!grid || !tableWrapper) return;

    if (toolbarShell) {
        toolbarShell.classList.toggle('is-table-mode', currentRequestsViewMode === 'table');
        toolbarShell.classList.toggle('is-cards-mode', currentRequestsViewMode !== 'table');
    }

    if (sortingBar) {
        sortingBar.classList.toggle('is-table-mode', currentRequestsViewMode === 'table');
    }

    if (currentRequestsViewMode === 'table') {
        grid.style.display = 'none';
        tableWrapper.style.display = 'block';
    } else {
        grid.style.display = 'flex';
        tableWrapper.style.display = 'none';
    }
}

function setQuickView(view, event) {
    currentQuickView = view;
    document.querySelectorAll('.quick-view-chip').forEach(chip => chip.classList.remove('active'));
    const selectedChip = document.querySelector(`.quick-view-chip[data-quick-view="${view}"]`);
    if (selectedChip) {
        selectedChip.classList.add('active');
    }

    const assignedToMe = document.getElementById('assigned-to-me-filter');
    const unassigned = document.getElementById('unassigned-filter');
    const withComments = document.getElementById('with-comments-filter');

    if (assignedToMe) assignedToMe.checked = false;
    if (unassigned) unassigned.checked = false;
    if (withComments) withComments.checked = false;

    if (view === 'mine' && assignedToMe) assignedToMe.checked = true;
    if (view === 'unassigned' && unassigned) unassigned.checked = true;
    if (view === 'commented' && withComments) withComments.checked = true;

    renderRequests();
}

function clearAllRequestFilters() {
    const priority = document.getElementById('priority-filter');
    const status = document.getElementById('status-filter');
    const difficulty = document.getElementById('difficulty-filter');
    const search = document.getElementById('request-search');
    const assignedToMe = document.getElementById('assigned-to-me-filter');
    const unassigned = document.getElementById('unassigned-filter');
    const withComments = document.getElementById('with-comments-filter');

    if (priority) priority.value = '';
    if (status) status.value = '';
    if (difficulty) difficulty.value = '';
    if (search) search.value = '';
    if (assignedToMe) assignedToMe.checked = false;
    if (unassigned) unassigned.checked = false;
    if (withComments) withComments.checked = false;

    requestSearchTerm = '';
    currentQuickView = 'all';
    document.querySelectorAll('.quick-view-chip').forEach(chip => chip.classList.remove('active'));
    const allChip = document.querySelector('.quick-view-chip[data-quick-view="all"]');
    if (allChip) allChip.classList.add('active');

    loadRequests();
}

function getFilteredRequests() {
    const currentUserId = parseInt(document.body.dataset.userId, 10);
    const assignedToMe = document.getElementById('assigned-to-me-filter')?.checked;
    const unassigned = document.getElementById('unassigned-filter')?.checked;
    const withComments = document.getElementById('with-comments-filter')?.checked;

    return requests.filter(request => {
        const assignments = request.assignments || [];
        const matchesSearch = !requestSearchTerm || [
            request.title,
            request.description,
            request.app_name,
            request.creator_name,
            request.creator_username,
            request.requester_name,
            request.requester_email
        ].filter(Boolean).some(value => value.toLowerCase().includes(requestSearchTerm));

        const isMine = assignments.some(a => parseInt(a.id, 10) === currentUserId);
        const isUnassigned = assignments.length === 0;
        const hasComments = parseInt(request.comment_count || 0, 10) > 0;

        let matchesQuickView = true;
        if (currentQuickView === 'mine') matchesQuickView = isMine;
        if (currentQuickView === 'in_progress') matchesQuickView = request.status === 'in_progress';
        if (currentQuickView === 'pending') matchesQuickView = request.status === 'pending';
        if (currentQuickView === 'completed') matchesQuickView = request.status === 'completed';
        if (currentQuickView === 'unassigned') matchesQuickView = isUnassigned;
        if (currentQuickView === 'commented') matchesQuickView = hasComments;

        if (!matchesSearch || !matchesQuickView) return false;
        if (assignedToMe && !isMine) return false;
        if (unassigned && !isUnassigned) return false;
        if (withComments && !hasComments) return false;

        return true;
    });
}

function updateRequestsSummary(filteredRequests) {
    const visibleEl = document.getElementById('summary-visible-count');
    const inProgressEl = document.getElementById('summary-in-progress-count');
    const pendingEl = document.getElementById('summary-pending-count');
    const unassignedEl = document.getElementById('summary-unassigned-count');
    const commentedEl = document.getElementById('summary-commented-count');
    const activeRequests = filteredRequests.filter(r => r.status !== 'completed' && r.status !== 'discarded');

    if (visibleEl) visibleEl.textContent = activeRequests.length;
    if (inProgressEl) inProgressEl.textContent = activeRequests.filter(r => r.status === 'in_progress').length;
    if (pendingEl) pendingEl.textContent = activeRequests.filter(r => r.status === 'pending').length;
    if (unassignedEl) unassignedEl.textContent = activeRequests.filter(r => !r.assignments || r.assignments.length === 0).length;
    if (commentedEl) commentedEl.textContent = activeRequests.filter(r => parseInt(r.comment_count || 0, 10) > 0).length;
}

function getRelativeAge(dateString) {
    if (!dateString) return '-';
    const created = new Date(dateString);
    const now = new Date();
    const diffDays = Math.floor((now - created) / (1000 * 60 * 60 * 24));
    if (diffDays <= 0) return 'Hoy';
    if (diffDays === 1) return '1 día';
    if (diffDays < 30) return `${diffDays} días`;
    const months = Math.floor(diffDays / 30);
    if (months === 1) return '1 mes';
    return `${months} meses`;
}

function getDateTimeLabel(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-ES', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getPrimaryOwnerLabel(request) {
    if (!request || !request.assignments || request.assignments.length === 0) {
        return 'Sin asignar';
    }

    const primary = request.assignments[0];
    return primary.full_name || primary.username || 'Sin asignar';
}

function getChecklistProgress(request) {
    const total = parseInt(request?.checklist_count || 0, 10);
    const completed = parseInt(request?.checklist_completed_count || 0, 10);
    return { total, completed };
}

function getLastActivityLabel(request, comments = currentComments) {
    if (comments && comments.length > 0) {
        const lastComment = comments[comments.length - 1];
        return `${lastComment.full_name || lastComment.username} · ${getDateTimeLabel(lastComment.created_at)}`;
    }

    if (request?.created_at) {
        return `Creada · ${getDateTimeLabel(request.created_at)}`;
    }

    return 'Sin actividad';
}

function renderActivityTimeline(request) {
    const timeline = document.getElementById('edit-activity-timeline');
    const lastActivityEl = document.getElementById('edit-last-activity');
    const primaryOwnerEl = document.getElementById('edit-primary-owner');

    if (lastActivityEl) {
        lastActivityEl.textContent = getLastActivityLabel(request);
    }

    if (primaryOwnerEl) {
        primaryOwnerEl.textContent = getPrimaryOwnerLabel(request);
    }

    if (!timeline) return;

    const items = [
        {
            type: 'created',
            icon: 'iconoir-plus-circle',
            title: 'Mejora creada',
            meta: `${request.creator_name || request.creator_username || 'Sistema'} · ${getDateTimeLabel(request.created_at)}`
        }
    ];

    if (request.assignments && request.assignments.length > 0) {
        items.push({
            type: 'owner',
            icon: 'iconoir-user-badge-check',
            title: `Responsable principal: ${getPrimaryOwnerLabel(request)}`,
            meta: `${request.assignments.length} persona(s) asignada(s)`
        });
    }

    currentComments.forEach(comment => {
        items.push({
            type: 'comment',
            icon: 'iconoir-chat-bubble',
            title: escapeHtml(comment.full_name || comment.username),
            meta: getDateTimeLabel(comment.created_at),
            content: escapeHtml(comment.content)
        });
    });

    timeline.innerHTML = items.map(item => `
        <div class="activity-timeline-item activity-${item.type}">
            <div class="activity-timeline-icon">
                <i class="${item.icon}"></i>
            </div>
            <div class="activity-timeline-content">
                <div class="activity-timeline-title">${item.title}</div>
                <div class="activity-timeline-meta">${item.meta}</div>
                ${item.content ? `<div class="activity-timeline-text">${item.content}</div>` : ''}
            </div>
        </div>
    `).join('');
}

function updateEditRequestSummary(request) {
    const statusEl = document.getElementById('edit-summary-status');
    const priorityEl = document.getElementById('edit-summary-priority');
    const difficultyEl = document.getElementById('edit-summary-difficulty');
    const createdEl = document.getElementById('edit-summary-created');
    const ageEl = document.getElementById('edit-summary-age');
    const commentsEl = document.getElementById('edit-summary-comments');
    const attachmentsEl = document.getElementById('edit-summary-attachments');
    const checklistEl = document.getElementById('edit-summary-checklist');
    const checklistProgress = getChecklistProgress(request);

    if (statusEl) statusEl.textContent = getStatusLabel(request.status);
    if (priorityEl) priorityEl.textContent = getPriorityLabel(request.priority);
    if (difficultyEl) difficultyEl.textContent = getDifficultyLabel(request.difficulty);
    if (createdEl) createdEl.textContent = new Date(request.created_at).toLocaleDateString('es-ES');
    if (ageEl) ageEl.textContent = getRelativeAge(request.created_at);
    if (commentsEl) commentsEl.textContent = request.comment_count || 0;
    if (attachmentsEl) attachmentsEl.textContent = request.attachment_count || 0;
    if (checklistEl) checklistEl.textContent = `${checklistProgress.completed}/${checklistProgress.total}`;

    renderActivityTimeline(request);
}

// Helper to update active nav state by app/company
function updateActiveNavItem(appId, companyId = null) {
    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
    document.querySelectorAll('.company-nav-item').forEach(i => i.classList.remove('active'));
    document.querySelectorAll('.quick-action-btn').forEach(b => b.classList.remove('active'));
    
    if (appId) {
        const navItem = document.querySelector(`.nav-item[data-app-id="${appId}"]`);
        if (navItem) {
            navItem.classList.add('active');
        }
    }

    if (companyId) {
        const companyNavItem = document.querySelector(`.company-nav-item[data-company-id="${companyId}"]`);
        if (companyNavItem) {
            companyNavItem.classList.add('active');
        }
    }
}

// Load all apps (grouped by company)
let appsGrouped = [];
async function loadApps() {
    try {
        const response = await fetch('/api/apps.php?grouped=1');
        const data = await response.json();

        if (data.success) {
            appsGrouped = data.data;
            // Flatten for compatibility with existing code
            apps = [];
            appsGrouped.forEach(group => {
                if (group.apps) {
                    apps.push(...group.apps);
                }
            });
            renderAppsNav();
            populateAppSelects();
        }
    } catch (error) {
        console.error('Error loading apps:', error);
    }
}

// Render apps in sidebar navigation (grouped by company)
function renderAppsNav() {
    const appsNav = document.getElementById('apps-nav');

    let navContent = '<div class="nav-section-title">Aplicaciones</div>';

    if (appsGrouped.length > 0) {
        navContent += appsGrouped.map(group => `
            <div class="company-group ${appsGrouped.length === 1 ? 'single-company' : ''}" data-company-id="${group.id}">
                <div class="company-group-header">
                    <a href="javascript:void(0)" class="company-nav-item" onclick="event.preventDefault(); loadView('company', ${group.id}, event); return false;" data-company-id="${group.id}">
                        <div class="company-group-title">
                            <i class="iconoir-building"></i>
                            <span>${escapeHtml(group.name)}</span>
                        </div>
                    </a>
                    <button type="button" class="company-group-toggle-btn" onclick="event.stopPropagation(); toggleCompanyGroup(${group.id})" title="Expandir/contraer apps">
                        <i class="iconoir-nav-arrow-down company-group-toggle"></i>
                    </button>
                </div>
                <div class="company-group-apps" style="max-height: none;">
                    ${group.apps.map(app => `
                        <a href="javascript:void(0)" class="nav-item" onclick="event.preventDefault(); loadView('app', ${app.id}, event); return false;" data-app-id="${app.id}">
                            <i class="iconoir-app-window"></i>
                            <span>${escapeHtml(app.name)}</span>
                        </a>
                    `).join('')}
                </div>
            </div>
        `).join('');
    } else {
        navContent += apps.map(app => `
            <a href="javascript:void(0)" class="nav-item" onclick="event.preventDefault(); loadView('app', ${app.id}, event); return false;" data-app-id="${app.id}">
                <i class="iconoir-app-window"></i>
                <span>${escapeHtml(app.name)}</span>
            </a>
        `).join('');
    }

    appsNav.innerHTML = navContent;
}

// Toggle company group collapse/expand
function toggleCompanyGroup(companyId) {
    const group = document.querySelector(`.company-group[data-company-id="${companyId}"]`);
    if (group) {
        group.classList.toggle('collapsed');
    }
}

// Populate app select dropdowns
function populateAppSelects() {
    const selects = document.querySelectorAll('#request-app');
    selects.forEach(select => {
        select.innerHTML = '<option value="">Selecciona una app</option>' +
            apps.map(app => `<option value="${app.id}">${escapeHtml(app.name)}</option>`).join('');
    });
}

// Switch view (global, company or specific app)
function loadView(type, entityId = null, sourceEvent = null) {
    currentView = type;
    currentAppId = type === 'app' ? entityId : null;
    currentCompanyId = type === 'company' ? entityId : null;

    // Update page title
    const pageTitle = document.getElementById('page-title');
    if (type === 'global') {
        pageTitle.textContent = 'Vista Global';
        // Update URL without reload
        history.pushState({view: 'global'}, '', '/index.php');
    } else if (type === 'company') {
        const company = appsGrouped.find(g => g.id == entityId);
        pageTitle.textContent = company ? company.name : 'Empresa';
        history.pushState({view: 'company', companyId: entityId}, '', `/index.php?company=${entityId}`);
    } else {
        const app = apps.find(a => a.id == entityId);
        pageTitle.textContent = app ? app.name : 'App';
        // Update URL without reload
        history.pushState({view: 'app', appId: entityId}, '', `/index.php?app=${entityId}`);
    }

    // Update active nav items and quick action buttons
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelectorAll('.company-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelectorAll('.quick-action-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Add active to the clicked element or find it by data attribute
    const evt = sourceEvent || window.event;
    if (evt && evt.target) {
        const clickedElement = evt.target.closest('.nav-item, .quick-action-btn, .company-nav-item');
        if (clickedElement) {
            clickedElement.classList.add('active');
        }
    } else if (type === 'global') {
        // Activate global view button
        const globalBtn = document.querySelector('.quick-action-btn[title="Vista Global"]');
        if (globalBtn) {
            globalBtn.classList.add('active');
        }
    } else if (type === 'app' && currentAppId) {
        updateActiveNavItem(currentAppId, null);
    } else if (type === 'company' && currentCompanyId) {
        updateActiveNavItem(null, currentCompanyId);
    }

    // Show/hide app info section (links, notes, files)
    const appInfoSection = document.getElementById('app-info-section');
    if (appInfoSection) {
        if ((type === 'app' && currentAppId) || (type === 'company' && currentCompanyId)) {
            appInfoSection.style.display = 'block';
            loadAppResources();
        } else {
            appInfoSection.style.display = 'none';
        }
    }

    // Update pending count for admins
    if ((userRole === 'admin' || userRole === 'superadmin') && typeof updatePendingCount === 'function') {
        updatePendingCount();
    }

    // Reload requests
    loadRequests();
}

// Load requests with multi-level sorting and filters
async function loadRequests() {
    try {
        const sortPrimary = document.getElementById('sort-primary')?.value || 'votes';
        const sortSecondary = document.getElementById('sort-secondary')?.value || '';
        const sortTertiary = document.getElementById('sort-tertiary')?.value || '';
        const priority = document.getElementById('priority-filter')?.value || '';
        const status = document.getElementById('status-filter')?.value || '';
        const difficulty = document.getElementById('difficulty-filter')?.value || '';

        let url = '/api/requests.php?';
        const params = [];

        if (currentView === 'app' && currentAppId) {
            params.push(`app_id=${currentAppId}`);
        }
        if (currentView === 'company' && currentCompanyId) {
            params.push(`company_id=${currentCompanyId}`);
        }

        // Multi-level sorting
        params.push(`sort_primary=${sortPrimary}`);
        if (sortSecondary) params.push(`sort_secondary=${sortSecondary}`);
        if (sortTertiary) params.push(`sort_tertiary=${sortTertiary}`);

        // Filters
        if (priority) params.push(`priority=${priority}`);
        if (status) params.push(`status=${status}`);
        if (difficulty) params.push(`difficulty=${difficulty}`);

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
    const tableBody = document.getElementById('requests-table-body');

    const filteredRequests = getFilteredRequests();
    const activeRequests = filteredRequests.filter(r =>
        r.status === 'pending' || r.status === 'in_progress'
    );
    const finishedRequests = filteredRequests.filter(r =>
        r.status === 'completed' || r.status === 'discarded'
    );

    updateRequestsSummary(filteredRequests);
    syncRequestsViewModeUI();

    if (filteredRequests.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                <h3>No hay resultados</h3>
                <p>Prueba a ajustar la búsqueda o limpiar los filtros activos.</p>
            </div>
        `;
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="requests-table-empty">No hay resultados para los filtros actuales</td>
                </tr>
            `;
        }
        return;
    }

    if (tableBody) {
        if (activeRequests.length > 0) {
            renderRequestsTable(activeRequests, tableBody);
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="requests-table-empty">No hay solicitudes activas para los filtros actuales</td>
                </tr>
            `;
        }
    }

    if (currentRequestsViewMode === 'table') {
        grid.innerHTML = '';
        return;
    }

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
    const canChangeStatus = ['admin', 'superadmin', 'programador'].includes(userRole);
    const canEdit = ['admin', 'superadmin', 'programador'].includes(userRole);
    const canDelete = ['admin', 'superadmin'].includes(userRole);
    const primaryOwnerLabel = getPrimaryOwnerLabel(request);
    const requestAgeLabel = getRelativeAge(request.created_at);
    const hasComments = parseInt(request.comment_count || 0, 10) > 0;
    const checklistProgress = getChecklistProgress(request);

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
                    ${(currentView !== 'app' && request.app_name) ? escapeHtml(request.app_name) + ' - ' : ''}${escapeHtml(request.title)}
                </h3>
            </div>
            
            ${canChangeStatus ? `
                <div style="display: flex; align-items: center; gap: var(--spacing-sm); flex-shrink: 0;">
                    ${isAdminOrSuperadmin ? `
                        <div class="difficulty-indicator" title="Dificultad">
                            <button class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 1 ? 'active' : ''}" 
                                    onclick="setDifficulty(${request.id}, 'low', event)" title="Baja"></button>
                            <button class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 2 ? 'active' : ''}" 
                                    onclick="setDifficulty(${request.id}, 'medium', event)" title="Media"></button>
                            <button class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 3 ? 'active' : ''}" 
                                    onclick="setDifficulty(${request.id}, 'high', event)" title="Alta"></button>
                        </div>
                    ` : request.difficulty ? `
                        <div class="difficulty-display" style="flex-shrink: 0;" title="Dificultad: ${getDifficultyLabel(request.difficulty)}">
                            <div class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 1 ? 'active' : ''}"></div>
                            <div class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 2 ? 'active' : ''}"></div>
                            <div class="difficulty-bar ${getDifficultyLevel(request.difficulty) >= 3 ? 'active' : ''}"></div>
                        </div>
                    ` : ''}
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

        <div class="request-card-insights">
            <span class="request-card-insight">
                <i class="iconoir-hourglass"></i>
                <span>${requestAgeLabel}</span>
            </span>
            <span class="request-card-insight ${request.assignments && request.assignments.length > 0 ? 'is-highlight' : ''}">
                <i class="iconoir-user-badge-check"></i>
                <span>${escapeHtml(primaryOwnerLabel)}</span>
            </span>
            ${hasComments ? `
                <span class="request-card-insight is-highlight">
                    <i class="iconoir-chat-bubble"></i>
                    <span>Actividad</span>
                </span>
            ` : ''}
            ${checklistProgress.total > 0 ? `
                <span class="request-card-insight ${checklistProgress.completed === checklistProgress.total ? 'is-highlight' : ''}">
                    <i class="iconoir-check-square"></i>
                    <span>${checklistProgress.completed}/${checklistProgress.total}</span>
                </span>
            ` : ''}
        </div>

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

        <div class="card-footer" style="flex-direction: column; gap: var(--spacing-sm);">
            <div style="display: flex; align-items: center; gap: var(--spacing-sm); width: 100%; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 4px;" title="Creado por">
                    <i class="iconoir-user" style="color: var(--text-muted); font-size: 0.75rem;"></i>
                    <span class="text-small text-muted">${escapeHtml(request.creator_name || request.creator_username || 'Anónimo')}</span>
                </div>

                ${request.assignments && request.assignments.length > 0 ? `
                    ${request.assignments.map((a, index) => `
                        <span class="assigned-tag ${index === 0 ? 'primary-owner-tag' : ''}" title="Asignado a ${escapeHtml(a.full_name || a.username)}">
                            ${escapeHtml(a.full_name || a.username)}${index === 0 ? ' · Resp.' : ''}
                        </span>
                    `).join('')}
                ` : (canEdit ? `
                    <button class="btn-assign-quick" onclick="openAssignModal(${request.id}, event)" title="Asignar">
                        <i class="iconoir-user-plus"></i>
                        <span>Asignar</span>
                    </button>
                ` : '')}

                ${request.comment_count > 0 ? `
                    <div style="display: flex; align-items: center; gap: 2px;" title="${request.comment_count} comentario(s)">
                        <i class="iconoir-chat-bubble" style="color: var(--text-muted); font-size: 0.75rem;"></i>
                        <span class="text-small text-muted">${request.comment_count}</span>
                    </div>
                ` : ''}

                ${request.attachment_count > 0 ? `
                    <div style="display: flex; align-items: center; gap: 2px;" title="${request.attachment_count} adjunto(s)">
                        <i class="iconoir-attachment" style="color: var(--text-muted); font-size: 0.75rem;"></i>
                        <span class="text-small text-muted">${request.attachment_count}</span>
                    </div>
                ` : ''}
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div class="vote-section" style="margin: 0;">
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
                ${canEdit ? `
                    <div style="display: flex; align-items: center; gap: var(--spacing-xs);">
                        ${userRole === 'superadmin' && (request.status === 'pending' || request.status === 'in_progress') ? `
                            <button class="quick-action-btn" onclick="openQuickCompleteAndSchedule(${request.id}, '${escapeHtml(request.title)}', ${request.app_id || 'null'})" 
                                    title="Completar y Programar" style="color: #22c55e;">
                                <i class="iconoir-rocket"></i>
                            </button>
                        ` : ''}
                        <button class="quick-action-btn edit" onclick="openEditRequestModal(${request.id})" title="Editar">
                            <i class="iconoir-edit"></i>
                        </button>
                        ${canDelete ? `
                            <button class="quick-action-btn delete" onclick="deleteRequest(${request.id})" title="Eliminar" style="color: var(--color-red);">
                                <i class="iconoir-xmark"></i>
                            </button>
                        ` : ''}
                    </div>
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
    if (modalId === 'edit-request-modal') {
        currentChecklistItems = [];
        const checklistInput = document.getElementById('edit-checklist-input');
        const checklistList = document.getElementById('edit-checklist-list');
        if (checklistInput) checklistInput.value = '';
        if (checklistList) checklistList.innerHTML = '';
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

            // Store current request's app_id for "Complete and Schedule" flow
            window.currentRequestAppId = request.app_id;

            // Set requester info if available
            const requesterNameField = document.getElementById('edit-request-requester-name');
            const requesterEmailField = document.getElementById('edit-request-requester-email');
            if (requesterNameField) requesterNameField.value = request.requester_name || '';
            if (requesterEmailField) requesterEmailField.value = request.requester_email || '';

            // Update requester dot and section state
            updateRequesterDot('edit');
            const editRequesterSection = document.getElementById('edit-requester-section');
            if (editRequesterSection) {
                if (request.requester_name || request.requester_email) {
                    editRequesterSection.classList.add('active');
                } else {
                    editRequesterSection.classList.remove('active');
                }
            }

            // Load attachments
            await loadRequestAttachments(requestId);

            // Load checklist
            await loadChecklistItems(requestId);

            // Load comments
            await loadComments(requestId);

            // Update operational summary
            updateEditRequestSummary(request);

            // Setup mentions autocomplete
            const commentInput = document.getElementById('edit-comment-input');
            const mentionsDropdown = document.getElementById('edit-mentions-dropdown');
            if (commentInput && mentionsDropdown) {
                setupMentionsAutocomplete(commentInput, mentionsDropdown);
            }

            // Setup multi-select assignments
            const assignedTags = document.getElementById('edit-assigned-tags');
            const assignSearch = document.getElementById('edit-assign-search');
            if (assignedTags && assignSearch) {
                // Store current assignments
                window.currentAssignments = (request.assignments || []).map(a => ({
                    id: a.id,
                    username: a.username,
                    full_name: a.full_name
                }));
                renderAssignedTags();
                setupAssignSearchInput();
            }

            // Open modal
            document.getElementById('edit-request-modal').classList.add('active');
        }
    } catch (error) {
        console.error('Error loading request:', error);
        alert('Error al cargar la petición');
    }
}

// Load attachments for a request
async function loadRequestAttachments(requestId) {
    const container = document.getElementById('edit-attachments-list');
    const countEl = document.getElementById('edit-attachment-count');
    
    if (!container) return;
    
    try {
        const response = await fetch(`/api/attachments.php?request_id=${requestId}`);
        const data = await response.json();
        
        if (data.success) {
            const attachments = data.data;
            
            if (attachments.length === 0) {
                container.innerHTML = '<p class="text-muted text-small">No hay archivos adjuntos</p>';
                if (countEl) countEl.textContent = '';
            } else {
                if (countEl) countEl.textContent = `${attachments.length} archivo${attachments.length > 1 ? 's' : ''}`;
                container.innerHTML = attachments.map(att => `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: var(--bg-secondary); border-radius: var(--radius-sm); margin-bottom: 0.5rem;">
                        <a href="/${att.file_path}" target="_blank" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none; color: var(--text-primary); flex: 1; overflow: hidden;">
                            <i class="${getFileIcon(att.mime_type)}" style="color: var(--primary-color);"></i>
                            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(att.original_filename)}">
                                ${escapeHtml(att.original_filename)}
                            </span>
                            <span class="text-muted text-small">(${formatFileSize(att.file_size)})</span>
                        </a>
                        <button type="button" class="btn btn-sm" onclick="deleteAttachment(${att.id}, ${requestId})" style="padding: 0.25rem 0.5rem; color: var(--text-muted);" title="Eliminar">
                            <i class="iconoir-trash"></i>
                        </button>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Error loading attachments:', error);
        container.innerHTML = '<p class="text-muted text-small">Error al cargar archivos</p>';
    }
}

// Get icon based on mime type
function getFileIcon(mimeType) {
    if (mimeType.startsWith('image/')) return 'iconoir-media-image';
    if (mimeType === 'application/pdf') return 'iconoir-page';
    if (mimeType.includes('word')) return 'iconoir-page';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'iconoir-table-2-columns';
    return 'iconoir-attachment';
}

// Delete attachment
async function deleteAttachment(attachmentId, requestId) {
    if (!confirm('¿Eliminar este archivo?')) return;
    
    try {
        const response = await fetch('/api/attachments.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: attachmentId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadRequestAttachments(requestId);
            showToast({
                title: 'Archivo eliminado',
                message: 'El archivo se ha eliminado correctamente',
                icon: 'iconoir-check'
            }, 'toast-completed');
        } else {
            alert(data.error || 'Error al eliminar el archivo');
        }
    } catch (error) {
        console.error('Error deleting attachment:', error);
        alert('Error al eliminar el archivo');
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
            // Save assignments via separate API
            if (window.currentAssignments !== undefined) {
                const assignmentIds = window.currentAssignments.map(a => a.id);
                await fetch('/api/assignments.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        request_id: parseInt(requestId),
                        user_ids: assignmentIds
                    })
                });
            }

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
                'programador': 'Programador',
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

// ========== App Files Functions ==========

// Helper to update requester dot status
function updateRequesterDot(type) {
    const name = document.getElementById(`${type}-request-requester-name`).value;
    const email = document.getElementById(`${type}-request-requester-email`).value;
    const dot = document.getElementById(`${type}-requester-dot`);
    if (dot) {
        if (name || email) {
            dot.classList.add('active');
        } else {
            dot.classList.remove('active');
        }
    }
}

// Setup app files upload in index.php
function setupAppFilesUpload() {
    const uploadArea = document.getElementById('app-files-upload');
    const fileInput = document.getElementById('app-file-input');
    
    if (!uploadArea || !fileInput) return;
    
    uploadArea.addEventListener('click', () => fileInput.click());
    
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--primary-color)';
    });
    
    uploadArea.addEventListener('dragleave', () => {
        uploadArea.style.borderColor = 'var(--border-color)';
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--border-color)';
        handleAppFileUpload(e.dataTransfer.files);
    });
    
    fileInput.addEventListener('change', () => {
        handleAppFileUpload(fileInput.files);
        fileInput.value = '';
    });
}

// Handle app file upload
async function handleAppFileUpload(files) {
    if (!currentAppId) return;
    
    for (const file of files) {
        const formData = new FormData();
        formData.append('app_id', currentAppId);
        formData.append('file', file);
        
        try {
            const response = await fetch('/api/app-files.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast({
                    title: 'Archivo subido',
                    message: file.name,
                    icon: 'iconoir-check'
                }, 'toast-completed');
            } else {
                showToast({
                    title: 'Error',
                    message: data.error || 'No se pudo subir el archivo',
                    icon: 'iconoir-warning-circle'
                }, 'toast-error');
            }
        } catch (error) {
            console.error('Error uploading file:', error);
        }
    }
    
    await loadAppResources();
}

// Setup file upload for edit modal
function setupEditModalFileUpload() {
    const editArea = document.getElementById('edit-file-upload-area');
    const editInput = document.getElementById('edit-file-input');
    
    if (editArea && editInput) {
        editArea.onclick = () => editInput.click();
        
        editInput.onchange = async (e) => {
            const requestId = document.getElementById('edit-request-id').value;
            if (!requestId) return;
            
            const files = e.target.files;
            if (files.length === 0) return;
            
            const formData = new FormData();
            formData.append('request_id', requestId);
            for (let i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }
            
            try {
                editArea.style.opacity = '0.5';
                editArea.style.pointerEvents = 'none';
                
                const response = await fetch('/api/upload.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    await loadRequestAttachments(requestId);
                    editInput.value = ''; // Reset input
                } else {
                    alert(data.error || 'Error al subir archivos');
                }
            } catch (error) {
                console.error('Error uploading files:', error);
                alert('Error al subir archivos');
            } finally {
                editArea.style.opacity = '1';
                editArea.style.pointerEvents = 'auto';
            }
        };

        // Drag and drop for edit modal
        editArea.ondragover = (e) => {
            e.preventDefault();
            editArea.classList.add('dragover');
        };
        editArea.ondragleave = () => editArea.classList.remove('dragover');
        editArea.ondrop = async (e) => {
            e.preventDefault();
            editArea.classList.remove('dragover');
            
            const requestId = document.getElementById('edit-request-id').value;
            if (!requestId) return;
            
            const files = e.dataTransfer.files;
            if (files.length === 0) return;
            
            const formData = new FormData();
            formData.append('request_id', requestId);
            for (let i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }
            
            try {
                editArea.style.opacity = '0.5';
                const response = await fetch('/api/upload.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    await loadRequestAttachments(requestId);
                } else {
                    alert(data.error || 'Error al subir archivos');
                }
            } catch (error) {
                console.error('Error uploading files:', error);
            } finally {
                editArea.style.opacity = '1';
            }
        };
    }
}

// Get file icon class based on mime type
function getFileIconClass(mimeType) {
    if (!mimeType) return 'iconoir-file';
    if (mimeType.startsWith('image/')) return 'iconoir-media-image';
    if (mimeType === 'application/pdf') return 'iconoir-page';
    if (mimeType.includes('word')) return 'iconoir-page';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'iconoir-table-2-columns';
    if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('tar')) return 'iconoir-archive';
    if (mimeType.startsWith('video/')) return 'iconoir-media-video';
    if (mimeType.startsWith('audio/')) return 'iconoir-music-double-note';
    return 'iconoir-file';
}

// Format date
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return 'Hoy';
    if (diffDays === 1) return 'Ayer';
    if (diffDays < 7) return `Hace ${diffDays} días`;
    
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
}

// ========== Floating Task Button ==========

function toggleFloatingTaskInput() {
    const btn = document.getElementById('floating-task-btn');
    const input = document.getElementById('floating-task-input');
    const titleInput = document.getElementById('floating-task-title');
    
    btn.classList.toggle('active');
    input.classList.toggle('active');
    
    if (input.classList.contains('active')) {
        titleInput.focus();
    }
}

function handleFloatingTaskKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        submitFloatingTask();
    } else if (e.key === 'Escape') {
        toggleFloatingTaskInput();
    }
}

async function submitFloatingTask() {
    const titleInput = document.getElementById('floating-task-title');
    const title = titleInput.value.trim();
    
    if (!title) return;
    
    try {
        const response = await fetch('/api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title })
        });
        
        const data = await response.json();
        
        if (data.success) {
            titleInput.value = '';
            toggleFloatingTaskInput();
            showToast({
                title: 'Tarea creada',
                message: title,
                icon: 'iconoir-check'
            }, 'toast-completed');
        } else {
            showToast({
                title: 'Error',
                message: data.error || 'No se pudo crear la tarea',
                icon: 'iconoir-warning-circle'
            }, 'toast-error');
        }
    } catch (error) {
        console.error('Error creating task:', error);
    }
}

// ===========================================
// Complete and Schedule Release (Superadmin)
// ===========================================

function openCompleteAndScheduleModal() {
    const modal = document.getElementById('complete-schedule-modal');
    if (!modal) return;
    
    // Use the stored app_id from the edit modal or from quick action
    const appId = window.currentRequestAppId || window.currentAppId || null;
    window.currentProcessingAppId = appId;
    
    // Set default date to today
    const today = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    document.getElementById('schedule-announce-date').value = todayStr;
    document.getElementById('schedule-description').value = '';
    
    modal.classList.add('active');
}

function openQuickCompleteAndSchedule(requestId, title, appId) {
    // Populate the hidden fields in the edit modal just in case (we need the ID and Title)
    document.getElementById('edit-request-id').value = requestId;
    document.getElementById('edit-request-title').value = title;
    
    // Store the specific app_id for this request
    window.currentProcessingAppId = appId;
    
    // Open the schedule modal directly
    openCompleteAndScheduleModal();
}

async function executeCompleteAndSchedule() {
    const requestId = document.getElementById('edit-request-id').value;
    const requestTitle = document.getElementById('edit-request-title').value;
    const announceDate = document.getElementById('schedule-announce-date').value;
    const description = document.getElementById('schedule-description').value;
    
    if (!announceDate) {
        alert('Por favor selecciona una fecha de anuncio');
        return;
    }
    
    // Use the specific app_id we stored
    const appId = window.currentProcessingAppId;
    
    try {
        // Step 1: Mark request as completed
        const completeResponse = await fetch('/api/requests.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: parseInt(requestId),
                status: 'completed'
            })
        });
        
        if (!completeResponse.ok) {
            throw new Error('Error al completar la solicitud');
        }
        
        // Step 2: Create the release
        const releaseResponse = await fetch('/api/releases.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                title: requestTitle,
                announce_at: announceDate,
                description: description || null,
                app_id: appId
            })
        });
        
        if (!releaseResponse.ok) {
            throw new Error('Error al crear el release');
        }
        
        // Success!
        closeModal('complete-schedule-modal');
        closeModal('edit-request-modal');
        loadRequests();
        
        showToast({
            title: '¡Completado y programado!',
            message: `Release programado para ${formatDateForToast(announceDate)}`,
            icon: 'iconoir-rocket'
        }, 'toast-completed');
        
    } catch (error) {
        console.error('Error:', error);
        alert(error.message || 'Error al procesar la solicitud');
    }
}

function formatDateForToast(dateStr) {
    const [year, month, day] = dateStr.split('-');
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `${parseInt(day)} ${months[parseInt(month) - 1]}`;
}

// ===========================================
// App Resources (Files, Links, Notes)
// ===========================================

let appResourcesData = { files: [], links: [], notes: [] };

function updateResourceActionsVisibility() {
    const canCreate = currentView === 'app' && !!currentAppId;
    const addLinkBtn = document.querySelector('.btn-add-link');
    const addNoteBtn = document.querySelector('.btn-add-note');
    const uploadBox = document.getElementById('app-files-upload');

    if (addLinkBtn) {
        addLinkBtn.style.display = canCreate ? 'flex' : 'none';
    }
    if (addNoteBtn) {
        addNoteBtn.style.display = canCreate ? 'inline-flex' : 'none';
    }
    if (uploadBox) {
        uploadBox.style.display = canCreate ? 'inline-flex' : 'none';
    }
}

// Load all app resources (files, links, notes)
async function loadAppResources() {
    const section = document.getElementById('app-info-section');
    
    if (!currentAppId && !currentCompanyId) {
        if (section) section.style.display = 'none';
        return;
    }
    
    section.style.display = 'block';
    updateResourceActionsVisibility();
    
    // Load files
    await loadAppFiles();
    
    // Load links and notes
    await loadAppLinksAndNotes();
}

// Load app files (compact chips)
async function loadAppFiles() {
    const list = document.getElementById('app-files-list');
    
    if ((!currentAppId && !currentCompanyId) || !list) return;
    
    try {
        const query = currentView === 'company' && currentCompanyId
            ? `company_id=${currentCompanyId}`
            : `app_id=${currentAppId}`;
        const response = await fetch(`/api/app-files.php?${query}`);
        const data = await response.json();
        
        if (data.success) {
            appResourcesData.files = data.data;
            const files = data.data;
            
            if (files.length === 0) {
                list.innerHTML = '';
            } else {
                list.innerHTML = files.map(file => `
                    <div class="app-file-chip" title="${escapeHtml(file.original_filename)} - ${formatFileSize(file.file_size)}">
                        <i class="${getFileIconClass(file.mime_type)}"></i>
                        <a href="/${file.file_path}" target="_blank" class="app-file-chip-name">
                            ${escapeHtml(file.original_filename)}
                        </a>
                        ${currentView === 'company' && file.app_name ? `
                            <span class="app-file-chip-app">${escapeHtml(file.app_name)}</span>
                        ` : ''}
                        <div class="app-file-chip-actions">
                            <button class="app-file-chip-btn edit" onclick="event.preventDefault(); openEditAppFileModal(${file.id}, '${escapeHtml(file.original_filename)}')" title="Editar nombre">
                                <i class="iconoir-edit-pencil"></i>
                            </button>
                            <button class="app-file-chip-btn delete" onclick="event.preventDefault(); deleteAppFile(${file.id})" title="Eliminar">
                                <i class="iconoir-xmark"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Error loading app files:', error);
    }
}

// Open edit app file modal
function openEditAppFileModal(fileId, currentName) {
    document.getElementById('edit-app-file-id').value = fileId;
    document.getElementById('edit-app-file-name').value = currentName;
    document.getElementById('edit-app-file-modal').classList.add('active');
    document.getElementById('edit-app-file-name').focus();
    document.getElementById('edit-app-file-name').select();
}

// Submit edit app file
async function submitEditAppFile(event) {
    event.preventDefault();
    
    const fileId = document.getElementById('edit-app-file-id').value;
    const newName = document.getElementById('edit-app-file-name').value.trim();
    
    if (!newName) return;
    
    try {
        const response = await fetch('/api/app-files.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: fileId,
                original_filename: newName
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeModal('edit-app-file-modal');
            await loadAppFiles();
            showToast({
                title: 'Renombrado',
                message: 'Archivo renombrado correctamente',
                icon: 'iconoir-check'
            }, 'toast-completed');
        } else {
            alert(data.error || 'Error al renombrar archivo');
        }
    } catch (error) {
        console.error('Error renaming app file:', error);
    }
}

// Load app links and notes
async function loadAppLinksAndNotes() {
    const linksList = document.getElementById('app-links-list');
    const notesDisplay = document.getElementById('app-notes-display');
    const linksCount = document.getElementById('links-count');
    
    if (!currentAppId && !currentCompanyId) return;
    
    try {
        const query = currentView === 'company' && currentCompanyId
            ? `company_id=${currentCompanyId}`
            : `app_id=${currentAppId}`;
        const response = await fetch(`/api/app-resources.php?${query}`);
        const data = await response.json();
        
        if (data.success) {
            const resources = data.data;
            const links = resources.filter(r => r.type === 'link');
            const notes = resources.filter(r => r.type === 'note');
            
            appResourcesData.links = links;
            appResourcesData.notes = notes;
            
            // Update links count
            if (linksCount) {
                linksCount.textContent = links.length > 0 ? links.length : '';
            }
            
            // Render links in dropdown
            if (linksList) {
                if (links.length === 0) {
                    linksList.innerHTML = '<div class="app-resources-empty" style="padding: 12px; text-align: center;">No hay enlaces</div>';
                } else {
                    linksList.innerHTML = links.map(link => `
                        <div class="app-link-item">
                            <a href="${escapeHtml(link.content)}" target="_blank" class="app-link-info">
                                <i class="iconoir-link app-link-icon"></i>
                                <div class="app-link-details">
                                    <div class="app-link-title">${escapeHtml(link.title)}</div>
                                    <div class="app-link-url">${escapeHtml(link.content)}</div>
                                    ${currentView === 'company' && link.app_name ? `<div class="app-link-meta">${escapeHtml(link.app_name)}</div>` : ''}
                                </div>
                            </a>
                            <div class="app-link-actions">
                                <button class="app-link-btn delete" onclick="event.stopPropagation(); event.preventDefault(); deleteAppResource(${link.id}, 'link')" title="Eliminar">
                                    <i class="iconoir-trash"></i>
                                </button>
                            </div>
                        </div>
                    `).join('');
                }
            }
            
            // Render notes as prominent cards
            if (notesDisplay) {
                if (notes.length === 0) {
                    notesDisplay.innerHTML = '';
                } else {
                    notesDisplay.innerHTML = notes.map(note => `
                        <div class="app-note-card">
                            <div class="app-note-card-header">
                                <div class="app-note-card-title">
                                    <i class="iconoir-notes"></i>
                                    ${escapeHtml(note.title)}
                                </div>
                                <div class="app-note-card-actions">
                                    <button class="app-note-card-btn delete" onclick="deleteAppResource(${note.id}, 'note')" title="Eliminar">
                                        <i class="iconoir-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="app-note-card-content">${escapeHtml(note.content || '')}</div>
                            <div class="app-note-card-meta">
                                ${currentView === 'company' && note.app_name ? `<span>${escapeHtml(note.app_name)}</span>` : ''}
                                <span>${note.created_by_name || note.created_by_username || 'Usuario'}</span>
                                <span>${formatDate(note.created_at)}</span>
                            </div>
                        </div>
                    `).join('');
                }
            }
        }
    } catch (error) {
        console.error('Error loading app resources:', error);
    }
}

// Toggle links dropdown
function toggleLinksDropdown() {
    const dropdown = document.getElementById('app-links-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('open');
    }
}

// Close links dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('app-links-dropdown');
    if (dropdown && !dropdown.contains(e.target)) {
        dropdown.classList.remove('open');
    }
});


// Delete app file
async function deleteAppFile(fileId) {
    if (!confirm('¿Eliminar este archivo?')) return;
    
    try {
        const response = await fetch('/api/app-files.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: fileId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadAppResources();
            showToast({
                title: 'Eliminado',
                message: 'Archivo eliminado',
                icon: 'iconoir-check'
            }, 'toast-completed');
        } else {
            alert(data.error || 'Error al eliminar');
        }
    } catch (error) {
        console.error('Error deleting file:', error);
    }
}

// Delete app resource (link or note)
async function deleteAppResource(resourceId, type) {
    const typeLabel = type === 'link' ? 'enlace' : 'nota';
    if (!confirm(`¿Eliminar este ${typeLabel}?`)) return;
    
    try {
        const response = await fetch('/api/app-resources.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: resourceId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadAppResources();
            showToast({
                title: 'Eliminado',
                message: data.message || `${typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1)} eliminado`,
                icon: 'iconoir-check'
            }, 'toast-completed');
        } else {
            alert(data.error || 'Error al eliminar');
        }
    } catch (error) {
        console.error('Error deleting resource:', error);
    }
}

// Open add link modal
function openAddLinkModal() {
    if (!currentAppId) {
        alert('Selecciona una aplicación para añadir enlaces');
        return;
    }
    document.getElementById('link-title').value = '';
    document.getElementById('link-url').value = '';
    document.getElementById('add-link-modal').classList.add('active');
    document.getElementById('link-title').focus();
}

// Open add note modal
function openAddNoteModal() {
    if (!currentAppId) {
        alert('Selecciona una aplicación para añadir notas');
        return;
    }
    document.getElementById('note-title').value = '';
    document.getElementById('note-content').value = '';
    document.getElementById('add-note-modal').classList.add('active');
    document.getElementById('note-title').focus();
}

// Submit add link
async function submitAddLink(event) {
    event.preventDefault();
    
    const title = document.getElementById('link-title').value.trim();
    const url = document.getElementById('link-url').value.trim();
    
    if (!title || !url) return;
    
    try {
        const response = await fetch('/api/app-resources.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                app_id: currentAppId,
                type: 'link',
                title: title,
                content: url
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeModal('add-link-modal');
            await loadAppResources();
            switchResourceTab('links');
            showToast({
                title: 'Enlace añadido',
                message: title,
                icon: 'iconoir-link'
            }, 'toast-completed');
        } else {
            alert(data.error || 'Error al añadir enlace');
        }
    } catch (error) {
        console.error('Error adding link:', error);
    }
}

// Submit add note
async function submitAddNote(event) {
    event.preventDefault();
    
    const title = document.getElementById('note-title').value.trim();
    const content = document.getElementById('note-content').value.trim();
    
    if (!title) return;
    
    try {
        const response = await fetch('/api/app-resources.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                app_id: currentAppId,
                type: 'note',
                title: title,
                content: content
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeModal('add-note-modal');
            await loadAppResources();
            switchResourceTab('notes');
            showToast({
                title: 'Nota añadida',
                message: title,
                icon: 'iconoir-notes'
            }, 'toast-completed');
        } else {
            alert(data.error || 'Error al añadir nota');
        }
    } catch (error) {
        console.error('Error adding note:', error);
    }
}

// View note in modal
function viewNote(noteId) {
    const note = appResourcesData.notes.find(n => n.id == noteId);
    if (!note) return;
    
    document.getElementById('view-note-title').textContent = note.title;
    document.getElementById('view-note-content').textContent = note.content || '(Sin contenido)';
    document.getElementById('view-note-modal').classList.add('active');
}

// ===================
// Export Functions
// ===================

function openExportModal() {
    document.getElementById('export-company').value = '';
    document.getElementById('export-modal').classList.add('active');
}

function exportRequests() {
    const companyId = document.getElementById('export-company').value;
    
    if (!companyId) {
        alert('Por favor, selecciona una empresa');
        return;
    }
    
    // Trigger download via direct navigation
    window.location.href = `/api/export-requests.php?company_id=${companyId}`;
    
    closeModal('export-modal');
    
    showToast({
        title: 'Exportación iniciada',
        message: 'El archivo CSV se descargará automáticamente',
        icon: 'iconoir-download'
    }, 'toast-completed');
}

// ===================
// Assignment Functions (Multi-select)
// ===================

let availableUsers = [];

async function loadAvailableUsers() {
    try {
        const response = await fetch('/api/users-list.php');
        const data = await response.json();
        if (data.success) {
            availableUsers = data.data;
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// Load users on page load
document.addEventListener('DOMContentLoaded', loadAvailableUsers);

function openAssignModal(requestId, event) {
    if (event) event.stopPropagation();
    
    const modal = document.getElementById('assign-modal');
    document.getElementById('assign-request-id').value = requestId;
    window.assignModalRequestId = requestId;
    
    // Load current assignments for this request
    window.currentAssignments = [];
    
    const userList = document.getElementById('assign-user-list');
    userList.innerHTML = availableUsers.map(user => `
        <div class="assign-dropdown-item" data-user-id="${user.id}" data-user-name="${escapeHtml(user.full_name || user.username)}">
            <div class="comment-avatar" style="width: 24px; height: 24px; font-size: 0.625rem;">
                ${(user.full_name || user.username).charAt(0).toUpperCase()}
            </div>
            <div>
                <div class="mention-item-name">${escapeHtml(user.full_name || user.username)}</div>
                <div class="mention-item-username" style="font-size: 0.75rem;">${escapeHtml(user.username)}</div>
            </div>
        </div>
    `).join('');
    
    userList.querySelectorAll('.assign-dropdown-item').forEach(item => {
        item.onclick = () => quickAssignUser(requestId, parseInt(item.dataset.userId), item.dataset.userName);
    });
    
    modal.classList.add('active');
}

async function quickAssignUser(requestId, userId, name) {
    try {
        // Get current assignments first
        const resp = await fetch(`/api/assignments.php?request_id=${requestId}`);
        const current = await resp.json();
        const currentIds = current.success ? current.data.map(a => a.user_id) : [];
        
        // Add new user if not already assigned
        if (!currentIds.includes(userId)) {
            currentIds.push(userId);
        }
        
        const response = await fetch('/api/assignments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId, user_ids: currentIds })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeModal('assign-modal');
            loadRequests();
            showToast({
                title: 'Asignado',
                message: name + ' asignado correctamente',
                icon: 'iconoir-user-badge-check'
            }, 'toast-completed');
        }
    } catch (error) {
        console.error('Error assigning user:', error);
    }
}

// Multi-select assignment in edit modal
function renderAssignedTags() {
    const container = document.getElementById('edit-assigned-tags');
    if (!container) return;
    
    if (!window.currentAssignments || window.currentAssignments.length === 0) {
        container.innerHTML = '<span class="text-muted text-small">Sin asignar</span>';
        return;
    }
    
    container.innerHTML = window.currentAssignments.map((a, index) => `
        <span class="assigned-tag ${index === 0 ? 'primary-owner-tag' : ''}">
            ${escapeHtml(a.full_name || a.username)}${index === 0 ? ' · Resp.' : ''}
            <span class="remove-tag" onclick="removeAssignment(${a.id})">
                ×
            </span>
        </span>
    `).join('');
}

function removeAssignment(userId) {
    window.currentAssignments = window.currentAssignments.filter(a => a.id !== userId);
    renderAssignedTags();
}

function setupAssignSearchInput() {
    const input = document.getElementById('edit-assign-search');
    const dropdown = document.getElementById('edit-assign-dropdown');
    if (!input || !dropdown) return;
    
    // Remove old listeners by cloning
    const newInput = input.cloneNode(true);
    input.parentNode.replaceChild(newInput, input);
    
    newInput.addEventListener('input', () => {
        const search = newInput.value.toLowerCase().trim();
        if (search.length === 0) {
            dropdown.style.display = 'none';
            return;
        }
        
        const assignedIds = (window.currentAssignments || []).map(a => a.id);
        const filtered = availableUsers.filter(u => 
            !assignedIds.includes(u.id) &&
            (u.username.toLowerCase().includes(search) ||
             (u.full_name && u.full_name.toLowerCase().includes(search)))
        );
        
        if (filtered.length === 0) {
            dropdown.style.display = 'none';
            return;
        }
        
        dropdown.innerHTML = filtered.slice(0, 5).map(user => `
            <div class="assign-dropdown-item" data-user-id="${user.id}" data-username="${user.username}" data-fullname="${escapeHtml(user.full_name || '')}">
                <div class="comment-avatar" style="width: 20px; height: 20px; font-size: 0.5rem;">
                    ${(user.full_name || user.username).charAt(0).toUpperCase()}
                </div>
                <span class="mention-item-name">${escapeHtml(user.full_name || user.username)}</span>
            </div>
        `).join('');
        dropdown.style.display = 'block';
        
        dropdown.querySelectorAll('.assign-dropdown-item').forEach(item => {
            item.onclick = () => {
                const userId = parseInt(item.dataset.userId);
                const username = item.dataset.username;
                const fullName = item.dataset.fullname;
                
                if (!window.currentAssignments) window.currentAssignments = [];
                window.currentAssignments.push({ id: userId, username, full_name: fullName });
                renderAssignedTags();
                
                newInput.value = '';
                dropdown.style.display = 'none';
            };
        });
    });
    
    newInput.addEventListener('blur', () => {
        setTimeout(() => { dropdown.style.display = 'none'; }, 200);
    });
}

// ===================
// Comments Functions
// ===================

let currentComments = [];

async function loadComments(requestId) {
    try {
        const response = await fetch(`/api/comments.php?request_id=${requestId}`);
        const data = await response.json();
        
        if (data.success) {
            currentComments = data.data;
            renderComments(requestId);
        }
    } catch (error) {
        console.error('Error loading comments:', error);
    }
}

function renderComments(requestId) {
    const container = document.getElementById('edit-comments-list');
    const countEl = document.getElementById('edit-comments-count');
    if (!container) return;

    const activeRequest = requests.find(r => parseInt(r.id, 10) === parseInt(requestId, 10));
    if (activeRequest) {
        renderActivityTimeline(activeRequest);
    }
    
    if (countEl) countEl.textContent = currentComments.length > 0 ? `${currentComments.length}` : '';
    
    if (currentComments.length === 0) {
        container.innerHTML = '<p class="text-muted text-small">No hay comentarios todavía</p>';
        return;
    }
    
    container.innerHTML = currentComments.map(comment => {
        const initials = (comment.full_name || comment.username).charAt(0).toUpperCase();
        const date = new Date(comment.created_at).toLocaleDateString('es-ES', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
        const currentUserId = parseInt(document.body.dataset.userId);
        const canModify = comment.user_id === currentUserId || ['admin', 'superadmin'].includes(document.body.dataset.userRole);
        
        // Parse mentions in content - highlight with name
        const contentWithMentions = escapeHtml(comment.content).replace(
            /@(\w+)/g, 
            '<span class="mention">@$1</span>'
        );
        
        return `
            <div class="comment-item" data-comment-id="${comment.id}">
                <div class="comment-avatar">${initials}</div>
                <div class="comment-content">
                    <div class="comment-header">
                        <span class="comment-author">${escapeHtml(comment.full_name || comment.username)}</span>
                        <span class="comment-time">${date}</span>
                    </div>
                    <div class="comment-text">${contentWithMentions}</div>
                    ${canModify ? `
                        <div class="comment-actions">
                            <button class="comment-action-btn" onclick="deleteComment(${comment.id}, ${requestId})">
                                <i class="iconoir-trash"></i> Eliminar
                            </button>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
}

async function submitComment(requestId) {
    const input = document.getElementById('edit-comment-input');
    const content = input.value.trim();
    
    if (!content) return;
    
    try {
        const response = await fetch('/api/comments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_id: requestId,
                content: content
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            input.value = '';
            hideMentionsDropdown(document.getElementById('edit-mentions-dropdown'));
            await loadComments(requestId);
            
            // Update comment count in card
            const card = document.querySelector(`[data-request-id="${requestId}"]`);
            if (card) {
                loadRequests();
            }
        } else {
            alert(data.error || 'Error al añadir comentario');
        }
    } catch (error) {
        console.error('Error adding comment:', error);
    }
}

async function deleteComment(commentId, requestId) {
    if (!confirm('¿Eliminar este comentario?')) return;
    
    try {
        const response = await fetch('/api/comments.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: commentId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadComments(requestId);
        } else {
            alert(data.error || 'Error al eliminar comentario');
        }
    } catch (error) {
        console.error('Error deleting comment:', error);
    }
}

// ===================
// Mentions Autocomplete
// ===================

function setupMentionsAutocomplete(inputElement, dropdownElement) {
    let mentionStart = -1;
    
    // Remove old listeners by cloning
    const newInput = inputElement.cloneNode(true);
    inputElement.parentNode.replaceChild(newInput, inputElement);
    
    // Update references
    newInput.id = inputElement.id;
    
    newInput.addEventListener('input', (e) => {
        const value = e.target.value;
        const cursorPos = e.target.selectionStart;
        
        const beforeCursor = value.substring(0, cursorPos);
        const lastAtIndex = beforeCursor.lastIndexOf('@');
        
        if (lastAtIndex !== -1) {
            const afterAt = beforeCursor.substring(lastAtIndex + 1);
            
            if (!afterAt.includes(' ')) {
                mentionStart = lastAtIndex;
                const searchTerm = afterAt.toLowerCase();
                
                const filtered = availableUsers.filter(u => 
                    u.username.toLowerCase().includes(searchTerm) ||
                    (u.full_name && u.full_name.toLowerCase().includes(searchTerm))
                );
                
                if (filtered.length > 0) {
                    showMentionsDropdown(dropdownElement, filtered, newInput, mentionStart);
                    return;
                }
            }
        }
        
        hideMentionsDropdown(dropdownElement);
    });
    
    newInput.addEventListener('keydown', (e) => {
        if (dropdownElement.style.display === 'block') {
            if (e.key === 'Escape') {
                hideMentionsDropdown(dropdownElement);
            }
        }
    });
}

function showMentionsDropdown(dropdown, users, input, startPos) {
    dropdown.innerHTML = users.slice(0, 5).map(user => `
        <div class="mention-item" data-username="${user.username}">
            <div class="comment-avatar" style="width: 20px; height: 20px; font-size: 0.5rem;">
                ${(user.full_name || user.username).charAt(0).toUpperCase()}
            </div>
            <span class="mention-item-name">${escapeHtml(user.full_name || user.username)}</span>
        </div>
    `).join('');
    
    dropdown.style.display = 'block';
    
    dropdown.querySelectorAll('.mention-item').forEach(item => {
        item.onclick = () => {
            const username = item.dataset.username;
            const value = input.value;
            const newValue = value.substring(0, startPos) + '@' + username + ' ' + value.substring(input.selectionStart);
            input.value = newValue;
            input.focus();
            hideMentionsDropdown(dropdown);
        };
    });
}

function hideMentionsDropdown(dropdown) {
    if (dropdown) dropdown.style.display = 'none';
}

// Inbox/notification functions are in sidebar.js (shared across pages)

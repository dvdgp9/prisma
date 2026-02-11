
// ===== PENDING APPROVALS MANAGEMENT =====

// Update pending count badge
async function updatePendingCount() {
    try {
        const response = await fetch('/api/pending-approvals.php');
        const data = await response.json();

        if (data.success) {
            const count = data.data.length;
            const badge = document.getElementById('pending-count');

            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error updating pending count:', error);
    }
}

// Load pending approvals view
async function loadPendingApprovals() {
    currentView = 'pending';
    currentAppId = null;
    currentCompanyId = null;

    // Update URL without reload
    history.pushState({view: 'pending'}, '', '/index.php#pending');

    // Update UI
    document.getElementById('page-title').textContent = 'Solicitudes Pendientes de Aprobar';
    
    // Remove active from all nav items and quick action buttons
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    document.querySelectorAll('.company-nav-item').forEach(item => item.classList.remove('active'));
    document.querySelectorAll('.quick-action-btn').forEach(btn => btn.classList.remove('active'));
    
    // Add active to pending approvals button
    const pendingNav = document.getElementById('pending-approvals-nav');
    if (pendingNav) pendingNav.classList.add('active');

    try {
        const response = await fetch('/api/pending-approvals.php');
        const data = await response.json();

        if (data.success) {
            renderPendingRequests(data.data);
        }
    } catch (error) {
        console.error('Error loading pending approvals:', error);
    }
}

// Render pending requests with approval actions
function renderPendingRequests(pendingRequests) {
    const grid = document.getElementById('requests-grid');

    // Get company name for the link
    const companyName = document.body.dataset.companyName || 'tu-empresa';
    const publicFormUrl = `${window.location.origin}/solicitud.php?empresa=${encodeURIComponent(companyName)}`;

    // Add share link section at top
    const shareSection = document.createElement('div');
    shareSection.style.gridColumn = '1 / -1';
    shareSection.innerHTML = `
        <div style="background: linear-gradient(135deg, rgba(0, 201, 183, 0.05), rgba(0, 201, 183, 0.02)); border-left: 4px solid var(--primary-color); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; align-items: start; gap: 1rem;">
                <i class="iconoir-share-ios" style="color: var(--primary-color); font-size: 1.5rem; flex-shrink: 0; margin-top: 0.25rem;"></i>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 0.5rem 0; color: var(--text-primary);">Formulario Público de Solicitudes</h3>
                    <p style="margin: 0 0 1rem 0; color: var(--text-secondary); font-size: 0.875rem;">
                        Comparte este enlace con tu equipo para que puedan solicitar mejoras sin necesidad de tener cuenta:
                    </p>
                    <div style="display: flex; gap: var(--spacing-sm); align-items: center;">
                        <input type="text" 
                               id="public-form-url" 
                               value="${publicFormUrl}" 
                               readonly 
                               style="flex: 1; padding: 0.625rem 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: white; font-size: 0.875rem; color: var(--text-primary);">
                        <button onclick="copyPublicFormUrl()" 
                                class="btn btn-primary" 
                                style="white-space: nowrap;">
                            <i class="iconoir-copy"></i>
                            Copiar Enlace
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    if (pendingRequests.length === 0) {
        grid.innerHTML = '';
        grid.appendChild(shareSection);

        const emptyState = document.createElement('div');
        emptyState.style.gridColumn = '1 / -1';
        emptyState.style.textAlign = 'center';
        emptyState.style.padding = '3rem';
        emptyState.style.color = 'var(--text-secondary)';
        emptyState.innerHTML = `
            <i class="iconoir-check-circle" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
            <h3>No hay solicitudes pendientes</h3>
            <p>Todas las solicitudes públicas han sido revisadas.</p>
        `;
        grid.appendChild(emptyState);
        return;
    }

    grid.innerHTML = '';
    grid.appendChild(shareSection);

    pendingRequests.forEach(request => {
        const card = document.createElement('div');
        card.className = 'card';
        card.style.borderLeft = '4px solid var(--secondary-color)';

        card.innerHTML = `
            <div class="card-header">
                <h3 class="card-title">${escapeHtml(request.title)}</h3>
                <span class="priority-badge priority-${request.priority}">
                    EXTERNA
                </span>
            </div>
            
            <p class="card-description">${escapeHtml(request.description)}</p>
            
            <div style="background: var(--bg-secondary); padding: var(--spacing-md); border-radius: var(--radius-md); margin: var(--spacing-md) 0;">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--spacing-sm); font-size: 0.875rem;">
                    <div>
                        <strong style="color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Solicitante:</strong>
                        <span>${escapeHtml(request.requester_name)}</span>
                    </div>
                    <div>
                        <strong style="color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Email:</strong>
                        <a href="mailto:${escapeHtml(request.requester_email)}" style="color: var(--primary-color);">
                            ${escapeHtml(request.requester_email)}
                        </a>
                    </div>
                    <div>
                        <strong style="color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Aplicación:</strong>
                        <span>${escapeHtml(request.app_name)}</span>
                    </div>
                    <div>
                        <strong style="color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Fecha:</strong>
                        <span>${new Date(request.created_at).toLocaleDateString('es-ES')}</span>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: var(--spacing-sm); justify-content: flex-end;">
                <button class="btn btn-outline" onclick="handleApproval(${request.id}, 'reject')" 
                        style="color: var(--secondary-color); border-color: var(--secondary-color);">
                    <i class="iconoir-xmark"></i>
                    Rechazar
                </button>
                <button class="btn btn-primary" onclick="handleApproval(${request.id}, 'approve')">
                    <i class="iconoir-check"></i>
                    Aprobar
                </button>
            </div>
        `;

        grid.appendChild(card);
    });
}

// Handle approval/rejection
async function handleApproval(requestId, action) {
    const confirmMsg = action === 'approve'
        ? '¿Aprobar esta solicitud? Se moverá a mejoras pendientes.'
        : '¿Rechazar esta solicitud? Se eliminará permanentemente.';

    if (!confirm(confirmMsg)) return;

    try {
        const response = await fetch('/api/pending-approvals.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId, action: action })
        });

        const data = await response.json();

        if (data.success) {
            const message = action === 'approve'
                ? 'Solicitud aprobada correctamente'
                : 'Solicitud rechazada';

            showToast({
                title: action === 'approve' ? 'Aprobada' : 'Rechazada',
                message: message,
                icon: action === 'approve' ? 'iconoir-check' : 'iconoir-xmark'
            }, action === 'approve' ? 'toast-completed' : 'toast-discarded');

            // Reload pending approvals
            loadPendingApprovals();
            updatePendingCount();
        } else {
            alert(data.error || 'Error al procesar la solicitud');
        }
    } catch (error) {
        console.error('Error handling approval:', error);
        alert('Error al procesar la solicitud');
    }
}

// Copy public form URL to clipboard
function copyPublicFormUrl() {
    const input = document.getElementById('public-form-url');
    input.select();
    input.setSelectionRange(0, 99999); // For mobile devices

    navigator.clipboard.writeText(input.value).then(() => {
        showToast({
            title: 'Enlace copiado',
            message: 'El enlace del formulario público se ha copiado al portapapeles',
            icon: 'iconoir-check'
        }, 'toast-completed');
    }).catch(err => {
        console.error('Error copying:', err);
        alert('No se pudo copiar el enlace. Por favor, cópialo manualmente.');
    });
}

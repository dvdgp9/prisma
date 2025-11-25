
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

    // Update UI
    document.getElementById('page-title').textContent = 'Solicitudes Pendientes de Aprobar';
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    document.getElementById('pending-approvals-nav').classList.add('active');

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

    if (pendingRequests.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                <i class="iconoir-check-circle" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
                <h3>No hay solicitudes pendientes</h3>
                <p>Todas las solicitudes públicas han sido revisadas.</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = '';

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

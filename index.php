<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prisma Dashboard</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Iconoir Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>

<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = get_logged_user();

// Get company name for public form link
$company_name = $user['company_name'] ?? '';
?>

<body data-user-role="<?php echo htmlspecialchars($user['role']); ?>"
    data-company-name="<?php echo htmlspecialchars($company_name); ?>">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="/assets/images/logo.png" alt="Prisma" class="logo" style="height: 32px; width: auto;">
                <div class="user-info" onclick="openProfileModal()" style="cursor: pointer;" title="Editar perfil">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;">
                            <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                        </div>
                        <div class="text-small text-muted"><?php echo htmlspecialchars($user['role']); ?></div>
                    </div>
                    <i class="iconoir-edit user-info-edit-icon"></i>
                </div>
            </div>

            <nav>
                <div class="nav-section">
                    <div class="nav-section-title">Vistas Generales</div>
                    <a href="javascript:void(0)" class="nav-item active" onclick="loadView('global')">
                        <i class="iconoir-globe"></i>
                        <span>Vista Global</span>
                    </a>
                </div>

                <div class="nav-section" id="apps-nav">
                    <div class="nav-section-title">Aplicaciones</div>
                    <!-- Apps will be loaded dynamically -->
                </div>

                <?php if (has_role('admin')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Administración</div>
                        <a href="#" onclick="loadPendingApprovals(); return false;" class="nav-item"
                            id="pending-approvals-nav">
                            <i class="iconoir-clock"></i>
                            <span>Pendientes Aprobar</span>
                            <span class="badge-count" id="pending-count" style="display: none;"></span>
                        </a>
                        <?php if (has_role('superadmin')): ?>
                            <a href="/admin.php" class="nav-item">
                                <i class="iconoir-shield-check"></i>
                                <span>Panel Admin</span>
                            </a>
                        <?php endif; ?>
                        <a href="/manage-apps.php" class="nav-item">
                            <i class="iconoir-settings"></i>
                            <span>Gestionar Apps</span>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="nav-section">
                    <a href="/logout.php" class="nav-item" style="color: var(--secondary);">
                        <i class="iconoir-log-out"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1 class="page-title" id="page-title">Vista Global</h1>
                <div class="actions">
                    <button class="btn btn-primary" onclick="openNewRequestModal()">
                        <i class="iconoir-plus"></i>
                        Nueva Mejora
                    </button>
                </div>
            </div>

            <!-- Filters and Sorting -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label>Ordenar por</label>
                    <select id="sort-select" onchange="loadRequests()">
                        <option value="votes">Más votadas</option>
                        <option value="status">Estado</option>
                        <option value="priority">Prioridad</option>
                        <option value="date">Más reciente</option>
                        <option value="date_asc">Más antigua</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Prioridad</label>
                    <select id="priority-filter" onchange="loadRequests()">
                        <option value="">Todas</option>
                        <option value="critical">Crítica</option>
                        <option value="high">Alta</option>
                        <option value="medium">Media</option>
                        <option value="low">Baja</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Estado</label>
                    <select id="status-filter" onchange="loadRequests()">
                        <option value="">Todos</option>
                        <option value="pending">Pendiente</option>
                        <option value="in_progress">En Progreso</option>
                        <option value="completed">Completado</option>
                        <option value="discarded">Descartado</option>
                    </select>
                </div>
            </div>

            <!-- Cards Grid -->
            <div class="cards-grid" id="requests-grid">
                <!-- Cards will be loaded dynamically -->
            </div>
        </main>
    </div>

    <!-- New Request Modal -->
    <div class="modal" id="new-request-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Nueva Mejora</h3>
                <button class="close-modal" onclick="closeModal('new-request-modal')">×</button>
            </div>

            <form id="new-request-form" onsubmit="submitNewRequest(event)">
                <div class="form-group">
                    <label for="request-app">Aplicación *</label>
                    <select id="request-app" required>
                        <option value="">Selecciona una app</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="request-title">Título *</label>
                    <input type="text" id="request-title" required placeholder="Ej: Error crítico en login móvil">
                </div>

                <div class="form-group">
                    <label for="request-description">Descripción</label>
                    <textarea id="request-description" rows="5"
                        placeholder="Describe el problema o la funcionalidad solicitada..."></textarea>
                </div>

                <div class="form-group">
                    <label for="request-priority">Prioridad</label>
                    <select id="request-priority">
                        <option value="low">Baja</option>
                        <option value="medium" selected>Media</option>
                        <option value="high">Alta</option>
                        <option value="critical">Crítica</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Adjuntos (opcional)</label>
                    <div class="file-upload-area" id="file-upload-area">
                        <p>📎 Arrastra archivos aquí o haz clic para seleccionar</p>
                        <p class="text-small text-muted">Máximo 5MB - Imágenes, PDF, documentos</p>
                        <input type="file" id="file-input" style="display: none;" multiple>
                    </div>
                    <div id="file-list" style="margin-top: 1rem;"></div>
                </div>

                <!-- Optional requester info -->
                <div style="border-top: 1px solid var(--border-color); margin-top: 1.5rem; padding-top: 1.5rem;">
                    <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;">
                        <strong>Opcional:</strong> Si alguien te solicitó esta mejora, añade sus datos para notificarle:
                    </p>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="request-requester-name">Nombre del solicitante</label>
                            <input type="text" id="request-requester-name" placeholder="Ej: Juan Pérez">
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="request-requester-email">Email del solicitante</label>
                            <input type="email" id="request-requester-email" placeholder="juan@ejemplo.com">
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Crear Petición</button>
                    <button type="button" class="btn btn-outline"
                        onclick="closeModal('new-request-modal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Request Modal (Admin/Superadmin) -->
    <div class="modal" id="edit-request-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Editar Petición</h3>
                <button class="close-modal" onclick="closeModal('edit-request-modal')">×</button>
            </div>

            <form id="edit-request-form" onsubmit="submitEditRequest(event)">
                <input type="hidden" id="edit-request-id">

                <div class="form-group">
                    <label for="edit-request-title">Título *</label>
                    <input type="text" id="edit-request-title" required>
                </div>

                <div class="form-group">
                    <label for="edit-request-description">Descripción</label>
                    <textarea id="edit-request-description" rows="5"></textarea>
                </div>

                <?php if (has_role('admin')): ?>
                    <div class="form-group">
                        <label for="edit-request-priority">Prioridad</label>
                        <select id="edit-request-priority">
                            <option value="low">Baja</option>
                            <option value="medium">Media</option>
                            <option value="high">Alta</option>
                            <option value="critical">Crítica</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit-request-status">Estado</label>
                        <select id="edit-request-status">
                            <option value="pending">Pendiente</option>
                            <option value="in_progress">En Progreso</option>
                            <option value="completed">Completado</option>
                            <option value="discarded">Descartado</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Guardar Cambios</button>
                    <?php if (has_role('superadmin')): ?>
                        <button type="button" class="btn btn-secondary" onclick="deleteRequest()">Eliminar</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline"
                        onclick="closeModal('edit-request-modal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal" id="profile-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Mi Perfil</h3>
                <button class="close-modal" onclick="closeModal('profile-modal')">×</button>
            </div>

            <form id="profile-form" onsubmit="submitProfile(event)">
                <div class="form-group">
                    <label for="profile-username">Usuario *</label>
                    <input type="text" id="profile-username" required>
                </div>

                <div class="form-group">
                    <label for="profile-fullname">Nombre Completo</label>
                    <input type="text" id="profile-fullname" placeholder="Tu nombre completo">
                </div>

                <div class="form-group">
                    <label for="profile-email">Email</label>
                    <input type="email" id="profile-email" placeholder="tu@email.com">
                </div>

                <div class="form-group">
                    <label for="profile-password">Nueva Contraseña</label>
                    <input type="password" id="profile-password" placeholder="Dejar vacío para no cambiar">
                    <small class="text-muted">Solo completa este campo si quieres cambiar tu contraseña</small>
                </div>

                <div
                    style="padding: var(--spacing-md); background: var(--bg-secondary); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-sm);">
                        <span class="text-muted">Rol:</span>
                        <span id="profile-role" style="font-weight: var(--font-weight-semibold);"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Empresa:</span>
                        <span id="profile-company" style="font-weight: var(--font-weight-semibold);"></span>
                    </div>
                </div>

                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Guardar Cambios</button>
                    <button type="button" class="btn btn-outline"
                        onclick="closeModal('profile-modal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toast-container"></div>

    <script src="/assets/js/main.js"></script>
    <?php if (has_role('admin')): ?>
        <script src="/assets/js/pending-approvals.js"></script>
    <?php endif; ?>
</body>

</html>
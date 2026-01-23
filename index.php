<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prisma Dashboard</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" href="/favicon.png?v=2">

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
        <?php $current_page = 'index'; include __DIR__ . '/includes/sidebar.php'; ?>

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

            <!-- Multi-level Sorting Bar -->
            <div class="sorting-bar">
                <div class="sorting-controls">
                    <div class="sort-group">
                        <label class="sort-label">Ordenar por</label>
                        <select id="sort-primary" onchange="loadRequests()" class="sort-select">
                            <option value="votes">Votos</option>
                            <option value="priority">Prioridad</option>
                            <option value="difficulty">Dificultad</option>
                            <option value="status">Estado</option>
                            <option value="date">Fecha (nueva)</option>
                            <option value="date_asc">Fecha (antigua)</option>
                        </select>
                    </div>
                    
                    <span class="sort-separator">→</span>
                    
                    <div class="sort-group">
                        <label class="sort-label">Luego por</label>
                        <select id="sort-secondary" onchange="loadRequests()" class="sort-select">
                            <option value="">Ninguno</option>
                            <option value="votes">Votos</option>
                            <option value="priority" selected>Prioridad</option>
                            <option value="difficulty">Dificultad</option>
                            <option value="status">Estado</option>
                            <option value="date">Fecha (nueva)</option>
                            <option value="date_asc">Fecha (antigua)</option>
                        </select>
                    </div>
                    
                    <span class="sort-separator">→</span>
                    
                    <div class="sort-group">
                        <label class="sort-label">Finalmente por</label>
                        <select id="sort-tertiary" onchange="loadRequests()" class="sort-select">
                            <option value="">Ninguno</option>
                            <option value="votes">Votos</option>
                            <option value="priority">Prioridad</option>
                            <option value="difficulty">Dificultad</option>
                            <option value="status">Estado</option>
                            <option value="date" selected>Fecha (nueva)</option>
                            <option value="date_asc">Fecha (antigua)</option>
                        </select>
                    </div>
                </div>
                
                <button class="btn-filters-toggle" onclick="toggleAdvancedFilters()" id="filters-toggle-btn">
                    <i class="iconoir-filter"></i>
                    Filtros
                </button>
            </div>
            
            <!-- Advanced Filters (Collapsible) -->
            <div class="advanced-filters" id="advanced-filters" style="display: none;">
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
                
                <div class="filter-group">
                    <label>Dificultad</label>
                    <select id="difficulty-filter" onchange="loadRequests()">
                        <option value="">Todas</option>
                        <option value="low">Baja</option>
                        <option value="medium">Media</option>
                        <option value="high">Alta</option>
                    </select>
                </div>
            </div>

            <!-- App Files Section (visible only when viewing specific app) -->
            <div class="app-files-section" id="app-files-section" style="display: none;">
                <div class="app-files-header" onclick="toggleAppFiles()" style="cursor: pointer;">
                    <div class="app-files-title">
                        <i class="iconoir-folder"></i>
                        <span>Archivos del proyecto</span>
                        <span class="app-files-count" id="app-files-count"></span>
                    </div>
                    <button class="btn btn-sm btn-outline">
                        <i class="iconoir-nav-arrow-right" id="app-files-toggle-icon"></i>
                    </button>
                </div>
                <div class="app-files-content collapsed" id="app-files-content">
                    <div class="app-files-list" id="app-files-list">
                        <!-- Files will be loaded here -->
                    </div>
                    <div class="app-files-upload" id="app-files-upload">
                        <i class="iconoir-cloud-upload"></i>
                        <span>Arrastra archivos aquí o haz clic para subir</span>
                        <span class="text-small text-muted">Máximo 10MB por archivo</span>
                        <input type="file" id="app-file-input" style="display: none;" multiple>
                    </div>
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
        <div class="modal-content modal-wide">
            <div class="modal-header">
                <div class="modal-header-left">
                    <i class="iconoir-plus-circle modal-header-icon"></i>
                    <h3 class="modal-title">Nueva Petición</h3>
                </div>
                <button class="close-modal" onclick="closeModal('new-request-modal')">
                    <i class="iconoir-xmark"></i>
                </button>
            </div>

            <form id="new-request-form" onsubmit="submitNewRequest(event)">
                <div class="modal-body-grid">
                    <!-- Left Column: Main Content -->
                    <div class="modal-column-main">
                        <div class="form-group">
                            <label for="request-title">
                                <i class="iconoir-text"></i> Título *
                            </label>
                            <input type="text" id="request-title" required placeholder="Describe brevemente la mejora o problema">
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="request-description">
                                <i class="iconoir-align-left"></i> Descripción
                            </label>
                            <textarea id="request-description" rows="8" placeholder="Explica con detalle el problema, la funcionalidad deseada o los pasos para reproducir un error..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="iconoir-attachment"></i> Adjuntos
                            </label>
                            <div class="file-upload-area" id="file-upload-area">
                                <i class="iconoir-cloud-upload" style="font-size: 2rem; color: var(--text-muted); margin-bottom: 0.5rem;"></i>
                                <p>Arrastra archivos o haz clic para seleccionar</p>
                                <p class="text-small text-muted">Máximo 5MB · Imágenes, PDF, documentos</p>
                                <input type="file" id="file-input" style="display: none;" multiple>
                            </div>
                            <div id="file-list" class="file-list-preview"></div>
                        </div>
                    </div>

                    <!-- Right Column: Metadata -->
                    <div class="modal-column-side">
                        <div class="modal-side-section">
                            <div class="modal-side-title">
                                <div class="modal-side-title-content">
                                    <i class="iconoir-settings"></i> Configuración
                                </div>
                            </div>
                            <div class="modal-side-content">
                                <div class="form-group">
                                    <label for="request-app">Aplicación *</label>
                                    <select id="request-app" required>
                                        <option value="">Selecciona una app</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="request-priority">Prioridad</label>
                                    <select id="request-priority">
                                        <option value="low">🟢 Baja</option>
                                        <option value="medium" selected>🟡 Media</option>
                                        <option value="high">🟠 Alta</option>
                                        <option value="critical">🔴 Crítica</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="request-difficulty">Dificultad estimada</label>
                                    <select id="request-difficulty">
                                        <option value="" selected>Sin definir</option>
                                        <option value="low">Baja</option>
                                        <option value="medium">Media</option>
                                        <option value="high">Alta</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="modal-side-section collapsible" id="new-requester-section">
                            <div class="modal-side-title" onclick="this.parentElement.classList.toggle('active')">
                                <div class="modal-side-title-content">
                                    <i class="iconoir-user"></i> Solicitante
                                    <div class="status-dot" id="new-requester-dot"></div>
                                </div>
                                <i class="iconoir-nav-arrow-right toggle-icon"></i>
                            </div>
                            <div class="modal-side-content">
                                <p class="text-small text-muted" style="margin-bottom: 1rem;">
                                    Si alguien te pidió esta mejora, añade sus datos para notificarle.
                                </p>
                                
                                <div class="form-group">
                                    <label for="request-requester-name">Nombre</label>
                                    <input type="text" id="request-requester-name" placeholder="Juan Pérez" oninput="updateRequesterDot('new')">
                                </div>

                                <div class="form-group">
                                    <label for="request-requester-email">Email</label>
                                    <input type="email" id="request-requester-email" placeholder="juan@ejemplo.com" oninput="updateRequesterDot('new')">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('new-request-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="iconoir-plus"></i> Crear Petición
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Request Modal (Admin/Superadmin) -->
    <div class="modal" id="edit-request-modal">
        <div class="modal-content modal-wide">
            <div class="modal-header">
                <div class="modal-header-left">
                    <i class="iconoir-edit-pencil modal-header-icon"></i>
                    <h3 class="modal-title">Editar Petición</h3>
                    <span class="modal-request-id" id="edit-request-id-display"></span>
                </div>
                <button class="close-modal" onclick="closeModal('edit-request-modal')">
                    <i class="iconoir-xmark"></i>
                </button>
            </div>

            <form id="edit-request-form" onsubmit="submitEditRequest(event)">
                <input type="hidden" id="edit-request-id">

                <div class="modal-body-grid">
                    <!-- Left Column: Main Content -->
                    <div class="modal-column-main">
                        <div class="form-group">
                            <label for="edit-request-title">
                                <i class="iconoir-text"></i> Título *
                            </label>
                            <input type="text" id="edit-request-title" required>
                        </div>

                        <div class="form-group">
                            <label for="edit-request-description">
                                <i class="iconoir-align-left"></i> Descripción
                            </label>
                            <textarea id="edit-request-description" rows="8"></textarea>
                        </div>

                        <!-- Attachments section -->
                        <div class="form-group">
                            <div class="attachments-header">
                                <label>
                                    <i class="iconoir-attachment"></i> Archivos adjuntos
                                </label>
                                <span id="edit-attachment-count" class="badge-count-inline"></span>
                            </div>
                            
                            <!-- Upload area in edit modal -->
                            <div class="file-upload-area" id="edit-file-upload-area" style="padding: var(--spacing-lg); margin-bottom: 1rem;">
                                <i class="iconoir-cloud-upload" style="font-size: 1.5rem; color: var(--text-muted); margin-bottom: 0.25rem;"></i>
                                <p style="font-size: 0.875rem;">Haz clic o arrastra para añadir más archivos</p>
                                <input type="file" id="edit-file-input" style="display: none;" multiple>
                            </div>

                            <div id="edit-attachments-list" class="attachments-grid">
                                <!-- Attachments will be loaded here -->
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Metadata -->
                    <div class="modal-column-side">
                        <?php if (has_role('admin')): ?>
                        <div class="modal-side-section">
                            <div class="modal-side-title">
                                <div class="modal-side-title-content">
                                    <i class="iconoir-settings"></i> Estado y prioridad
                                </div>
                            </div>
                            <div class="modal-side-content">
                                <div class="form-group">
                                    <label for="edit-request-status">Estado</label>
                                    <select id="edit-request-status">
                                        <option value="pending">⏳ Pendiente</option>
                                        <option value="in_progress">🔄 En Progreso</option>
                                        <option value="completed">✅ Completado</option>
                                        <option value="discarded">❌ Descartado</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="edit-request-priority">Prioridad</label>
                                    <select id="edit-request-priority">
                                        <option value="low">🟢 Baja</option>
                                        <option value="medium">🟡 Media</option>
                                        <option value="high">🟠 Alta</option>
                                        <option value="critical">🔴 Crítica</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="edit-request-difficulty">Dificultad</label>
                                    <select id="edit-request-difficulty">
                                        <option value="">Sin definir</option>
                                        <option value="low">Baja</option>
                                        <option value="medium">Media</option>
                                        <option value="high">Alta</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="modal-side-section collapsible" id="edit-requester-section">
                            <div class="modal-side-title" onclick="this.parentElement.classList.toggle('active')">
                                <div class="modal-side-title-content">
                                    <i class="iconoir-user"></i> Solicitante
                                    <div class="status-dot" id="edit-requester-dot"></div>
                                </div>
                                <i class="iconoir-nav-arrow-right toggle-icon"></i>
                            </div>
                            <div class="modal-side-content">
                                <p class="text-small text-muted" style="margin-bottom: 1rem;">
                                    Datos del solicitante para notificaciones.
                                </p>
                                
                                <div class="form-group">
                                    <label for="edit-request-requester-name">Nombre</label>
                                    <input type="text" id="edit-request-requester-name" placeholder="Juan Pérez" oninput="updateRequesterDot('edit')">
                                </div>

                                <div class="form-group">
                                    <label for="edit-request-requester-email">Email</label>
                                    <input type="email" id="edit-request-requester-email" placeholder="juan@ejemplo.com" oninput="updateRequesterDot('edit')">
                                </div>
                            </div>
                        </div>

                        <?php if (has_role('superadmin')): ?>
                        <div class="modal-side-section modal-danger-zone">
                            <div class="modal-side-title">
                                <i class="iconoir-warning-triangle"></i> Zona peligrosa
                            </div>
                            <button type="button" class="btn btn-danger-outline btn-sm" onclick="deleteRequest()">
                                <i class="iconoir-trash"></i> Eliminar petición
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-footer">
                    <?php if (has_role('superadmin')): ?>
                    <button type="button" class="btn btn-success" onclick="openCompleteAndScheduleModal()" style="margin-right: auto; background: #22c55e; color: white; border: none;">
                        <i class="iconoir-rocket"></i> Completar y Programar
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-ghost" onclick="closeModal('edit-request-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="iconoir-check"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (has_role('superadmin')): ?>
    <!-- Complete and Schedule Modal -->
    <div class="modal" id="complete-schedule-modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="iconoir-rocket" style="color: #22c55e;"></i> Completar y Programar</h3>
                <button class="close-modal" onclick="closeModal('complete-schedule-modal')">
                    <i class="iconoir-xmark"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom: 1rem; font-size: 0.875rem;">
                    Esto marcará la solicitud como <strong>completada</strong> y creará un release programado.
                </p>
                <div class="form-group">
                    <label for="schedule-announce-date">
                        <i class="iconoir-calendar"></i> Fecha de anuncio *
                    </label>
                    <input type="date" id="schedule-announce-date" required>
                </div>
                <div class="form-group">
                    <label for="schedule-description">
                        <i class="iconoir-align-left"></i> Descripción del release (opcional)
                    </label>
                    <textarea id="schedule-description" rows="3" placeholder="Qué hace esta funcionalidad..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('complete-schedule-modal')">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="executeCompleteAndSchedule()" style="background: #22c55e;">
                    <i class="iconoir-check"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

    <!-- Floating Task Button -->
    <div class="floating-task-btn" id="floating-task-btn" onclick="toggleFloatingTaskInput()">
        <i class="iconoir-plus"></i>
    </div>
    <div class="floating-task-input" id="floating-task-input">
        <input type="text" id="floating-task-title" placeholder="Nueva tarea rápida..." onkeydown="handleFloatingTaskKeydown(event)">
        <button class="floating-task-submit" onclick="submitFloatingTask()">
            <i class="iconoir-send"></i>
        </button>
    </div>

    <!-- Toast Notifications -->
    <div id="toast-container"></div>

    <script src="/assets/js/main.js"></script>
    <?php if (has_role('admin')): ?>
        <script src="/assets/js/pending-approvals.js"></script>
    <?php endif; ?>
</body>

</html>
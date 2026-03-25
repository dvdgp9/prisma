<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prisma Dashboard</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" href="/favicon.png?v=2">

    <?php include __DIR__ . '/includes/pwa-head.php'; ?>

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
    data-user-id="<?php echo htmlspecialchars($user['id']); ?>"
    data-company-name="<?php echo htmlspecialchars($company_name); ?>">
    <div class="dashboard-container">
        <?php $current_page = 'index'; include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1 class="page-title" id="page-title">Vista Global</h1>
                <div class="actions">
                    <button class="btn btn-outline" onclick="openExportModal()">
                        <i class="iconoir-download"></i>
                        Exportar
                    </button>
                    <button class="btn btn-primary" onclick="openNewRequestModal()">
                        <i class="iconoir-plus"></i>
                        Nueva Mejora
                    </button>
                </div>
            </div>

            <!-- App Info Section (visible only when viewing specific app) -->
            <div class="app-info-section" id="app-info-section" style="display: none;">
                <!-- Notes Section - Prominent, below app name -->
                <div class="app-notes-section" id="app-notes-section">
                    <div class="app-notes-display" id="app-notes-display">
                        <!-- Notes will be loaded here as cards -->
                    </div>
                    <button class="btn-add-note" onclick="openAddNoteModal()" title="Añadir nota">
                        <i class="iconoir-plus"></i> Añadir nota
                    </button>
                </div>
                
                <!-- Quick Actions Row: Links dropdown + Files upload -->
                <div class="app-quick-actions">
                    <!-- Links Dropdown -->
                    <div class="app-links-dropdown" id="app-links-dropdown">
                        <button class="btn-links-toggle" onclick="toggleLinksDropdown()">
                            <i class="iconoir-link"></i>
                            <span>Enlaces</span>
                            <span class="links-count" id="links-count"></span>
                            <i class="iconoir-nav-arrow-down dropdown-arrow"></i>
                        </button>
                        <div class="links-dropdown-content" id="links-dropdown-content">
                            <div class="links-list" id="app-links-list">
                                <!-- Links will be loaded here -->
                            </div>
                            <button class="btn-add-link" onclick="openAddLinkModal()">
                                <i class="iconoir-plus"></i> Añadir enlace
                            </button>
                        </div>
                    </div>
                    
                    <!-- Files Section - Compact -->
                    <div class="app-files-compact">
                        <div class="app-files-list-compact" id="app-files-list">
                            <!-- Files will be loaded here -->
                        </div>
                        <div class="app-files-upload-compact" id="app-files-upload">
                            <i class="iconoir-cloud-upload"></i>
                            <span>Subir archivo</span>
                            <input type="file" id="app-file-input" style="display: none;" multiple>
                        </div>
                    </div>
                </div>
            </div>

            <div class="requests-toolbar-shell" id="requests-toolbar-shell">
                <div class="sorting-bar" id="sorting-bar">
                    <div class="sorting-controls">
                        <div class="sort-group sort-group-search">
                            <label class="sort-label">Buscar</label>
                            <div class="toolbar-search-input">
                                <i class="iconoir-search"></i>
                                <input type="text" id="request-search" placeholder="Buscar por título, descripción, app o solicitante" oninput="handleSearchInput()">
                            </div>
                        </div>

                        <div class="sort-group sort-group-order">
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
                        
                        <div class="sort-group sort-group-order">
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
                        
                        <div class="sort-group sort-group-order">
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
                </div>

                <div class="quick-views-bar" id="quick-views-bar">
                    <button type="button" class="quick-view-chip active" data-quick-view="all" onclick="setQuickView('all', event)">
                        <i class="iconoir-view-grid"></i>
                        <span>Todas</span>
                    </button>
                    <button type="button" class="quick-view-chip" data-quick-view="mine" onclick="setQuickView('mine', event)">
                        <i class="iconoir-user-badge-check"></i>
                        <span>Mis asignadas</span>
                    </button>
                    <button type="button" class="quick-view-chip" data-quick-view="in_progress" onclick="setQuickView('in_progress', event)">
                        <i class="iconoir-play"></i>
                        <span>En progreso</span>
                    </button>
                    <button type="button" class="quick-view-chip" data-quick-view="pending" onclick="setQuickView('pending', event)">
                        <i class="iconoir-pause"></i>
                        <span>Pendientes</span>
                    </button>
                    <button type="button" class="quick-view-chip" data-quick-view="completed" onclick="setQuickView('completed', event)">
                        <i class="iconoir-check-circle"></i>
                        <span>Completadas</span>
                    </button>
                    <button type="button" class="quick-view-chip" data-quick-view="unassigned" onclick="setQuickView('unassigned', event)">
                        <i class="iconoir-user-xmark"></i>
                        <span>Sin asignar</span>
                    </button>
                    <button type="button" class="quick-view-chip" data-quick-view="commented" onclick="setQuickView('commented', event)">
                        <i class="iconoir-chat-bubble"></i>
                        <span>Con comentarios</span>
                    </button>
                    <div class="view-toggle-group" id="view-toggle-group">
                        <button type="button" class="view-toggle-btn active" data-view-mode="cards" onclick="setRequestsViewMode('cards', event)">
                            <i class="iconoir-view-grid"></i>
                            <span>Tarjetas</span>
                        </button>
                        <button type="button" class="view-toggle-btn" data-view-mode="table" onclick="setRequestsViewMode('table', event)">
                            <i class="iconoir-table-rows"></i>
                            <span>Tabla</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="requests-summary-bar" id="requests-summary-bar">
                <div class="summary-stat-card">
                    <span class="summary-stat-label">Visibles</span>
                    <strong class="summary-stat-value" id="summary-visible-count">0</strong>
                </div>
                <div class="summary-stat-card">
                    <span class="summary-stat-label">En progreso</span>
                    <strong class="summary-stat-value" id="summary-in-progress-count">0</strong>
                </div>
                <div class="summary-stat-card">
                    <span class="summary-stat-label">Pendientes</span>
                    <strong class="summary-stat-value" id="summary-pending-count">0</strong>
                </div>
                <div class="summary-stat-card">
                    <span class="summary-stat-label">Sin asignar</span>
                    <strong class="summary-stat-value" id="summary-unassigned-count">0</strong>
                </div>
                <div class="summary-stat-card">
                    <span class="summary-stat-label">Con comentarios</span>
                    <strong class="summary-stat-value" id="summary-commented-count">0</strong>
                </div>
            </div>

            <!-- Cards Grid -->
            <div class="cards-grid" id="requests-grid">
                <!-- Cards will be loaded dynamically -->
            </div>

            <div class="requests-table-wrapper" id="requests-table-wrapper" style="display: none;">
                <table class="requests-table" id="requests-table">
                    <thead>
                        <tr>
                            <th class="requests-table-sortable" data-sort-field="priority" onclick="setTableSort('priority')">Prioridad</th>
                            <th class="requests-table-sortable" data-sort-field="status" onclick="setTableSort('status')">Estado</th>
                            <th class="requests-table-sortable" data-sort-field="title" onclick="setTableSort('title')">Título</th>
                            <th class="requests-table-sortable" data-sort-field="app_name" onclick="setTableSort('app_name')">App</th>
                            <th class="requests-table-sortable" data-sort-field="owner" onclick="setTableSort('owner')">Responsable</th>
                            <th class="requests-table-sortable" data-sort-field="assignments_count" onclick="setTableSort('assignments_count')">Asignados</th>
                            <th class="requests-table-sortable" data-sort-field="comment_count" onclick="setTableSort('comment_count')">Comentarios</th>
                            <th class="requests-table-sortable" data-sort-field="checklist" onclick="setTableSort('checklist')">Checklist</th>
                            <th class="requests-table-sortable" data-sort-field="created_at" onclick="setTableSort('created_at')">Antigüedad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="requests-table-body">
                        <!-- Rows rendered dynamically -->
                    </tbody>
                </table>
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

                        <!-- Comments section -->
                        <div class="comments-section">
                            <div class="attachments-header" style="margin-bottom: 1rem; margin-top: 0.5rem;">
                                <label>
                                    <i class="iconoir-check-square"></i> Checklist
                                </label>
                                <span id="edit-checklist-count" class="badge-count-inline"></span>
                            </div>

                            <div class="checklist-progress-bar-wrapper">
                                <div class="checklist-progress-bar">
                                    <div class="checklist-progress-fill" id="edit-checklist-progress-fill" style="width: 0%;"></div>
                                </div>
                                <span class="checklist-progress-text" id="edit-checklist-progress-text">0/0</span>
                            </div>

                            <div class="checklist-add-row">
                                <input type="text" id="edit-checklist-input" placeholder="Añadir subtarea o paso de verificación..." onkeydown="handleChecklistKeydown(event)">
                                <button type="button" class="btn btn-primary btn-sm" onclick="addChecklistItem()">
                                    <i class="iconoir-plus"></i>
                                    Añadir
                                </button>
                            </div>

                            <div id="edit-checklist-list" class="checklist-list">
                                <!-- Checklist items loaded dynamically -->
                            </div>

                            <div class="attachments-header" style="margin-bottom: 1rem;">
                                <label>
                                    <i class="iconoir-chat-bubble"></i> Comentarios
                                </label>
                                <span id="edit-comments-count" class="badge-count-inline"></span>
                            </div>

                            <div class="request-activity-overview" id="edit-activity-overview">
                                <div class="request-activity-stat">
                                    <span class="request-activity-stat-label">Último toque</span>
                                    <strong id="edit-last-activity">Sin actividad</strong>
                                </div>
                                <div class="request-activity-stat">
                                    <span class="request-activity-stat-label">Responsable principal</span>
                                    <strong id="edit-primary-owner">Sin asignar</strong>
                                </div>
                            </div>

                            <div id="edit-activity-timeline" class="activity-timeline">
                                <!-- Timeline loaded dynamically -->
                            </div>
                            
                            <div id="edit-comments-list" class="comments-list">
                                <!-- Comments will be loaded here -->
                            </div>
                            
                            <div class="comment-input-wrapper" style="position: relative;">
                                <textarea id="edit-comment-input" class="comment-input" placeholder="Escribe un comentario... Usa @usuario para mencionar" rows="2"></textarea>
                                <div id="edit-mentions-dropdown" class="mentions-dropdown" style="display: none;"></div>
                                <button type="button" class="comment-submit-btn" onclick="submitComment(document.getElementById('edit-request-id').value)">
                                    <i class="iconoir-send"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Metadata -->
                    <div class="modal-column-side">
                        <?php if (has_role('programador')): ?>
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
                                        <option value="pending">Pendiente</option>
                                        <option value="in_progress">En Progreso</option>
                                        <option value="completed">Completado</option>
                                        <option value="discarded">Descartado</option>
                                    </select>
                                </div>

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

                        <?php if (has_role('programador')): ?>
                        <div class="modal-side-section">
                            <div class="modal-side-title">
                                <div class="modal-side-title-content">
                                    <i class="iconoir-user-badge-check"></i> Asignados
                                </div>
                            </div>
                            <div class="modal-side-content">
                                <div id="edit-assigned-tags" class="assigned-tags"></div>
                                <div class="assign-search-wrapper">
                                    <input type="text" id="edit-assign-search" class="assign-search-input" placeholder="Buscar usuario..." autocomplete="off">
                                    <div id="edit-assign-dropdown" class="assign-dropdown" style="display: none;"></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="modal-side-section">
                            <div class="modal-side-title">
                                <div class="modal-side-title-content">
                                    <i class="iconoir-report-columns"></i> Resumen
                                </div>
                            </div>
                            <div class="modal-side-content">
                                <div class="request-insight-list" id="edit-request-insights">
                                    <div class="request-insight-item">
                                        <span class="request-insight-label">Estado</span>
                                        <strong id="edit-summary-status">-</strong>
                                    </div>
                                    <div class="request-insight-item">
                                        <span class="request-insight-label">Prioridad</span>
                                        <strong id="edit-summary-priority">-</strong>
                                    </div>
                                    <div class="request-insight-item">
                                        <span class="request-insight-label">Dificultad</span>
                                        <strong id="edit-summary-difficulty">-</strong>
                                    </div>
                                    <div class="request-insight-item">
                                        <span class="request-insight-label">Creada</span>
                                        <strong id="edit-summary-created">-</strong>
                                    </div>
                                    <div class="request-insight-item">
                                        <span class="request-insight-label">Antigüedad</span>
                                        <strong id="edit-summary-age">-</strong>
                                    </div>
                                    <div class="request-insight-item">
                                        <span class="request-insight-label">Comentarios</span>
                                        <strong id="edit-summary-comments">0</strong>
                                    </div>
                                    <div class="request-insight-item">
                                        <span class="request-insight-label">Adjuntos</span>
                                        <strong id="edit-summary-attachments">0</strong>
                                    </div>
                                    <div class="request-insight-item">
                                        <span class="request-insight-label">Checklist</span>
                                        <strong id="edit-summary-checklist">0/0</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

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

    <!-- Add Link Modal -->
    <div class="modal" id="add-link-modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <div class="modal-header-left">
                    <i class="iconoir-link modal-header-icon"></i>
                    <h3 class="modal-title">Añadir Enlace</h3>
                </div>
                <button class="close-modal" onclick="closeModal('add-link-modal')">
                    <i class="iconoir-xmark"></i>
                </button>
            </div>
            <form id="add-link-form" onsubmit="submitAddLink(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="link-title">
                            <i class="iconoir-text"></i> Título *
                        </label>
                        <input type="text" id="link-title" required placeholder="Ej: Documentación API">
                    </div>
                    <div class="form-group">
                        <label for="link-url">
                            <i class="iconoir-link"></i> URL *
                        </label>
                        <input type="url" id="link-url" required placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('add-link-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="iconoir-plus"></i> Añadir
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div class="modal" id="add-note-modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <div class="modal-header-left">
                    <i class="iconoir-notes modal-header-icon"></i>
                    <h3 class="modal-title">Añadir Nota</h3>
                </div>
                <button class="close-modal" onclick="closeModal('add-note-modal')">
                    <i class="iconoir-xmark"></i>
                </button>
            </div>
            <form id="add-note-form" onsubmit="submitAddNote(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="note-title">
                            <i class="iconoir-text"></i> Título *
                        </label>
                        <input type="text" id="note-title" required placeholder="Ej: Credenciales de acceso">
                    </div>
                    <div class="form-group">
                        <label for="note-content">
                            <i class="iconoir-align-left"></i> Contenido
                        </label>
                        <textarea id="note-content" rows="5" placeholder="Escribe aquí la información..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('add-note-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="iconoir-plus"></i> Añadir
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Note Modal -->
    <div class="modal" id="view-note-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-left">
                    <i class="iconoir-notes modal-header-icon"></i>
                    <h3 class="modal-title" id="view-note-title"></h3>
                </div>
                <button class="modal-close" onclick="closeModal('view-note-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="view-note-content" class="note-content-display"></div>
            </div>
        </div>
    </div>

    <!-- Edit App File Modal -->
    <div class="modal" id="edit-app-file-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-left">
                    <i class="iconoir-edit-pencil modal-header-icon"></i>
                    <h3 class="modal-title">Editar nombre del archivo</h3>
                </div>
                <button class="modal-close" onclick="closeModal('edit-app-file-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form onsubmit="submitEditAppFile(event)">
                    <input type="hidden" id="edit-app-file-id">
                    <div class="form-group">
                        <label for="edit-app-file-name">Nombre del archivo</label>
                        <input type="text" id="edit-app-file-name" required class="form-control">
                    </div>
                    <div class="modal-footer" style="padding: var(--spacing-md) 0 0 0; border: none;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('edit-app-file-modal')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal" id="export-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-left">
                    <i class="iconoir-download modal-header-icon"></i>
                    <h3 class="modal-title">Exportar Mejoras</h3>
                </div>
                <button class="close-modal" onclick="closeModal('export-modal')">
                    <i class="iconoir-xmark"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom: var(--spacing-lg);">
                    Selecciona la empresa de la que quieres exportar las mejoras pendientes.
                </p>
                <div class="form-group">
                    <label for="export-company">
                        <i class="iconoir-building"></i> Empresa *
                    </label>
                    <select id="export-company" required>
                        <option value="">Selecciona una empresa</option>
                        <?php 
                        $user_companies = get_user_companies();
                        foreach ($user_companies as $company): 
                        ?>
                            <option value="<?php echo $company['id']; ?>">
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('export-modal')">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="exportRequests()">
                    <i class="iconoir-download"></i> Exportar CSV
                </button>
            </div>
        </div>
    </div>

    <!-- Assign User Modal -->
    <div class="modal" id="assign-modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <div class="modal-header-left">
                    <i class="iconoir-user-plus modal-header-icon"></i>
                    <h3 class="modal-title">Asignar tarea</h3>
                </div>
                <button class="close-modal" onclick="closeModal('assign-modal')">
                    <i class="iconoir-xmark"></i>
                </button>
            </div>
            <input type="hidden" id="assign-request-id">
            <div class="modal-body">
                <p class="text-small text-muted" style="margin-bottom: 1rem;">Selecciona quién trabajará en esta tarea:</p>
                <div id="assign-user-list" style="max-height: 300px; overflow-y: auto;">
                    <!-- Users will be loaded here -->
                </div>
            </div>
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
    <script src="/assets/js/pwa.js"></script>
</body>

</html>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prisma - Dashboard</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>

<body>
    <?php
    require_once __DIR__ . '/includes/auth.php';
    require_login();

    $user = get_current_user();
    ?>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">Prisma</div>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;">
                            <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
                        <div class="text-small text-muted"><?php echo htmlspecialchars($user['role']); ?></div>
                    </div>
                </div>
            </div>

            <nav>
                <div class="nav-section">
                    <div class="nav-section-title">Vistas Generales</div>
                    <a href="javascript:void(0)" class="nav-item active" onclick="loadView('global')">
                        <span>🌍</span>
                        <span>Vista Global</span>
                    </a>
                </div>

                <div class="nav-section" id="apps-nav">
                    <div class="nav-section-title">Aplicaciones</div>
                    <!-- Apps will be loaded dynamically -->
                </div>

                <?php if (has_role('superadmin')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Administración</div>
                        <a href="/manage-apps.php" class="nav-item">
                            <span>⚙️</span>
                            <span>Gestionar Apps</span>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="nav-section">
                    <a href="/logout.php" class="nav-item" style="color: var(--danger-color);">
                        <span>🚪</span>
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
                        + Nueva Petición
                    </button>
                </div>
            </div>

            <!-- Filters and Sorting -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label>Ordenar por</label>
                    <select id="sort-select" onchange="loadRequests()">
                        <option value="date">Más reciente</option>
                        <option value="date_asc">Más antigua</option>
                        <option value="priority">Prioridad</option>
                        <option value="votes">Más votadas</option>
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
                <h3 class="modal-title">Nueva Petición</h3>
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

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Crear Petición</button>
                    <button type="button" class="btn btn-outline"
                        onclick="closeModal('new-request-modal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
</body>

</html>
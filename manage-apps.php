<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prisma - Gestionar Apps</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Iconoir Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>

<body>
    <?php
    require_once __DIR__ . '/includes/auth.php';
    require_role('admin');

    $user = get_logged_user();
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
                            <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                        </div>
                        <div class="text-small text-muted"><?php echo htmlspecialchars($user['role']); ?></div>
                    </div>
                </div>
            </div>

            <nav>
                <div class="nav-section">
                    <div class="nav-section-title">Vistas Generales</div>
                    <a href="/index.php" class="nav-item">
                        <i class="iconoir-globe"></i>
                        <span>Vista Global</span>
                    </a>
                </div>

                <div class="nav-section" id="apps-nav">
                    <div class="nav-section-title">Aplicaciones</div>
                    <!-- Apps will be loaded dynamically -->
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Administración</div>
                    <a href="/manage-apps.php" class="nav-item active">
                        <i class="iconoir-settings"></i>
                        <span>Gestionar Apps</span>
                    </a>
                </div>

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
                <h1 class="page-title">Gestionar Aplicaciones</h1>
                <div class="actions">
                    <button class="btn btn-primary" onclick="openNewAppModal()">
                        <i class="iconoir-plus"></i>
                        Nueva App
                    </button>
                </div>
            </div>

            <div class="cards-grid" id="apps-grid">
                <!-- Apps will be loaded dynamically -->
            </div>
        </main>
    </div>

    <!-- New App Modal -->
    <div class="modal" id="new-app-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Nueva Aplicación</h3>
                <button class="close-modal" onclick="closeModal('new-app-modal')">×</button>
            </div>

            <form id="new-app-form" onsubmit="submitNewApp(event)">
                <div class="form-group">
                    <label for="app-name">Nombre *</label>
                    <input type="text" id="app-name" required placeholder="Ej: Puri">
                </div>

                <div class="form-group">
                    <label for="app-description">Descripción</label>
                    <textarea id="app-description" rows="4"
                        placeholder="Descripción breve de la aplicación..."></textarea>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Crear App</button>
                    <button type="button" class="btn btn-outline"
                        onclick="closeModal('new-app-modal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit App Modal -->
    <div class="modal" id="edit-app-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Editar Aplicación</h3>
                <button class="close-modal" onclick="closeModal('edit-app-modal')">×</button>
            </div>

            <form id="edit-app-form" onsubmit="submitEditApp(event)">
                <input type="hidden" id="edit-app-id">

                <div class="form-group">
                    <label for="edit-app-name">Nombre *</label>
                    <input type="text" id="edit-app-name" required>
                </div>

                <div class="form-group">
                    <label for="edit-app-description">Descripción</label>
                    <textarea id="edit-app-description" rows="4"></textarea>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Guardar Cambios</button>
                    <button type="button" class="btn btn-secondary" onclick="deleteApp()">Eliminar</button>
                    <button type="button" class="btn btn-outline"
                        onclick="closeModal('edit-app-modal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/assets/js/manage-apps.js"></script>
</body>

</html>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prisma - Gestionar Apps</title>
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

<body>
    <?php
    require_once __DIR__ . '/includes/auth.php';
    require_role('admin');

    $user = get_logged_user();
    ?>

    <div class="dashboard-container" data-company-name="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>">
        <?php $current_page = 'manage-apps'; include __DIR__ . '/includes/sidebar.php'; ?>

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
    <script src="/assets/js/pwa.js"></script>
</body>

</html>
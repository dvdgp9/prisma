<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prisma - Panel Administración</title>
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
    <style>
        .tabs {
            display: flex;
            gap: var(--spacing-sm);
            border-bottom: 2px solid var(--border-light);
            margin-bottom: var(--spacing-xl);
        }

        .tab {
            padding: var(--spacing-md) var(--spacing-lg);
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: var(--font-weight-medium);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all var(--transition-fast);
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .tab:hover {
            color: var(--text-primary);
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        table {
            width: 100%;
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        thead {
            background: var(--bg-secondary);
        }

        th {
            padding: var(--spacing-md) var(--spacing-lg);
            text-align: left;
            font-weight: var(--font-weight-semibold);
            color: var(--text-primary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: var(--spacing-md) var(--spacing-lg);
            border-top: 1px solid var(--border-light);
            color: var(--text-secondary);
        }

        tr:hover {
            background: var(--bg-secondary);
        }

        .badge {
            display: inline-block;
            padding: var(--spacing-xs) var(--spacing-md);
            border-radius: var(--radius-full);
            font-size: 0.8125rem;
            font-weight: var(--font-weight-semibold);
        }

        .badge-superadmin {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .badge-admin {
            background: rgba(77, 171, 247, 0.1);
            color: #4DABF7;
        }

        .badge-user {
            background: var(--bg-tertiary);
            color: var(--text-muted);
        }

        .badge-active {
            background: rgba(81, 207, 102, 0.1);
            color: var(--status-completed);
        }

        .badge-inactive {
            background: var(--bg-tertiary);
            color: var(--status-discarded);
        }

        .actions-cell {
            display: flex;
            gap: var(--spacing-sm);
        }
    </style>
</head>

<?php
require_once __DIR__ . '/includes/auth.php';
require_role('superadmin');

$user = get_logged_user();
?>

<body data-user-role="<?php echo htmlspecialchars($user['role']); ?>">
    <div class="dashboard-container">
        <?php $current_page = 'admin'; include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1 class="page-title">Panel de Administración</h1>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="switchTab('companies')">
                    <i class="iconoir-building"></i>
                    Empresas
                </button>
                <button class="tab" onclick="switchTab('users')">
                    <i class="iconoir-user"></i>
                    Usuarios
                </button>
                <button class="tab" onclick="switchTab('apps')">
                    <i class="iconoir-app-window"></i>
                    Aplicaciones
                </button>
            </div>

            <!-- Companies Tab -->
            <div id="companies-tab" class="tab-content active">
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-lg);">
                    <h2 style="font-size: 1.25rem; font-weight: var(--font-weight-semibold);">Gestión de Empresas</h2>
                    <button class="btn btn-primary" onclick="openNewCompanyModal()">
                        <i class="iconoir-plus"></i>
                        Nueva Empresa
                    </button>
                </div>
                <table id="companies-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Usuarios</th>
                            <th>Admins</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Will be loaded dynamically -->
                    </tbody>
                </table>
            </div>

            <!-- Users Tab -->
            <div id="users-tab" class="tab-content">
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-lg);">
                    <h2 style="font-size: 1.25rem; font-weight: var(--font-weight-semibold);">Gestión de Usuarios</h2>
                    <button class="btn btn-primary" onclick="openNewUserModal()">
                        <i class="iconoir-plus"></i>
                        Nuevo Usuario
                    </button>
                </div>
                <table id="users-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Empresa</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Will be loaded dynamically -->
                    </tbody>
                </table>
            </div>

            <!-- Apps Tab -->
            <div id="apps-tab" class="tab-content">
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-lg);">
                    <h2 style="font-size: 1.25rem; font-weight: var(--font-weight-semibold);">Gestión de Aplicaciones
                    </h2>
                    <button class="btn btn-primary" onclick="openNewAppModal()">
                        <i class="iconoir-plus"></i>
                        Nueva Aplicación
                    </button>
                </div>
                <table id="apps-table">
                    <thead>
                        <tr>
                            <th>Aplicación</th>
                            <th>Empresa</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Will be loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Company Modal -->
    <div class="modal" id="company-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="company-modal-title">Nueva Empresa</h3>
                <button class="close-modal" onclick="closeModal('company-modal')">×</button>
            </div>

            <form id="company-form" onsubmit="submitCompany(event)">
                <input type="hidden" id="company-id">

                <div class="form-group">
                    <label for="company-name">Nombre *</label>
                    <input type="text" id="company-name" required>
                </div>

                <div class="form-group">
                    <label for="company-description">Descripción</label>
                    <textarea id="company-description" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Guardar</button>
                    <button type="button" class="btn btn-outline"
                        onclick="closeModal('company-modal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal" id="user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="user-modal-title">Nuevo Usuario</h3>
                <button class="close-modal" onclick="closeModal('user-modal')">×</button>
            </div>

            <form id="user-form" onsubmit="submitUser(event)">
                <input type="hidden" id="user-id">

                <div class="form-group">
                    <label for="user-username">Usuario *</label>
                    <input type="text" id="user-username" required>
                </div>

                <div class="form-group">
                    <label for="user-fullname">Nombre Completo</label>
                    <input type="text" id="user-fullname">
                </div>

                <div class="form-group">
                    <label for="user-email">Email</label>
                    <input type="email" id="user-email">
                </div>

                <div class="form-group">
                    <label>Empresas asignadas *</label>
                    <div class="permissions-container-premium" style="max-height: 150px;">
                        <div id="user-companies-list" class="permissions-grid-premium">
                            <!-- Companies will be loaded dynamically -->
                        </div>
                    </div>
                    <div class="perms-footer-hint">
                        <i class="iconoir-info-empty"></i>
                        <span>El usuario verá las apps de todas las empresas seleccionadas.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="user-role">Rol *</label>
                    <select id="user-role" required>
                        <option value="user">Usuario</option>
                        <option value="admin">Admin</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="user-password">Contraseña <span id="password-optional"
                            style="color: var(--text-muted);">(dejar vacío para no cambiar)</span></label>
                    <input type="password" id="user-password">
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: var(--spacing-sm);">
                        <input type="checkbox" id="user-active" checked>
                        <span>Usuario activo</span>
                    </label>
                </div>

                <div class="form-group" id="user-app-permissions-group">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--spacing-sm);">
                        <label style="margin-bottom: 0;">Permisos de Aplicaciones</label>
                        <div id="perms-search-container" style="display: none; position: relative; width: 200px;">
                            <i class="iconoir-search" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; color: var(--text-muted);"></i>
                            <input type="text" id="perms-search" placeholder="Buscar app..." 
                                style="padding: 4px 8px 4px 28px; font-size: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); width: 100%;">
                        </div>
                    </div>
                    
                    <div class="permissions-container-premium">
                        <div class="perms-header-actions" id="perms-actions" style="display: none;">
                            <button type="button" class="btn-text-action" onclick="toggleAllPerms(true)">Marcar todas</button>
                            <span style="color: var(--border-light);">|</span>
                            <button type="button" class="btn-text-action" onclick="toggleAllPerms(false)">Desmarcar todas</button>
                        </div>
                        
                        <div id="user-app-permissions-list" class="permissions-grid-premium">
                            <!-- Apps will be loaded dynamically -->
                        </div>
                    </div>
                    <div class="perms-footer-hint">
                        <i class="iconoir-info-empty"></i>
                        <span>Si no marcas ninguna, el usuario tendrá acceso a todas las apps de su empresa.</span>
                    </div>
                </div>

                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Guardar</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('user-modal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- App Modal -->
    <div class="modal" id="app-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="app-modal-title">Nueva Aplicación</h3>
                <button class="close-modal" onclick="closeModal('app-modal')">×</button>
            </div>

            <form id="app-form" onsubmit="submitApp(event)">
                <input type="hidden" id="app-id">

                <div class="form-group">
                    <label for="app-name">Nombre *</label>
                    <input type="text" id="app-name" required placeholder="Ej: Puri">
                </div>

                <div class="form-group">
                    <label for="app-description">Descripción</label>
                    <textarea id="app-description" rows="3" placeholder="Descripción de la aplicación..."></textarea>
                </div>

                <div class="form-group">
                    <label for="app-company">Empresa *</label>
                    <select id="app-company" required>
                        <option value="">Selecciona una empresa</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: var(--spacing-sm);">
                        <input type="checkbox" id="app-active" checked>
                        <span>Aplicación activa</span>
                    </label>
                </div>

                <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Guardar</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('app-modal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/assets/js/admin.js"></script>
    <script src="/assets/js/pwa.js"></script>
</body>

</html>
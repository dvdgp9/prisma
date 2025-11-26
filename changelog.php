<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changelog - Prisma Dashboard</title>
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
$company_name = $user['company_name'] ?? '';
?>

<body data-user-role="<?php echo htmlspecialchars($user['role']); ?>"
    data-company-name="<?php echo htmlspecialchars($company_name); ?>">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <img src="/assets/images/logo.png" alt="Prisma" style="height: 32px; width: auto;">
                    <div class="logo">Prisma</div>
                </div>
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
                    <a href="/index.php" class="nav-item">
                        <i class="iconoir-globe"></i>
                        <span>Vista Global</span>
                    </a>
                    <?php if (has_role('admin')): ?>
                        <a href="#" onclick="window.location.href='/index.php#pending'; return false;" class="nav-item">
                            <i class="iconoir-clock"></i>
                            <span>Pendientes Aprobar</span>
                        </a>
                    <?php endif; ?>
                    <a href="/changelog.php" class="nav-item active">
                        <i class="iconoir-list"></i>
                        <span>Changelog</span>
                    </a>
                </div>

                <div class="nav-section" id="apps-nav">
                    <div class="nav-section-title">Aplicaciones</div>
                    <!-- Apps will be loaded dynamically -->
                </div>

                <?php if (has_role('admin')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Administración</div>
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
                <h1 class="page-title">
                    <i class="iconoir-list" style="font-size: 1.5rem;"></i>
                    Changelog
                </h1>
                <div class="actions">
                    <button class="btn btn-outline" onclick="exportToMarkdown()">
                        <i class="iconoir-download"></i>
                        Exportar Markdown
                    </button>
                    <button class="btn btn-primary" onclick="copyToClipboard()">
                        <i class="iconoir-copy"></i>
                        Copiar al Portapapeles
                    </button>
                </div>
            </div>

            <!-- Filters and Controls -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label>Aplicación</label>
                    <select id="app-filter" onchange="loadChangelog()">
                        <option value="">Todas las aplicaciones</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Período</label>
                    <select id="period-filter" onchange="handlePeriodChange()">
                        <option value="7">Última semana</option>
                        <option value="30" selected>Último mes</option>
                        <option value="90">Último trimestre</option>
                        <option value="365">Último año</option>
                        <option value="custom">Personalizado...</option>
                    </select>
                </div>

                <div class="filter-group" id="custom-dates-group" style="display: none;">
                    <label>Desde</label>
                    <input type="date" id="date-from" onchange="loadChangelog()">
                </div>

                <div class="filter-group" id="custom-dates-group-to" style="display: none;">
                    <label>Hasta</label>
                    <input type="date" id="date-to" onchange="loadChangelog()">
                </div>

                <div class="filter-group">
                    <label>Agrupar por</label>
                    <select id="group-by" onchange="renderChangelog()">
                        <option value="day">Día</option>
                        <option value="week" selected>Semana</option>
                        <option value="month">Mes</option>
                    </select>
                </div>

                <div class="filter-group" style="flex: 1; min-width: 200px;">
                    <label>Buscar</label>
                    <input type="text" id="search-input" placeholder="Buscar en changelog..."
                        oninput="renderChangelog()">
                </div>
            </div>

            <!-- Changelog Content -->
            <div id="changelog-container" style="margin-top: 2rem;">
                <!-- Changelog will be loaded dynamically -->
            </div>
        </main>
    </div>

    <!-- Profile Modal (reusing from index) -->
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

    <script src="/assets/js/changelog.js"></script>
</body>

</html>
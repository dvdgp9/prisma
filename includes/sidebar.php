<?php
/**
 * Sidebar Component - Unified sidebar for all pages
 * 
 * Usage: 
 *   $current_page = 'index'; // or 'changelog', 'tasks', 'admin', 'manage-apps'
 *   include __DIR__ . '/includes/sidebar.php';
 * 
 * Requires: $user variable from auth.php
 */

if (!isset($user)) {
    $user = get_logged_user();
}

$company_name = $user['company_name'] ?? '';

// En index.php: qué entrada principal va activa (las apps/empresas las marca main.js).
$index_view = '';
if ($current_page === 'index' && !isset($_GET['app']) && !isset($_GET['app_id']) && !isset($_GET['company'])) {
    $index_view = (($_GET['view'] ?? '') === 'mejoras' || isset($_GET['open_request'])) ? 'mejoras' : 'global';
}
?>

<?php
    $user_initials = strtoupper(mb_substr(trim($user['full_name'] ?? $user['username'] ?? '?'), 0, 1));
    $user_display = $user['full_name'] ?? $user['username'] ?? 'Usuario';
    $role_label_map = ['superadmin' => 'Superadmin', 'admin' => 'Admin', 'programador' => 'Programador', 'user' => 'Usuario'];
    $role_label = $role_label_map[$user['role'] ?? 'user'] ?? 'Usuario';
?>
<link rel="stylesheet" href="/assets/css/sidebar.css?v=1">
<div class="sidebar-mobile-bar">
    <a href="/index.php" class="sidebar-brand" aria-label="Prisma · Inicio">
        <img src="/assets/images/logo.png" alt="" aria-hidden="true"><span class="sidebar-brand-text">Prisma</span>
    </a>
    <button type="button" class="sidebar-mobile-toggle" onclick="toggleMobileSidebar()" aria-label="Abrir menú" aria-controls="prisma-sidebar" aria-expanded="false">
        <i class="iconoir-menu" aria-hidden="true"></i>
    </button>
</div>
<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleMobileSidebar(false)" aria-hidden="true" hidden></div>
<aside class="sidebar" id="prisma-sidebar" aria-label="Navegación de Prisma" data-user-id="<?php echo (int) $user['id']; ?>" data-company-id="<?php echo (int) ($user['company_id'] ?? 0); ?>">
    <div class="sidebar-header">
        <a href="/index.php" class="sidebar-brand" aria-label="Prisma · Inicio">
            <img src="/assets/images/logo.png" alt="" aria-hidden="true">
            <span class="sidebar-brand-text">Prisma</span>
        </a>
        <button type="button" class="sidebar-close" onclick="toggleMobileSidebar(false)" aria-label="Cerrar menú">
            <i class="iconoir-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="sidebar-nav" aria-label="Principal">
        <!-- Primary navigation -->
        <div class="nav-section nav-section--workspace" role="group" aria-labelledby="sidebar-workspace-title">
            <h2 class="nav-section-title" id="sidebar-workspace-title">Espacio de trabajo</h2>
            <?php if ($current_page === 'index'): ?>
                <a href="/index.php" class="nav-item <?php echo $index_view === 'global' ? 'active' : ''; ?>" onclick="if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return true; event.preventDefault(); loadView('home', null, event); return false;" data-nav="global">
                    <i class="iconoir-view-grid"></i>
                    <span>Vista global</span>
                </a>
            <?php else: ?>
                <a href="/index.php" class="nav-item" data-nav="global">
                    <i class="iconoir-view-grid"></i>
                    <span>Vista global</span>
                </a>
            <?php endif; ?>

            <a href="/tasks.php" class="nav-item <?php echo $current_page === 'tasks' ? 'active' : ''; ?>" data-nav="tasks">
                <i class="iconoir-task-list"></i>
                <span>Tareas</span>
                <span class="nav-count" id="tasks-count" hidden></span>
            </a>

            <?php if ($current_page === 'index'): ?>
                <a href="/index.php?view=mejoras" class="nav-item <?php echo $index_view === 'mejoras' ? 'active' : ''; ?>" onclick="if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return true; event.preventDefault(); loadView('mejoras', null, event); return false;" data-nav="mejoras">
                    <i class="iconoir-light-bulb"></i>
                    <span>Mejoras</span>
                </a>
            <?php else: ?>
                <a href="/index.php?view=mejoras" class="nav-item" data-nav="mejoras">
                    <i class="iconoir-light-bulb"></i>
                    <span>Mejoras</span>
                </a>
            <?php endif; ?>

            <a href="/notes.php" class="nav-item <?php echo $current_page === 'notes' ? 'active' : ''; ?>" data-nav="notes">
                <i class="iconoir-page"></i>
                <span>Notas</span>
            </a>

        </div>

        <!-- Apps section -->
        <div class="nav-section nav-section--apps" role="group" aria-labelledby="sidebar-apps-title">
            <div class="nav-section-header">
                <h2 class="nav-section-title" id="sidebar-apps-title">Aplicaciones</h2>
                <div class="sidebar-search">
                    <i class="iconoir-search" aria-hidden="true"></i>
                    <input type="search" id="sidebar-search-input" placeholder="Buscar aplicación…" oninput="filterSidebarApps(this.value)" aria-label="Buscar aplicación o empresa" aria-controls="apps-nav" autocomplete="off">
                    <button type="button" class="sidebar-search-clear" onclick="clearSidebarSearch()" aria-label="Limpiar búsqueda de aplicaciones" hidden><i class="iconoir-xmark" aria-hidden="true"></i></button>
                </div>
            </div>
            <div id="apps-nav" aria-busy="true">
                <div class="sidebar-apps-loading" aria-hidden="true"><span></span><span></span><span></span></div>
            </div>
            <p class="sidebar-search-status" id="sidebar-search-status" role="status" aria-live="polite"></p>
        </div>

        <!-- Tools / Admin section -->
        <div class="nav-section nav-section--secondary" role="group" aria-labelledby="sidebar-secondary-title">
            <h2 class="nav-section-title" id="sidebar-secondary-title"><?php echo has_role('admin') ? 'Gestión' : 'Más en Prisma'; ?></h2>
            <div class="sidebar-secondary-links">
                <?php if (has_role('admin')): ?>
                    <a href="/index.php#pending"
                       onclick="<?php echo $current_page === 'index' ? 'if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return true; openPendingApprovalsView(event); return false;' : 'return true;'; ?>"
                       class="nav-item" id="pending-approvals-nav" data-nav="pending" aria-label="Por aprobar">
                        <i class="iconoir-hourglass"></i>
                        <span>Por aprobar</span>
                        <span class="nav-count nav-count--accent" id="pending-count" hidden></span>
                    </a>
                <?php endif; ?>
                <?php if (has_role('superadmin')): ?>
                    <a href="/releases.php" class="nav-item <?php echo $current_page === 'releases' ? 'active' : ''; ?>" aria-label="Release Planner">
                        <i class="iconoir-rocket"></i>
                        <span>Release Planner</span>
                    </a>
                <?php endif; ?>
                <a href="/changelog.php" class="nav-item <?php echo $current_page === 'changelog' ? 'active' : ''; ?>" aria-label="Changelog">
                    <i class="iconoir-journal"></i>
                    <span>Changelog</span>
                </a>
                <?php if (has_role('superadmin')): ?>
                    <a href="/admin.php" class="nav-item <?php echo $current_page === 'admin' ? 'active' : ''; ?>" aria-label="Panel Admin">
                        <i class="iconoir-shield-check"></i>
                        <span>Panel Admin</span>
                    </a>
                <?php elseif (has_role('admin')): ?>
                    <!-- Admins no superadmin gestionan apps aquí (no tienen acceso al Panel Admin) -->
                    <a href="/manage-apps.php" class="nav-item <?php echo $current_page === 'manage-apps' ? 'active' : ''; ?>" aria-label="Gestionar apps">
                        <i class="iconoir-settings"></i>
                        <span>Gestionar apps</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Account stays available while the navigation scrolls. -->
    <div class="sidebar-user" id="sidebar-user">
        <button type="button" class="sidebar-user-trigger" onclick="toggleSidebarUserMenu(event)" aria-expanded="false" aria-controls="sidebar-user-menu">
            <span class="sidebar-user-avatar"><?php echo htmlspecialchars($user_initials, ENT_QUOTES); ?></span>
            <span class="sidebar-user-meta">
                <span class="sidebar-user-name"><?php echo htmlspecialchars($user_display, ENT_QUOTES); ?></span>
                <span class="sidebar-user-role"><?php echo htmlspecialchars($role_label, ENT_QUOTES); ?></span>
            </span>
            <i class="iconoir-nav-arrow-up sidebar-user-caret"></i>
        </button>
        <div class="sidebar-user-menu" id="sidebar-user-menu" hidden>
            <a href="/index.php?profile=1" class="sidebar-user-menu-item" onclick="return openSidebarProfile(event)">
                <i class="iconoir-user" aria-hidden="true"></i><span>Mi perfil</span>
            </a>
            <?php if ($user['role'] === 'user'): ?>
            <button type="button" class="sidebar-user-menu-item" id="pwa-install-menu-item" onclick="promptInstall(); toggleSidebarUserMenu()" hidden>
                <i class="iconoir-download"></i><span>Instalar aplicación</span>
            </button>
            <?php endif; ?>
            <a href="/logout.php" class="sidebar-user-menu-item sidebar-user-menu-item--danger">
                <i class="iconoir-log-out"></i><span>Cerrar sesión</span>
            </a>
        </div>
    </div>
</aside>

<!-- Inbox Panel -->
<div class="inbox-overlay" id="inbox-overlay" onclick="toggleInbox()"></div>
<div class="inbox-panel" id="inbox-panel" role="dialog" aria-modal="true" aria-label="Notificaciones" tabindex="-1">
    <div class="inbox-header">
        <h3>Notificaciones</h3>
        <div class="inbox-header-actions">
            <button type="button" class="inbox-mark-all-btn" id="inbox-mark-all-btn" onclick="markAllNotificationsRead()" hidden>
                Marcar todo
            </button>
            <button type="button" class="inbox-close-btn" onclick="toggleInbox()" aria-label="Cerrar notificaciones">
                <i class="iconoir-xmark"></i>
            </button>
        </div>
    </div>
    <div class="inbox-toolbar" id="inbox-toolbar">
        <div class="inbox-segmented" role="group" aria-label="Filtrar por estado de lectura">
            <button type="button" class="inbox-segment active" data-read-filter="all" onclick="setInboxReadFilter('all')">Todas</button>
            <button type="button" class="inbox-segment" data-read-filter="unread" onclick="setInboxReadFilter('unread')">
                No leídas<span class="inbox-segment-count" id="inbox-segment-count" hidden></span>
            </button>
        </div>
        <div class="inbox-type-wrap">
            <select class="inbox-type-select" id="inbox-type-select" aria-label="Filtrar por tipo" onchange="setInboxTypeFilter(this.value)">
                <option value="all">Todo tipo</option>
                <option value="mention">Menciones</option>
                <option value="assignment">Asignaciones</option>
                <option value="comment">Comentarios</option>
                <option value="status">Estado</option>
                <option value="note_share">Notas</option>
            </select>
            <i class="iconoir-nav-arrow-down" aria-hidden="true"></i>
        </div>
    </div>
    <div class="inbox-body" id="inbox-body">
        <!-- Notifications loaded dynamically -->
    </div>
</div>

<script src="/assets/js/sidebar.js?v=3"></script>

<?php include __DIR__ . '/assistant-panel.php'; ?>

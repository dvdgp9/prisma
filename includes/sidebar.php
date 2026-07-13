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
?>

<?php
    $user_initials = strtoupper(mb_substr(trim($user['full_name'] ?? $user['username'] ?? '?'), 0, 1));
    $user_display = $user['full_name'] ?? $user['username'] ?? 'Usuario';
    $role_label_map = ['superadmin' => 'Superadmin', 'admin' => 'Admin', 'programador' => 'Programador', 'user' => 'Usuario'];
    $role_label = $role_label_map[$user['role'] ?? 'user'] ?? 'Usuario';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="/index.php" class="sidebar-brand" aria-label="Prisma · Inicio">
            <img src="/assets/images/logo.png" alt="" aria-hidden="true">
            <span class="sidebar-brand-text">Prisma</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <!-- Primary navigation -->
        <div class="nav-section">
            <?php if ($current_page === 'index'): ?>
                <a href="javascript:void(0)" class="nav-item <?php echo ($current_page === 'index' && !isset($_GET['app_id'])) ? 'active' : ''; ?>" onclick="event.preventDefault(); loadView('global', null, event); return false;" data-nav="global">
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
                <span>Mis tareas</span>
                <span class="nav-count" id="tasks-count" hidden></span>
            </a>

            <a href="/ai-inbox.php" class="nav-item <?php echo $current_page === 'ai-inbox' ? 'active' : ''; ?>" data-nav="ai-inbox">
                <i class="iconoir-sparks"></i>
                <span>Nota rápida</span>
            </a>

            <a href="javascript:void(0)" class="nav-item" onclick="toggleInbox()" id="inbox-nav-btn" data-nav="inbox">
                <i class="iconoir-bell"></i>
                <span>Notificaciones</span>
                <span class="nav-count nav-count--accent" id="inbox-count" hidden></span>
            </a>
        </div>

        <!-- Apps section -->
        <div class="nav-section nav-section--apps">
            <div class="nav-section-header">
                <span class="nav-section-title">Aplicaciones</span>
                <div class="sidebar-search">
                    <i class="iconoir-search"></i>
                    <input type="text" id="sidebar-search-input" placeholder="Buscar app" onkeyup="filterSidebarApps(this.value)" aria-label="Buscar aplicación">
                </div>
            </div>
            <div id="apps-nav">
                <!-- Apps loaded dynamically -->
            </div>
        </div>

        <!-- Tools / Admin section -->
        <div class="nav-section nav-section--tools">
            <div class="nav-tools-row">
                <?php if (has_role('admin')): ?>
                    <a href="<?php echo $current_page === 'index' ? '#' : '/index.php#pending'; ?>"
                       onclick="<?php echo $current_page === 'index' ? 'openPendingApprovalsView(event); return false;' : 'return true;'; ?>"
                       class="nav-item nav-tool-icon" id="pending-approvals-nav" data-nav="pending" aria-label="Por aprobar" data-tooltip="Por aprobar">
                        <i class="iconoir-hourglass"></i>
                        <span>Por aprobar</span>
                        <span class="nav-count nav-count--accent" id="pending-count" hidden></span>
                    </a>
                <?php endif; ?>
                <?php if (has_role('superadmin')): ?>
                    <a href="/releases.php" class="nav-item nav-tool-icon <?php echo $current_page === 'releases' ? 'active' : ''; ?>" aria-label="Release Planner" data-tooltip="Release Planner">
                        <i class="iconoir-rocket"></i>
                        <span>Release Planner</span>
                    </a>
                <?php endif; ?>
                <a href="/changelog.php" class="nav-item nav-tool-icon <?php echo $current_page === 'changelog' ? 'active' : ''; ?>" aria-label="Changelog" data-tooltip="Changelog">
                    <i class="iconoir-journal"></i>
                    <span>Changelog</span>
                </a>
                <?php if (has_role('superadmin')): ?>
                    <a href="/admin.php" class="nav-item nav-tool-icon <?php echo $current_page === 'admin' ? 'active' : ''; ?>" aria-label="Panel Admin" data-tooltip="Panel Admin">
                        <i class="iconoir-shield-check"></i>
                        <span>Panel Admin</span>
                    </a>
                <?php elseif (has_role('admin')): ?>
                    <!-- Admins no superadmin gestionan apps aquí (no tienen acceso al Panel Admin) -->
                    <a href="/manage-apps.php" class="nav-item nav-tool-icon <?php echo $current_page === 'manage-apps' ? 'active' : ''; ?>" aria-label="Gestionar apps" data-tooltip="Gestionar apps">
                        <i class="iconoir-settings"></i>
                        <span>Gestionar apps</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- User pill at footer -->
    <div class="sidebar-user" id="sidebar-user">
        <button type="button" class="sidebar-user-trigger" onclick="toggleSidebarUserMenu(event)" aria-haspopup="true" aria-expanded="false">
            <span class="sidebar-user-avatar"><?php echo htmlspecialchars($user_initials, ENT_QUOTES); ?></span>
            <span class="sidebar-user-meta">
                <span class="sidebar-user-name"><?php echo htmlspecialchars($user_display, ENT_QUOTES); ?></span>
                <span class="sidebar-user-role"><?php echo htmlspecialchars($role_label, ENT_QUOTES); ?></span>
            </span>
            <i class="iconoir-nav-arrow-up sidebar-user-caret"></i>
        </button>
        <div class="sidebar-user-menu" id="sidebar-user-menu" hidden>
            <button type="button" class="sidebar-user-menu-item" onclick="openProfileModal(); toggleSidebarUserMenu()">
                <i class="iconoir-user"></i><span>Mi perfil</span>
            </button>
            <a href="/logout.php" class="sidebar-user-menu-item sidebar-user-menu-item--danger">
                <i class="iconoir-log-out"></i><span>Cerrar sesión</span>
            </a>
        </div>
    </div>
</aside>

<!-- Inbox Panel -->
<div class="inbox-overlay" id="inbox-overlay" onclick="toggleInbox()"></div>
<div class="inbox-panel" id="inbox-panel">
    <div class="inbox-header">
        <h3><i class="iconoir-bell"></i> Notificaciones</h3>
        <div style="display: flex; gap: var(--spacing-sm);">
            <button class="comment-action-btn" onclick="markAllNotificationsRead()" title="Marcar todo como leído">
                <i class="iconoir-check-circle"></i>
            </button>
            <button class="comment-action-btn" onclick="toggleInbox()" title="Cerrar">
                <i class="iconoir-xmark"></i>
            </button>
        </div>
    </div>
    <div class="inbox-filters" id="inbox-filters">
        <button type="button" class="inbox-filter-chip active" data-filter="all" onclick="setInboxFilter('all', event)">Todas</button>
        <button type="button" class="inbox-filter-chip" data-filter="unread" onclick="setInboxFilter('unread', event)">No leídas</button>
        <button type="button" class="inbox-filter-chip" data-filter="mention" onclick="setInboxFilter('mention', event)">Menciones</button>
        <button type="button" class="inbox-filter-chip" data-filter="assignment" onclick="setInboxFilter('assignment', event)">Asignaciones</button>
        <button type="button" class="inbox-filter-chip" data-filter="comment" onclick="setInboxFilter('comment', event)">Comentarios</button>
        <button type="button" class="inbox-filter-chip" data-filter="completion" onclick="setInboxFilter('completion', event)">Completadas</button>
    </div>
    <div class="inbox-body" id="inbox-body">
        <!-- Notifications loaded dynamically -->
    </div>
</div>

<script src="/assets/js/sidebar.js?v=1.1"></script>

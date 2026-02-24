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

<aside class="sidebar">
    <div class="sidebar-header">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <img src="/assets/images/logo.png" alt="Prisma" style="height: 32px; width: auto;">
            <div class="logo">Prisma</div>
        </div>
    </div>

    <nav>
        <!-- Search Box -->
        <div class="nav-section">
            <div class="sidebar-search">
                <i class="iconoir-search"></i>
                <input type="text" id="sidebar-search-input" placeholder="Buscar apps..." onkeyup="filterSidebarApps(this.value)">
            </div>
        </div>

        <!-- Quick Actions Icons -->
        <div class="nav-section">
            <div class="quick-actions-row">
                <?php if ($current_page === 'index'): ?>
                    <a href="javascript:void(0)" class="quick-action-btn <?php echo ($current_page === 'index' && !isset($_GET['app_id'])) ? 'active' : ''; ?>" onclick="event.preventDefault(); loadView('global', null, event); return false;" title="Vista Global">
                        <i class="iconoir-globe"></i>
                    </a>
                <?php else: ?>
                    <a href="/index.php" class="quick-action-btn" title="Vista Global">
                        <i class="iconoir-globe"></i>
                    </a>
                <?php endif; ?>
                
                <?php if (has_role('admin')): 
                    // Get pending count
                    $db = getDB();
                    $where_pending = ["r.is_public_request = 1", "r.status = 'pending_approval'"];
                    $params_pending = [];
                    
                    if ($user['role'] !== 'superadmin') {
                        $user_comps = get_user_companies();
                        $comp_ids = array_column($user_comps, 'id');
                        if (!empty($comp_ids)) {
                            $placeholders = implode(',', array_fill(0, count($comp_ids), '?'));
                            $where_pending[] = "a.company_id IN ($placeholders)";
                            $params_pending = $comp_ids;
                        } else {
                            $where_pending[] = "a.company_id = ?";
                            $params_pending[] = $user['company_id'] ?? 0;
                        }
                    }
                    
                    $stmt_pending = $db->prepare("
                        SELECT COUNT(*) 
                        FROM requests r
                        INNER JOIN apps a ON r.app_id = a.id
                        WHERE " . implode(' AND ', $where_pending)
                    );
                    $stmt_pending->execute($params_pending);
                    $pending_count = $stmt_pending->fetchColumn();
                ?>
                    <a href="<?php echo $current_page === 'index' ? '#' : '/index.php#pending'; ?>" 
                       onclick="<?php echo $current_page === 'index' ? 'loadPendingApprovals(); return false;' : 'return true;'; ?>" 
                       class="quick-action-btn" 
                       id="pending-approvals-nav" 
                       title="Pendientes Aprobar">
                        <i class="iconoir-clock"></i>
                        <span class="badge-count" id="pending-count" <?php echo $pending_count > 0 ? '' : 'style="display: none;"'; ?>><?php echo $pending_count; ?></span>
                    </a>
                <?php endif; ?>
                
                <a href="/changelog.php" class="quick-action-btn <?php echo $current_page === 'changelog' ? 'active' : ''; ?>" title="Changelog">
                    <i class="iconoir-list"></i>
                </a>
            </div>
            
            <a href="/tasks.php" class="nav-item <?php echo $current_page === 'tasks' ? 'active' : ''; ?>">
                <i class="iconoir-check-circle"></i>
                <span>Mis Tareas</span>
                <span class="badge-count" id="tasks-count" style="display: none;"></span>
            </a>
            <?php if (has_role('superadmin')): ?>
            <a href="/releases.php" class="nav-item <?php echo $current_page === 'releases' ? 'active' : ''; ?>">
                <i class="iconoir-calendar"></i>
                <span>Release Planner</span>
            </a>
            <?php endif; ?>
        </div>

        <!-- Apps Section - Will be loaded dynamically and grouped by company -->
        <div class="nav-section" id="apps-nav">
            <div class="nav-section-title">Aplicaciones</div>
            <!-- Apps will be loaded dynamically via JS -->
        </div>

        <!-- Admin Section -->
        <?php if (has_role('admin')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Administración</div>
                <?php if (has_role('superadmin')): ?>
                    <a href="/admin.php" class="nav-item <?php echo $current_page === 'admin' ? 'active' : ''; ?>">
                        <i class="iconoir-shield-check"></i>
                        <span>Panel Admin</span>
                    </a>
                <?php endif; ?>
                <a href="/manage-apps.php" class="nav-item <?php echo $current_page === 'manage-apps' ? 'active' : ''; ?>">
                    <i class="iconoir-settings"></i>
                    <span>Gestionar Apps</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- Profile and Logout -->
        <div class="nav-section">
            <a href="javascript:void(0)" class="nav-item" onclick="openProfileModal()">
                <i class="iconoir-user"></i>
                <span>Mi Perfil</span>
            </a>
            <a href="/logout.php" class="nav-item" style="color: var(--secondary);">
                <i class="iconoir-log-out"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </nav>
</aside>

<script src="/assets/js/sidebar.js"></script>

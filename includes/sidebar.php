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
        <!-- Search Box -->
        <div class="nav-section">
            <div class="sidebar-search">
                <i class="iconoir-search"></i>
                <input type="text" id="sidebar-search-input" placeholder="Buscar apps..." onkeyup="filterSidebarApps(this.value)">
            </div>
        </div>

        <!-- General Views -->
        <div class="nav-section">
            <div class="nav-section-title">Vistas Generales</div>
            <?php if ($current_page === 'index'): ?>
                <a href="javascript:void(0)" class="nav-item <?php echo ($current_page === 'index' && !isset($_GET['app_id'])) ? 'active' : ''; ?>" onclick="loadView('global')">
                    <i class="iconoir-globe"></i>
                    <span>Vista Global</span>
                </a>
            <?php else: ?>
                <a href="/index.php" class="nav-item">
                    <i class="iconoir-globe"></i>
                    <span>Vista Global</span>
                </a>
            <?php endif; ?>
            
            <?php if (has_role('admin')): ?>
                <?php if ($current_page === 'index'): ?>
                    <a href="#" onclick="loadPendingApprovals(); return false;" class="nav-item" id="pending-approvals-nav">
                        <i class="iconoir-clock"></i>
                        <span>Pendientes Aprobar</span>
                        <span class="badge-count" id="pending-count" style="display: none;"></span>
                    </a>
                <?php else: ?>
                    <a href="/index.php#pending" class="nav-item">
                        <i class="iconoir-clock"></i>
                        <span>Pendientes Aprobar</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            
            <a href="/changelog.php" class="nav-item <?php echo $current_page === 'changelog' ? 'active' : ''; ?>">
                <i class="iconoir-list"></i>
                <span>Changelog</span>
            </a>
            <a href="/tasks.php" class="nav-item <?php echo $current_page === 'tasks' ? 'active' : ''; ?>">
                <i class="iconoir-check-circle"></i>
                <span>Mis Tareas</span>
                <span class="badge-count" id="tasks-count" style="display: none;"></span>
            </a>
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

        <!-- Logout -->
        <div class="nav-section">
            <a href="/logout.php" class="nav-item" style="color: var(--secondary);">
                <i class="iconoir-log-out"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </nav>
</aside>

<script>
// Sidebar search filter
function filterSidebarApps(query) {
    const apps = document.querySelectorAll('#apps-nav .nav-item');
    const companyGroups = document.querySelectorAll('#apps-nav .company-group');
    const q = query.toLowerCase().trim();
    
    if (companyGroups.length > 0) {
        // Multi-company mode: filter within groups
        companyGroups.forEach(group => {
            const items = group.querySelectorAll('.nav-item');
            let visibleCount = 0;
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (q === '' || text.includes(q)) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            // Hide entire group if no matches
            group.style.display = visibleCount > 0 ? '' : 'none';
        });
    } else {
        // Single company mode
        apps.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = (q === '' || text.includes(q)) ? '' : 'none';
        });
    }
}
</script>

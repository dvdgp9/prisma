<?php
/**
 * Sidebar Navigation Component
 * Include this file in all pages for consistent navigation
 */

require_once __DIR__ . '/auth.php';
$user = get_logged_user();
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

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
            <a href="/index.php" class="nav-item <?php echo $current_page === 'index' ? 'active' : ''; ?>">
                <i class="iconoir-globe"></i>
                <span>Vista Global</span>
            </a>
        </div>

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

        <div class="nav-section" id="apps-nav">
            <div class="nav-section-title">Aplicaciones</div>
            <!-- Apps will be loaded here -->
        </div>

        <div class="nav-section">
            <a href="/logout.php" class="nav-item" style="color: var(--secondary);">
                <i class="iconoir-log-out"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </nav>
</aside>

<script>
    // Load apps in sidebar for all pages
    (async function loadSidebarApps() {
        try {
            const response = await fetch('/api/apps.php');
            const data = await response.json();

            if (data.success && data.data.length > 0) {
                const appsNav = document.getElementById('apps-nav');
                const navItems = data.data.map(app => `
                <a href="/index.php?app=${app.id}" class="nav-item">
                    <i class="iconoir-app-window"></i>
                    <span>${escapeHtmlSidebar(app.name)}</span>
                </a>
            `).join('');

                appsNav.innerHTML = `
                <div class="nav-section-title">Aplicaciones</div>
                ${navItems}
            `;
            }
        } catch (error) {
            console.error('Error loading sidebar apps:', error);
        }

        function escapeHtmlSidebar(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    })();
</script>
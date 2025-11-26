<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changelog - Prisma</title>
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
    <style>
        .changelog-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .timeline-item {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .timeline-date {
            flex-shrink: 0;
            width: 120px;
            text-align: right;
            padding-top: 0.25rem;
        }

        .timeline-date-day {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
        }

        .timeline-date-month {
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .timeline-content {
            flex: 1;
            position: relative;
            padding-left: 2rem;
        }

        .timeline-content::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid var(--bg-primary);
            box-shadow: 0 0 0 2px var(--primary-color);
        }

        .timeline-content::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 1.5rem;
            bottom: -2rem;
            width: 2px;
            background: var(--border-color);
        }

        .timeline-item:last-child .timeline-content::after {
            display: none;
        }

        .changelog-entry {
            background: var(--bg-secondary);
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 0.75rem;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .changelog-entry:hover {
            border-color: var(--primary-color);
            transform: translateX(4px);
        }

        .changelog-entry-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .changelog-entry-title {
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
            margin: 0;
            font-size: 0.9375rem;
        }

        .changelog-entry-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .changelog-entry-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin: 0;
            line-height: 1.5;
        }

        .export-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filters-section {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .group-header {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
    </style>
</head>

<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = get_logged_user();
?>

<body data-user-role="<?php echo htmlspecialchars($user['role']); ?>">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <img src="/assets/images/logo.png" alt="Prisma" style="height: 32px; width: auto;">
                    <div class="logo">Prisma</div>
                </div>
                <div class="user-info" onclick="window.location.href='/index.php'" style="cursor: pointer;" title="Ir al dashboard">
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
                    <div class="nav-section-title">Vistas</div>
                    <a href="/index.php" class="nav-item">
                        <i class="iconoir-globe"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="/changelog.php" class="nav-item active">
                        <i class="iconoir-journal-page"></i>
                        <span>Changelog</span>
                    </a>
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
                <div>
                    <h1 class="page-title">Changelog</h1>
                    <p style="color: var(--text-secondary); margin: 0.5rem 0 0 0;">Historial de mejoras completadas</p>
                </div>
                <div class="actions">
                    <button class="btn btn-primary export-btn" onclick="exportToMarkdown()">
                        <i class="iconoir-download"></i>
                        Exportar Markdown
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <div class="filters-grid">
                    <div class="form-group" style="margin: 0;">
                        <label for="app-filter">Aplicación</label>
                        <select id="app-filter" onchange="loadChangelog()">
                            <option value="">Todas las apps</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="period-filter">Período</label>
                        <select id="period-filter" onchange="loadChangelog()">
                            <option value="7">Última semana</option>
                            <option value="30" selected>Último mes</option>
                            <option value="90">Últimos 3 meses</option>
                            <option value="180">Últimos 6 meses</option>
                            <option value="365">Último año</option>
                            <option value="all">Todo el tiempo</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="search-filter">Buscar</label>
                        <input type="text" id="search-filter" placeholder="Buscar en changelog..." 
                               oninput="filterChangelog()">
                    </div>
                </div>
            </div>

            <!-- Changelog Timeline -->
            <div class="changelog-container" id="changelog-content">
                <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                    <i class="iconoir-refresh-double" style="font-size: 2rem; animation: spin 1s linear infinite;"></i>
                    <p>Cargando changelog...</p>
                </div>
            </div>
        </main>
    </div>

    <script src="/assets/js/changelog.js"></script>
</body>

</html>

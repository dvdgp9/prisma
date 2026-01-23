<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Release Planner - Prisma</title>
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
        .view-toggle {
            display: flex;
            gap: var(--spacing-xs);
            background: var(--bg-secondary);
            padding: var(--spacing-xs);
            border-radius: var(--radius-md);
        }
        .view-toggle-btn {
            padding: var(--spacing-sm) var(--spacing-md);
            border: none;
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: 0.875rem;
            transition: all var(--transition-fast);
        }
        .view-toggle-btn:hover { color: var(--text-primary); }
        .view-toggle-btn.active {
            background: var(--bg-primary);
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        /* Calendar Styles */
        .calendar-container {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--border-light);
        }
        .calendar-nav {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        .calendar-nav-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border-light);
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all var(--transition-fast);
        }
        .calendar-nav-btn:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        .calendar-month-year {
            font-size: 1.125rem;
            font-weight: var(--font-weight-semibold);
            min-width: 180px;
            text-align: center;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }
        .calendar-day-header {
            padding: var(--spacing-md);
            text-align: center;
            font-size: 0.75rem;
            font-weight: var(--font-weight-semibold);
            color: var(--text-muted);
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-light);
        }
        .calendar-day {
            min-height: 100px;
            padding: var(--spacing-sm);
            border-right: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
            background: var(--bg-primary);
            transition: background var(--transition-fast);
            overflow: hidden;
        }
        .calendar-day:nth-child(7n) { border-right: none; }
        .calendar-day:hover { background: var(--bg-secondary); }
        .calendar-day.other-month { background: var(--bg-secondary); opacity: 0.5; }
        .calendar-day.today { background: rgba(var(--primary-rgb), 0.05); }
        .calendar-day-number {
            font-size: 0.875rem;
            font-weight: var(--font-weight-medium);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-xs);
        }
        .calendar-day.today .calendar-day-number {
            background: var(--primary);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .calendar-release {
            font-size: 0.7rem;
            padding: 3px 6px;
            margin-bottom: 2px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: all var(--transition-fast);
            display: block;
            max-width: 100%;
        }
        .calendar-release:hover {
            opacity: 0.85;
        }
        .calendar-release.status-draft {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border-left: 3px solid var(--text-muted);
        }
        .calendar-release.status-scheduled {
            background: rgba(59, 130, 246, 0.1);
            color: rgb(59, 130, 246);
            border-left: 3px solid rgb(59, 130, 246);
        }
        .calendar-release.status-announced {
            background: rgba(34, 197, 94, 0.1);
            color: rgb(34, 197, 94);
            border-left: 3px solid rgb(34, 197, 94);
        }

        /* List View Styles */
        .releases-list { display: flex; flex-direction: column; gap: var(--spacing-md); }
        .release-card {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--spacing-lg);
            display: flex;
            gap: var(--spacing-lg);
            transition: all var(--transition-fast);
            cursor: pointer;
            border-left: 4px solid transparent;
        }
        .release-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        .release-card.status-draft { border-left-color: var(--text-muted); }
        .release-card.status-scheduled { border-left-color: rgb(59, 130, 246); }
        .release-card.status-announced { border-left-color: rgb(34, 197, 94); }
        .release-date-block {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 70px;
            padding: var(--spacing-md);
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
        }
        .release-date-day {
            font-size: 1.5rem;
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            line-height: 1;
        }
        .release-date-month {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: var(--font-weight-medium);
        }
        .release-content { flex: 1; min-width: 0; }
        .release-title {
            font-size: 1rem;
            font-weight: var(--font-weight-semibold);
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
        }
        .release-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-sm);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .release-meta {
            display: flex;
            gap: var(--spacing-md);
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .release-meta-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        .release-actions {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
            align-items: flex-end;
        }
        .status-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: var(--font-weight-medium);
        }
        .status-badge.draft { background: var(--bg-secondary); color: var(--text-muted); }
        .status-badge.scheduled { background: rgba(59, 130, 246, 0.1); color: rgb(59, 130, 246); }
        .status-badge.announced { background: rgba(34, 197, 94, 0.1); color: rgb(34, 197, 94); }

        .btn-mark-announced {
            padding: var(--spacing-xs) var(--spacing-sm);
            border: 1px solid rgb(34, 197, 94);
            background: transparent;
            color: rgb(34, 197, 94);
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        .btn-mark-announced:hover {
            background: rgb(34, 197, 94);
            color: white;
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
        }
        .filter-tab {
            padding: var(--spacing-sm) var(--spacing-md);
            border: none;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.875rem;
            transition: all var(--transition-fast);
        }
        .filter-tab:hover { color: var(--text-primary); }
        .filter-tab.active {
            background: var(--primary);
            color: white;
        }
        .filter-tab .count {
            background: rgba(255,255,255,0.2);
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: var(--spacing-xs);
        }
        .filter-tab.active .count { background: rgba(255,255,255,0.3); }

        /* Quick Add */
        .quick-add-release {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.03) 0%, rgba(139, 92, 246, 0.03) 100%);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        .quick-add-release .section-title {
            font-size: 0.8rem;
            font-weight: var(--font-weight-semibold);
            color: var(--primary);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        .quick-add-row {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: var(--spacing-md);
            align-items: end;
        }
        .form-group { margin-bottom: 0; }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: var(--font-weight-medium);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-xs);
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }
        .quick-add-expanded {
            display: none;
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 1px dashed var(--border-light);
        }
        .quick-add-expanded.show { display: block; }
        .quick-add-expanded-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-md);
        }
        .quick-add-expanded-full { grid-column: 1 / -1; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--spacing-3xl);
            color: var(--text-muted);
        }
        .empty-state i { font-size: 3rem; margin-bottom: var(--spacing-md); }
        .empty-state h3 { color: var(--text-secondary); margin-bottom: var(--spacing-sm); }

        /* Modal overrides */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalSlideIn 0.2s ease-out;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-lg) var(--spacing-xl);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
            border-bottom: 1px solid var(--border-light);
        }
        .modal-title {
            font-size: 1.125rem;
            font-weight: var(--font-weight-semibold);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        .modal-title i { color: var(--primary); }
        .modal-body {
            padding: var(--spacing-xl);
            background: var(--bg-secondary);
        }
        .modal-body .form-group {
            background: var(--bg-primary);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-light);
        }
        .modal-body .form-group label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }
        .modal-body .form-group input,
        .modal-body .form-group select,
        .modal-body .form-group textarea {
            border: none;
            background: transparent;
            padding: var(--spacing-xs) 0;
            font-size: 0.9rem;
        }
        .modal-body .form-group input:focus,
        .modal-body .form-group select:focus,
        .modal-body .form-group textarea:focus {
            box-shadow: none;
            outline: none;
        }
        .modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-lg) var(--spacing-xl);
            border-top: 1px solid var(--border-light);
            background: var(--bg-primary);
        }
        .modal-footer-right {
            display: flex;
            gap: var(--spacing-sm);
        }
        .close-modal {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 1.25rem;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-fast);
        }
        .close-modal:hover { background: var(--bg-secondary); color: var(--text-primary); }
        .btn { padding: var(--spacing-sm) var(--spacing-lg); border-radius: var(--radius-md); font-size: 0.875rem; cursor: pointer; transition: all var(--transition-fast); display: inline-flex; align-items: center; gap: var(--spacing-xs); font-weight: var(--font-weight-medium); }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-ghost { background: transparent; border: 1px solid var(--border-light); color: var(--text-secondary); }
        .btn-ghost:hover { background: var(--bg-secondary); }
        .btn-danger { background: transparent; color: #ef4444; border: 1px solid #fecaca; }
        .btn-danger:hover { background: #fef2f2; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md); }
        .form-group.full { grid-column: 1 / -1; }
        .modal .form-group { margin-bottom: var(--spacing-md); }
    </style>
</head>

<?php
require_once __DIR__ . '/includes/auth.php';
require_role('superadmin');

$user = get_logged_user();
$userApps = get_user_apps();
?>

<body>
    <div class="dashboard-container">
        <?php $current_page = 'releases'; include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div>
                    <h1 class="page-title">
                        <i class="iconoir-calendar" style="color: var(--primary);"></i>
                        Release Planner
                    </h1>
                    <p class="text-muted">Programa cuándo anunciar tus funcionalidades</p>
                </div>
                <div class="header-actions">
                    <div class="view-toggle">
                        <button class="view-toggle-btn active" data-view="calendar" onclick="switchView('calendar')">
                            <i class="iconoir-calendar"></i> Calendario
                        </button>
                        <button class="view-toggle-btn" data-view="list" onclick="switchView('list')">
                            <i class="iconoir-list"></i> Lista
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Add Release -->
            <div class="quick-add-release">
                <div class="section-title">
                    <i class="iconoir-plus-circle"></i> Nuevo lanzamiento
                </div>
                <form id="quick-add-form" onsubmit="createRelease(event)">
                    <div class="quick-add-row">
                        <div class="form-group">
                            <label for="qa-title">Título *</label>
                            <input type="text" id="qa-title" placeholder="Ej: Generador de cursos con IA" required>
                        </div>
                        <div class="form-group">
                            <label for="qa-completed">Completado</label>
                            <input type="date" id="qa-completed" required>
                        </div>
                        <div class="form-group">
                            <label for="qa-announce">Anunciar el</label>
                            <input type="date" id="qa-announce" required>
                        </div>
                        <div class="form-group" style="display: flex; align-items: end; gap: var(--spacing-sm);">
                            <button type="button" class="btn btn-ghost" onclick="toggleQuickAddExpanded()">
                                <i class="iconoir-more-horiz"></i>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="iconoir-plus"></i> Añadir
                            </button>
                        </div>
                    </div>
                    <div class="quick-add-expanded" id="quick-add-expanded">
                        <div class="quick-add-expanded-grid">
                            <div class="form-group">
                                <label for="qa-app">Aplicación</label>
                                <select id="qa-app">
                                    <option value="">Sin aplicación</option>
                                    <?php foreach ($userApps as $app): ?>
                                        <option value="<?php echo $app['id']; ?>"><?php echo htmlspecialchars($app['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="qa-link">Enlace (opcional)</label>
                                <input type="url" id="qa-link" placeholder="https://...">
                            </div>
                            <div class="form-group quick-add-expanded-full">
                                <label for="qa-description">Descripción</label>
                                <textarea id="qa-description" rows="2" placeholder="Qué hace esta funcionalidad..."></textarea>
                            </div>
                            <div class="form-group quick-add-expanded-full">
                                <label for="qa-notes">Notas internas</label>
                                <textarea id="qa-notes" rows="2" placeholder="Cosas que quieras recordar..."></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab active" data-status="" onclick="filterByStatus('')">
                    Todos <span class="count" id="count-all">0</span>
                </button>
                <button class="filter-tab" data-status="scheduled" onclick="filterByStatus('scheduled')">
                    Programados <span class="count" id="count-scheduled">0</span>
                </button>
                <button class="filter-tab" data-status="announced" onclick="filterByStatus('announced')">
                    Anunciados <span class="count" id="count-announced">0</span>
                </button>
                <button class="filter-tab" data-status="draft" onclick="filterByStatus('draft')">
                    Borradores <span class="count" id="count-draft">0</span>
                </button>
            </div>

            <!-- Calendar View -->
            <div id="calendar-view" class="calendar-container">
                <div class="calendar-header">
                    <div class="calendar-nav">
                        <button class="calendar-nav-btn" onclick="changeMonth(-1)">
                            <i class="iconoir-nav-arrow-left"></i>
                        </button>
                        <span class="calendar-month-year" id="calendar-month-year"></span>
                        <button class="calendar-nav-btn" onclick="changeMonth(1)">
                            <i class="iconoir-nav-arrow-right"></i>
                        </button>
                    </div>
                    <button class="btn btn-ghost" onclick="goToToday()">Hoy</button>
                </div>
                <div class="calendar-grid" id="calendar-grid">
                    <div class="calendar-day-header">Lun</div>
                    <div class="calendar-day-header">Mar</div>
                    <div class="calendar-day-header">Mié</div>
                    <div class="calendar-day-header">Jue</div>
                    <div class="calendar-day-header">Vie</div>
                    <div class="calendar-day-header">Sáb</div>
                    <div class="calendar-day-header">Dom</div>
                </div>
            </div>

            <!-- List View -->
            <div id="list-view" class="releases-list" style="display: none;">
                <!-- Releases loaded dynamically -->
            </div>

            <!-- Empty State -->
            <div class="empty-state" id="empty-state" style="display: none;">
                <i class="iconoir-calendar"></i>
                <h3>No hay releases programados</h3>
                <p>Añade tu primer release arriba</p>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="release-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="iconoir-edit-pencil"></i> Editar Release</h3>
                <button class="close-modal" onclick="closeModal()">
                    <i class="iconoir-xmark"></i>
                </button>
            </div>
            <form id="release-form" onsubmit="saveRelease(event)">
                <div class="modal-body">
                    <input type="hidden" id="edit-id">
                    <div class="form-group full">
                        <label for="edit-title">Título *</label>
                        <input type="text" id="edit-title" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-completed">Fecha completado *</label>
                            <input type="date" id="edit-completed" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-announce">Fecha anuncio *</label>
                            <input type="date" id="edit-announce" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-app">Aplicación</label>
                            <select id="edit-app">
                                <option value="">Sin aplicación</option>
                                <?php foreach ($userApps as $app): ?>
                                    <option value="<?php echo $app['id']; ?>"><?php echo htmlspecialchars($app['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit-status">Estado</label>
                            <select id="edit-status">
                                <option value="draft">Borrador</option>
                                <option value="scheduled">Programado</option>
                                <option value="announced">Anunciado</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group full">
                        <label for="edit-link">Enlace</label>
                        <input type="url" id="edit-link" placeholder="https://...">
                    </div>
                    <div class="form-group full">
                        <label for="edit-description">Descripción</label>
                        <textarea id="edit-description" rows="3"></textarea>
                    </div>
                    <div class="form-group full">
                        <label for="edit-notes">Notas internas</label>
                        <textarea id="edit-notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="deleteRelease()">
                        <i class="iconoir-trash"></i> Eliminar
                    </button>
                    <div class="modal-footer-right">
                        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="iconoir-check"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script>
        // State
        let releases = [];
        let currentView = 'calendar';
        let currentMonth = new Date();
        let currentFilter = '';

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            // Set default dates
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('qa-completed').value = today;
            document.getElementById('qa-announce').value = today;
            
            loadReleases();
        });

        // API Functions
        async function loadReleases() {
            try {
                const response = await fetch('/api/releases.php');
                const data = await response.json();
                releases = data.data || [];
                updateCounts();
                renderCurrentView();
            } catch (error) {
                showToast('Error cargando releases', 'error');
            }
        }

        async function createRelease(e) {
            e.preventDefault();
            
            const payload = {
                title: document.getElementById('qa-title').value,
                completed_at: document.getElementById('qa-completed').value,
                announce_at: document.getElementById('qa-announce').value,
                app_id: document.getElementById('qa-app').value || null,
                link: document.getElementById('qa-link').value || null,
                description: document.getElementById('qa-description').value || null,
                internal_notes: document.getElementById('qa-notes').value || null
            };

            try {
                const response = await fetch('/api/releases.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                if (response.ok) {
                    showToast('Release programado', 'success');
                    document.getElementById('quick-add-form').reset();
                    document.getElementById('qa-completed').value = new Date().toISOString().split('T')[0];
                    document.getElementById('qa-announce').value = new Date().toISOString().split('T')[0];
                    document.getElementById('quick-add-expanded').classList.remove('show');
                    loadReleases();
                } else {
                    const data = await response.json();
                    showToast(data.error || 'Error', 'error');
                }
            } catch (error) {
                showToast('Error de conexión', 'error');
            }
        }

        async function saveRelease(e) {
            e.preventDefault();
            
            const payload = {
                id: document.getElementById('edit-id').value,
                title: document.getElementById('edit-title').value,
                completed_at: document.getElementById('edit-completed').value,
                announce_at: document.getElementById('edit-announce').value,
                status: document.getElementById('edit-status').value,
                app_id: document.getElementById('edit-app').value || null,
                link: document.getElementById('edit-link').value || null,
                description: document.getElementById('edit-description').value || null,
                internal_notes: document.getElementById('edit-notes').value || null
            };

            try {
                const response = await fetch('/api/releases.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                if (response.ok) {
                    showToast('Release actualizado', 'success');
                    closeModal();
                    loadReleases();
                } else {
                    const data = await response.json();
                    showToast(data.error || 'Error', 'error');
                }
            } catch (error) {
                showToast('Error de conexión', 'error');
            }
        }

        async function deleteRelease() {
            if (!confirm('¿Eliminar este release?')) return;
            
            const id = document.getElementById('edit-id').value;
            
            try {
                const response = await fetch('/api/releases.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                
                if (response.ok) {
                    showToast('Release eliminado', 'success');
                    closeModal();
                    loadReleases();
                }
            } catch (error) {
                showToast('Error de conexión', 'error');
            }
        }

        async function markAsAnnounced(id, e) {
            e.stopPropagation();
            
            try {
                const response = await fetch('/api/releases.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, status: 'announced' })
                });
                
                if (response.ok) {
                    showToast('¡Marcado como anunciado!', 'success');
                    loadReleases();
                }
            } catch (error) {
                showToast('Error', 'error');
            }
        }

        // View Functions
        function switchView(view) {
            currentView = view;
            document.querySelectorAll('.view-toggle-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.view === view);
            });
            document.getElementById('calendar-view').style.display = view === 'calendar' ? 'block' : 'none';
            document.getElementById('list-view').style.display = view === 'list' ? 'flex' : 'none';
            renderCurrentView();
        }

        function renderCurrentView() {
            const filtered = currentFilter 
                ? releases.filter(r => r.status === currentFilter)
                : releases;
            
            if (currentView === 'calendar') {
                renderCalendar(filtered);
            } else {
                renderList(filtered);
            }
            
            document.getElementById('empty-state').style.display = filtered.length === 0 ? 'block' : 'none';
        }

        function filterByStatus(status) {
            currentFilter = status;
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.status === status);
            });
            renderCurrentView();
        }

        function updateCounts() {
            document.getElementById('count-all').textContent = releases.length;
            document.getElementById('count-scheduled').textContent = releases.filter(r => r.status === 'scheduled').length;
            document.getElementById('count-announced').textContent = releases.filter(r => r.status === 'announced').length;
            document.getElementById('count-draft').textContent = releases.filter(r => r.status === 'draft').length;
        }

        // Calendar Functions
        function renderCalendar(filteredReleases) {
            const grid = document.getElementById('calendar-grid');
            const monthYear = document.getElementById('calendar-month-year');
            
            // Clear previous days (keep headers)
            const headers = grid.querySelectorAll('.calendar-day-header');
            grid.innerHTML = '';
            headers.forEach(h => grid.appendChild(h));
            
            const year = currentMonth.getFullYear();
            const month = currentMonth.getMonth();
            
            const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            monthYear.textContent = `${monthNames[month]} ${year}`;
            
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            
            const now = new Date();
            const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
            
            // Start from Monday (0 = Monday in our grid)
            let startOffset = firstDay.getDay() - 1;
            if (startOffset < 0) startOffset = 6;
            
            // Previous month days
            const prevMonthLast = new Date(year, month, 0);
            for (let i = startOffset - 1; i >= 0; i--) {
                const dayEl = createDayElement(prevMonthLast.getDate() - i, true, false, []);
                grid.appendChild(dayEl);
            }
            
            // Current month days
            for (let day = 1; day <= lastDay.getDate(); day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dayReleases = filteredReleases.filter(r => r.announce_at === dateStr);
                const isToday = dateStr === todayStr;
                const dayEl = createDayElement(day, false, isToday, dayReleases);
                grid.appendChild(dayEl);
            }
            
            // Next month days to complete grid
            const totalCells = grid.querySelectorAll('.calendar-day').length + 7; // +7 for headers
            const remaining = 42 - (totalCells - 7); // 6 weeks max
            for (let day = 1; day <= remaining && day <= 14; day++) {
                const dayEl = createDayElement(day, true, false, []);
                grid.appendChild(dayEl);
            }
        }

        function createDayElement(day, isOtherMonth, isToday, dayReleases) {
            const div = document.createElement('div');
            div.className = 'calendar-day';
            if (isOtherMonth) div.classList.add('other-month');
            if (isToday) div.classList.add('today');
            
            const dayNum = document.createElement('div');
            dayNum.className = 'calendar-day-number';
            dayNum.textContent = day;
            div.appendChild(dayNum);
            
            dayReleases.forEach(release => {
                const releaseEl = document.createElement('div');
                releaseEl.className = `calendar-release status-${release.status}`;
                releaseEl.textContent = release.title;
                releaseEl.onclick = () => openEditModal(release);
                div.appendChild(releaseEl);
            });
            
            return div;
        }

        function changeMonth(delta) {
            currentMonth.setMonth(currentMonth.getMonth() + delta);
            renderCurrentView();
        }

        function goToToday() {
            currentMonth = new Date();
            renderCurrentView();
        }

        // List Functions
        function renderList(filteredReleases) {
            const container = document.getElementById('list-view');
            
            // Sort by announce_at
            const sorted = [...filteredReleases].sort((a, b) => 
                new Date(a.announce_at) - new Date(b.announce_at)
            );
            
            container.innerHTML = sorted.map(release => {
                const announceDate = new Date(release.announce_at);
                const day = announceDate.getDate();
                const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                const month = monthNames[announceDate.getMonth()];
                
                const statusLabels = { draft: 'Borrador', scheduled: 'Programado', announced: 'Anunciado' };
                
                return `
                    <div class="release-card status-${release.status}" onclick="openEditModal(${JSON.stringify(release).replace(/"/g, '&quot;')})">
                        <div class="release-date-block">
                            <span class="release-date-day">${day}</span>
                            <span class="release-date-month">${month}</span>
                        </div>
                        <div class="release-content">
                            <div class="release-title">${escapeHtml(release.title)}</div>
                            ${release.description ? `<div class="release-description">${escapeHtml(release.description)}</div>` : ''}
                            <div class="release-meta">
                                ${release.app_name ? `<span class="release-meta-item"><i class="iconoir-app-window"></i> ${escapeHtml(release.app_name)}</span>` : ''}
                                <span class="release-meta-item"><i class="iconoir-check-circle"></i> Completado: ${formatDate(release.completed_at)}</span>
                                ${release.link ? `<span class="release-meta-item"><i class="iconoir-link"></i> Con enlace</span>` : ''}
                            </div>
                        </div>
                        <div class="release-actions">
                            <span class="status-badge ${release.status}">${statusLabels[release.status]}</span>
                            ${release.status !== 'announced' ? `
                                <button class="btn-mark-announced" onclick="markAsAnnounced(${release.id}, event)">
                                    <i class="iconoir-check"></i> Marcar anunciado
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Modal Functions
        function openEditModal(release) {
            document.getElementById('edit-id').value = release.id;
            document.getElementById('edit-title').value = release.title;
            document.getElementById('edit-completed').value = release.completed_at;
            document.getElementById('edit-announce').value = release.announce_at;
            document.getElementById('edit-status').value = release.status;
            document.getElementById('edit-app').value = release.app_id || '';
            document.getElementById('edit-link').value = release.link || '';
            document.getElementById('edit-description').value = release.description || '';
            document.getElementById('edit-notes').value = release.internal_notes || '';
            
            document.getElementById('release-modal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('release-modal').classList.remove('show');
        }

        function toggleQuickAddExpanded() {
            document.getElementById('quick-add-expanded').classList.toggle('show');
        }

        // Utility Functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="iconoir-${type === 'success' ? 'check-circle' : type === 'error' ? 'warning-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            toast.style.cssText = `
                display: flex; align-items: center; gap: 0.5rem;
                padding: 0.75rem 1rem; border-radius: 0.5rem;
                background: ${type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white; font-size: 0.875rem; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideIn 0.3s ease;
            `;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Close modal on backdrop click
        document.getElementById('release-modal').addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) closeModal();
        });

        // Close modal on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>

    <style>
        #toast-container {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            z-index: 2000;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
    </style>
</body>

</html>

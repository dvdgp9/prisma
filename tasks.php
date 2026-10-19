<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tareas - Prisma</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" href="/favicon.png?v=2">

    <?php include __DIR__ . '/includes/pwa-head.php'; ?>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Iconoir Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/tokens.css?v=2.7">
    <link rel="stylesheet" href="/assets/css/styles.css?v=5.4">
    <link rel="stylesheet" href="/assets/css/tasks.css?v=3.1">
</head>

<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = get_logged_user();
$userApps = get_user_apps();
?>

<body data-user-id="<?php echo (int) $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['full_name'] ?: $user['username'], ENT_QUOTES); ?>">
    <div class="dashboard-container">
        <?php $current_page = 'tasks'; include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div>
                    <div class="page-title-row">
                        <h1 class="page-title">Tareas</h1>
                        <span class="done-today" id="done-today" hidden>
                            <i class="iconoir-check"></i>
                            <span id="done-today-text"></span>
                        </span>
                    </div>
                    <p class="text-muted">Apunta rápidamente lo que necesitas recordar</p>
                </div>
                <div class="header-actions">
                    <label class="tasks-search">
                        <i class="iconoir-search"></i>
                        <input type="search" id="tasks-search" placeholder="Buscar" autocomplete="off" aria-label="Buscar tareas">
                        <kbd class="tasks-search-kbd">/</kbd>
                    </label>
                    <div class="view-toggle" id="view-toggle" role="group" aria-label="Cambiar vista">
                        <button type="button" class="view-toggle-btn" data-view="agenda" onclick="setTasksView('agenda')" title="Vista por fechas">
                            <i class="iconoir-calendar"></i>
                            <span>Agenda</span>
                        </button>
                        <button type="button" class="view-toggle-btn" data-view="list" onclick="setTasksView('list')" title="Lista simple">
                            <i class="iconoir-list"></i>
                            <span>Lista</span>
                        </button>
                    </div>
                    <div class="filter-group">
                        <select id="app-filter" class="sort-select" onchange="loadTasks()">
                            <option value="">Todas las apps</option>
                            <?php foreach ($userApps as $app): ?>
                                <option value="<?php echo $app['id']; ?>"><?php echo htmlspecialchars($app['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-chips">
                        <label class="filter-chip">
                            <input type="checkbox" id="show-completed" onchange="loadTasks()">
                            <span class="chip-content">
                                <i class="iconoir-check-circle"></i>
                                <span>Completadas</span>
                            </span>
                        </label>
                        <label class="filter-chip">
                            <input type="checkbox" id="show-shared" onchange="loadTasks()">
                            <span class="chip-content">
                                <i class="iconoir-group"></i>
                                <span>Del equipo</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Quick Add -->
            <div class="quick-add-container">
                <div class="quick-add-input-wrapper">
                    <i class="iconoir-plus quick-add-icon"></i>
                    <input
                        type="text"
                        id="quick-add-input"
                        class="quick-add-input"
                        placeholder="Escribe una tarea y pulsa Enter…  (prueba: mañana, viernes, 15/07, @app)"
                        autocomplete="off"
                    >
                    <div class="quick-add-actions">
                        <button type="button" class="quick-add-btn" id="expand-btn" title="Más opciones">
                            <i class="iconoir-more-horiz"></i>
                        </button>
                    </div>
                </div>

                <!-- Chips de detección en vivo (fecha / app a partir del texto) -->
                <div class="quick-add-chips" id="quick-add-chips" hidden></div>
                
                <!-- Expanded options (hidden by default) -->
                <div class="quick-add-expanded" id="quick-add-expanded">
                    <div class="expanded-row">
                        <select id="quick-add-app" class="expanded-select">
                            <option value="">Sin aplicación</option>
                            <?php foreach ($userApps as $app): ?>
                                <option value="<?php echo $app['id']; ?>"><?php echo htmlspecialchars($app['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="date-input-wrapper">
                            <i class="iconoir-calendar"></i>
                            <input type="date" id="quick-add-due-date" class="expanded-date" placeholder="Fecha límite">
                        </div>
                    </div>
                    <textarea 
                        id="quick-add-description" 
                        class="expanded-textarea" 
                        placeholder="Descripción (opcional)..."
                        rows="2"
                    ></textarea>
                </div>
            </div>

            <!-- Tasks List -->
            <div class="tasks-list" id="tasks-list">
                <!-- Tasks will be loaded here -->
            </div>

            <p class="tasks-shortcuts-hint">
                <kbd>/</kbd> buscar · <kbd>n</kbd> nueva · <kbd>j</kbd> <kbd>k</kbd> moverse · <kbd>x</kbd> completar · <kbd>p</kbd> posponer · <kbd>r</kbd> renombrar · <kbd>s</kbd> compartir · <kbd>e</kbd> abrir · doble clic en el título para renombrar
            </p>

            <!-- Empty State -->
            <div class="empty-state" id="empty-state" style="display: none;">
                <i class="iconoir-check-circle" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                <h3>No hay tareas pendientes</h3>
                <p class="text-muted">Escribe algo arriba para crear tu primera tarea</p>
            </div>
        </main>
    </div>

    <!-- Task Detail Modal -->
    <div class="modal" id="task-modal" role="dialog" aria-modal="true" aria-labelledby="task-modal-heading">
        <div class="modal-content modal-wide task-modal-content" id="task-modal-content">
            <div class="modal-header">
                <div class="modal-header-left">
                    <i class="iconoir-task-list modal-header-icon"></i>
                    <h3 class="modal-title" id="task-modal-heading">Tarea</h3>
                    <span class="modal-request-id" id="task-id-display"></span>
                    <a class="task-source-link" id="task-source-link" href="#" hidden>
                        <i class="iconoir-light-bulb"></i>
                        <span id="task-source-link-text"></span>
                    </a>
                </div>
                <button type="button" class="close-modal" onclick="requestCloseTaskModal()" aria-label="Cerrar">
                    <i class="iconoir-xmark"></i>
                </button>
            </div>

            <!-- Barra de propiedades: completar, fecha, app, compartir y metadatos.
                 Se guardan al momento (fuera del form). La pinta renderTaskMetaBar(). -->
            <div class="request-meta-bar task-meta-bar" id="task-meta-bar"></div>

            <form id="task-form" onsubmit="saveTask(event)">
                <input type="hidden" id="task-id">

                <div class="task-modal-body">
                    <div class="form-group">
                        <label for="task-title" class="visually-hidden">Título</label>
                        <input type="text" id="task-title" class="task-title-field" required maxlength="500" placeholder="Título de la tarea" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="task-description">
                            <i class="iconoir-align-left"></i> Descripción
                        </label>
                        <textarea id="task-description" class="task-description-field" rows="3" placeholder="Añade detalles, enlaces o notas"></textarea>
                    </div>

                    <!-- Attachments -->
                    <div class="form-group">
                        <div class="attachments-header">
                            <label>
                                <i class="iconoir-attachment"></i> Archivos adjuntos
                            </label>
                            <span class="badge-count-inline" id="task-attachment-count"></span>
                        </div>

                        <div class="file-upload-area file-upload-area--compact" id="task-file-upload" role="button" tabindex="0" aria-label="Añadir archivos">
                            <i class="iconoir-cloud-upload"></i>
                            <p>Haz clic, arrastra o pega una captura</p>
                            <input type="file" id="task-file-input" class="task-file-input" multiple>
                        </div>
                        <p class="task-upload-status" id="task-upload-status" role="status" hidden></p>

                        <div id="task-attachments" class="task-attachments-list">
                            <!-- Loaded dynamically -->
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-danger-outline btn-sm modal-footer-delete" id="task-delete-btn" onclick="deleteTask()">
                        <i class="iconoir-trash"></i> Eliminar
                    </button>
                    <span class="task-unsaved-hint" id="task-unsaved-hint" hidden>Cambios sin guardar</span>
                    <button type="button" class="btn btn-ghost" onclick="requestCloseTaskModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" title="Guardar (Ctrl/Cmd + Enter)">
                        <i class="iconoir-check"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script>
        // Apps del usuario para el parser de creación rápida.
        const QUICK_APPS = <?php echo json_encode(array_map(function ($a) {
            return ['id' => (int) $a['id'], 'name' => $a['name']];
        }, $userApps), JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="/assets/js/task-parser.js?v=6"></script>
    <script src="/assets/js/file-viewer.js?v=1"></script>
    <script src="/assets/js/tasks.js?v=13"></script>
    <script src="/assets/js/tasks-interactions.js?v=4"></script>
    <script src="/assets/js/pwa.js?v=1.1"></script>
</body>

</html>

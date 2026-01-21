<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Tareas - Prisma</title>
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
    <link rel="stylesheet" href="/assets/css/tasks.css">
</head>

<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = get_logged_user();
$userApps = get_user_apps();
?>

<body>
    <div class="dashboard-container">
        <?php $current_page = 'tasks'; include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div>
                    <h1 class="page-title">Mis Tareas</h1>
                    <p class="text-muted">Apunta rápidamente lo que necesitas recordar</p>
                </div>
                <div class="header-actions">
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
                        placeholder="Escribe una tarea y pulsa Enter..."
                        autocomplete="off"
                    >
                    <div class="quick-add-actions">
                        <button type="button" class="quick-add-btn" id="expand-btn" title="Más opciones">
                            <i class="iconoir-more-horiz"></i>
                        </button>
                    </div>
                </div>
                
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
                    <div class="expanded-row">
                        <label class="expanded-toggle">
                            <input type="checkbox" id="quick-add-shared">
                            <span>Compartir con equipo</span>
                        </label>
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

            <!-- Empty State -->
            <div class="empty-state" id="empty-state" style="display: none;">
                <i class="iconoir-check-circle" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                <h3>No hay tareas pendientes</h3>
                <p class="text-muted">Escribe algo arriba para crear tu primera tarea</p>
            </div>
        </main>
    </div>

    <!-- Task Detail Modal -->
    <div class="modal" id="task-modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title">Editar Tarea</h3>
                <button class="close-modal" onclick="closeTaskModal()">×</button>
            </div>
            
            <form id="task-form" onsubmit="saveTask(event)">
                <input type="hidden" id="task-id">
                
                <div class="form-group">
                    <label for="task-title">Título *</label>
                    <input type="text" id="task-title" required>
                </div>
                
                <div class="form-group">
                    <label for="task-description">Descripción</label>
                    <textarea id="task-description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="task-app">Aplicación</label>
                    <select id="task-app">
                        <option value="">Sin aplicación</option>
                        <?php foreach ($userApps as $app): ?>
                            <option value="<?php echo $app['id']; ?>"><?php echo htmlspecialchars($app['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="task-due-date">
                        <i class="iconoir-calendar"></i> Fecha límite
                    </label>
                    <input type="date" id="task-due-date">
                </div>
                
                <div class="form-group">
                    <label class="toggle-label">
                        <input type="checkbox" id="task-shared">
                        <span>Compartir con el equipo</span>
                    </label>
                </div>

                <!-- Attachments -->
                <div class="form-group">
                    <label>Archivos adjuntos</label>
                    <div id="task-attachments" class="task-attachments-list">
                        <!-- Loaded dynamically -->
                    </div>
                    <div class="file-upload-area" id="task-file-upload">
                        <p>📎 Arrastra archivos aquí o haz clic</p>
                        <p class="text-small text-muted">Máximo 5MB</p>
                        <input type="file" id="task-file-input" style="display: none;" multiple>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="deleteTask()">Eliminar</button>
                    <button type="button" class="btn btn-outline" onclick="closeTaskModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script src="/assets/js/tasks.js"></script>
</body>

</html>

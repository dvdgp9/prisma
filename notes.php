<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas - Prisma</title>
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
    <link rel="stylesheet" href="/assets/css/styles.css?v=5.2">
    <link rel="stylesheet" href="/assets/css/notes.css?v=1">
</head>

<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = get_logged_user();
$userApps = get_user_apps();
$noteApps = array_map(function ($app) {
    return ['id' => (int) $app['id'], 'name' => $app['name']];
}, $userApps);
?>

<body class="notes-page" data-user-id="<?php echo (int) $user['id']; ?>">
    <div class="dashboard-container">
        <?php $current_page = 'notes'; include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content notes-main">
            <div class="content-header notes-header">
                <div>
                    <h1 class="page-title">Notas</h1>
                    <p class="text-muted notes-subtitle">Guarda y comparte lo que el equipo necesita tener a mano</p>
                </div>
                <div class="notes-header-actions">
                    <label class="notes-search">
                        <i class="iconoir-search" aria-hidden="true"></i>
                        <input type="search" id="notes-search" placeholder="Buscar en notas" autocomplete="off" aria-label="Buscar en notas">
                        <kbd class="notes-kbd notes-search-kbd" aria-hidden="true">/</kbd>
                    </label>
                    <button type="button" class="btn btn-primary" id="notes-new-btn" title="Nueva nota (N)" aria-label="Nueva nota">
                        <i class="iconoir-plus" aria-hidden="true"></i>
                        <span>Nueva nota</span>
                    </button>
                </div>
            </div>

            <div class="notes-toolbar">
                <div class="notes-filter" role="tablist" aria-label="Qué notas ver">
                    <button type="button" class="notes-filter-btn is-active" data-filter="all" role="tab" aria-selected="true">Todas</button>
                    <button type="button" class="notes-filter-btn" data-filter="mine" role="tab" aria-selected="false">Mías</button>
                    <button type="button" class="notes-filter-btn" data-filter="shared" role="tab" aria-selected="false">Compartidas conmigo</button>
                </div>
                <?php if (!empty($noteApps)): ?>
                    <select id="notes-app-filter" class="notes-app-filter" aria-label="Filtrar por aplicación">
                        <option value="">Todas las apps</option>
                        <?php foreach ($noteApps as $app): ?>
                            <option value="<?php echo $app['id']; ?>"><?php echo htmlspecialchars($app['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <select id="notes-app-filter" class="notes-app-filter" aria-label="Filtrar por aplicación" hidden>
                        <option value="">Todas las apps</option>
                    </select>
                <?php endif; ?>
            </div>

            <div class="notes-workspace" id="notes-workspace">
                <section class="notes-list-pane" aria-label="Lista de notas">
                    <div class="notes-list" id="notes-list"></div>
                </section>
                <section class="note-pane" id="note-pane" aria-label="Nota"></section>
            </div>
        </main>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script id="notes-apps-data" type="application/json"><?php echo json_encode($noteApps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?></script>
    <!-- Markdown seguro (mismas versiones que index.php) -->
    <script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.11/dist/purify.min.js"></script>
    <script src="/assets/js/notes-helpers.js?v=1"></script>
    <script src="/assets/js/notes.js?v=1"></script>
    <script src="/assets/js/pwa.js?v=1.1"></script>
</body>

</html>

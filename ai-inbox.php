<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota rápida - Prisma</title>
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
    <link rel="stylesheet" href="/assets/css/tokens.css?v=2.4">
    <link rel="stylesheet" href="/assets/css/styles.css?v=3.1">
</head>

<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = get_logged_user();
$userApps = get_user_apps();
?>

<body>
    <div class="dashboard-container">
        <?php $current_page = 'ai-inbox'; include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div>
                    <h1 class="page-title">Nota rápida</h1>
                    <p class="text-muted">Pega tus apuntes de reunión y la IA propondrá mejoras y tareas. Tú revisas y decides.</p>
                </div>
            </div>

            <!-- Paso 1: Entrada de nota -->
            <section id="ai-step-note" class="ai-step">
                <div class="ai-note-card">
                    <textarea id="ai-note-input" class="ai-note-textarea"
                        placeholder="Escribe o pega aquí tu nota tal cual... Por ejemplo:&#10;&#10;Reunión con marketing 10/06&#10;- En la web de reservas el botón de pagar falla en móvil, urgente&#10;- Añadir filtro por fecha al listado de pedidos&#10;- Llamar a Juan para renovar el dominio"
                        maxlength="10000"></textarea>
                    <div class="ai-note-footer">
                        <span id="ai-note-counter" class="ai-note-counter">0 / 10.000</span>
                        <button id="ai-analyze-btn" class="btn btn-primary" onclick="analyzeNote()">
                            <i class="iconoir-sparks"></i>
                            Analizar con IA
                        </button>
                    </div>
                </div>
                <p id="ai-note-error" class="ai-error" hidden></p>
            </section>

            <!-- Estado de carga -->
            <section id="ai-step-loading" class="ai-step" hidden>
                <div class="ai-loading-card">
                    <div class="ai-loading-spinner"></div>
                    <h3>Analizando tu nota...</h3>
                    <p class="text-muted">La IA está identificando mejoras, tareas y a qué aplicación pertenece cada cosa.
                        Suele tardar unos segundos.</p>
                </div>
            </section>

            <!-- Paso 2: Revisión -->
            <section id="ai-step-review" class="ai-step" hidden>
                <div class="ai-review-intro">
                    <i class="iconoir-eye"></i>
                    <div>
                        <strong id="ai-review-summary">La IA ha detectado varios elementos.</strong>
                        <p>Revisa cada propuesta: puedes <strong>editar el texto, cambiar el tipo, la app, la prioridad y
                            las subtareas</strong>, o descartar lo que no quieras.
                            <strong>No se creará nada hasta que pulses "Crear seleccionados".</strong></p>
                    </div>
                </div>

                <div id="ai-review-list"></div>

                <div class="ai-review-actions">
                    <button class="btn btn-outline" onclick="backToNote()">
                        <i class="iconoir-arrow-left"></i>
                        Volver a la nota
                    </button>
                    <button id="ai-confirm-btn" class="btn btn-primary" onclick="confirmItems()">
                        <i class="iconoir-check"></i>
                        <span id="ai-confirm-label">Crear seleccionados</span>
                    </button>
                </div>
                <p id="ai-review-error" class="ai-error" hidden></p>
            </section>

            <!-- Paso 3: Resultado -->
            <section id="ai-step-done" class="ai-step" hidden>
                <div class="ai-done-card">
                    <i class="iconoir-check-circle ai-done-icon"></i>
                    <h3 id="ai-done-title">¡Hecho!</h3>
                    <div id="ai-done-list"></div>
                    <button class="btn btn-primary" onclick="resetFlow()">
                        <i class="iconoir-plus"></i>
                        Nueva nota
                    </button>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Apps disponibles para los selectores de la revisión
        const AI_USER_APPS = <?php echo json_encode(array_map(function ($a) {
            return ['id' => (int) $a['id'], 'name' => $a['name'], 'company' => $a['company_name']];
        }, $userApps), JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="/assets/js/ai-inbox.js?v=ai4"></script>
    <script src="/assets/js/pwa.js"></script>
</body>

</html>

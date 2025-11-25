<?php
/**
 * Public Request Form - Allows external users to submit improvement requests
 */

require_once __DIR__ . '/config/database.php';

// Get company slug from URL
$company_slug = $_GET['empresa'] ?? '';

if (empty($company_slug)) {
    http_response_code(404);
    die('Empresa no especificada');
}

// Get company info
$db = getDB();
$stmt = $db->prepare("SELECT id, name FROM companies WHERE LOWER(name) = LOWER(?)");
$stmt->execute([$company_slug]);
$company = $stmt->fetch();

if (!$company) {
    http_response_code(404);
    die('Empresa no encontrada');
}

// Get apps for this company
$stmt = $db->prepare("SELECT id, name FROM apps WHERE company_id = ? ORDER BY name");
$stmt->execute([$company['id']]);
$apps = $stmt->fetchAll();

$success = false;
$error = '';

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Mejora - <?php echo htmlspecialchars($company['name']); ?></title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card" style="max-width: 600px;">
            <div class="login-header">
                <div class="login-logo">Prisma</div>
                <h2 style="margin: 0.5rem 0 0 0; font-size: 1.25rem; color: var(--text-primary);">
                    Solicitar Mejora
                </h2>
                <p class="text-muted" style="margin-top: 0.5rem;">
                    <?php echo htmlspecialchars($company['name']); ?>
                </p>
            </div>

            <?php if ($success): ?>
                <div
                    style="padding: 1.5rem; background: rgba(92, 184, 92, 0.1); border-left: 4px solid #5CB85C; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <i class="iconoir-check" style="font-size: 1.5rem; color: #5CB85C;"></i>
                        <div>
                            <strong style="color: #5CB85C;">¡Solicitud enviada!</strong>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: var(--text-secondary);">
                                Tu solicitud será revisada por el equipo. Te contactaremos al correo proporcionado.
                            </p>
                        </div>
                    </div>
                </div>
                <a href="?empresa=<?php echo urlencode($company_slug); ?>" class="btn btn-primary"
                    style="width: 100%; text-align: center;">
                    Enviar otra solicitud
                </a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form id="public-request-form" method="POST" action="/api/public-request.php">
                    <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">

                    <div class="form-group">
                        <label for="requester_name">Tu nombre *</label>
                        <input type="text" id="requester_name" name="requester_name" required placeholder="Ej: Juan Pérez">
                    </div>

                    <div class="form-group">
                        <label for="requester_email">Tu correo electrónico *</label>
                        <input type="email" id="requester_email" name="requester_email" required placeholder="tu@email.com">
                    </div>

                    <div class="form-group">
                        <label for="app_id">Aplicación *</label>
                        <select id="app_id" name="app_id" required>
                            <option value="">Selecciona una aplicación</option>
                            <?php foreach ($apps as $app): ?>
                                <option value="<?php echo $app['id']; ?>">
                                    <?php echo htmlspecialchars($app['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Título de la mejora *</label>
                        <input type="text" id="title" name="title" required placeholder="Ej: Añadir filtro por fecha">
                    </div>

                    <div class="form-group">
                        <label for="description">Descripción *</label>
                        <textarea id="description" name="description" rows="6" required
                            placeholder="Describe la mejora que necesitas..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        <i class="iconoir-send"></i>
                        Enviar Solicitud
                    </button>

                    <p class="text-muted" style="text-align: center; margin-top: 1rem; font-size: 0.8125rem;">
                        Tu solicitud será revisada por un administrador antes de ser procesada.
                    </p>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('public-request-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="iconoir-refresh"></i> Enviando...';

            const formData = new FormData(e.target);

            try {
                const response = await fetch('/api/public-request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.fromEntries(formData))
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = '?empresa=<?php echo urlencode($company_slug); ?>&success=1';
                } else {
                    alert(data.error || 'Error al enviar la solicitud');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                alert('Error al enviar la solicitud. Por favor, intenta de nuevo.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });

        <?php if (isset($_GET['success'])): ?>
            window.history.replaceState({}, '', '?empresa=<?php echo urlencode($company_slug); ?>');
        <?php endif; ?>
    </script>
</body>

</html>
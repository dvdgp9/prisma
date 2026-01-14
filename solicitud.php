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
$stmt = $db->prepare("SELECT id, name FROM apps WHERE company_id = ? AND is_active = 1 ORDER BY name");
$stmt->execute([$company['id']]);
$apps = $stmt->fetchAll();

// Get app slug from URL and find matching app
$app_slug = $_GET['app'] ?? '';
$selected_app_id = null;

if (!empty($app_slug)) {
    foreach ($apps as $app) {
        if (strtolower($app['name']) === strtolower($app_slug)) {
            $selected_app_id = $app['id'];
            break;
        }
    }
}

$success = false;
$error = '';

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sugerir Mejora - Prisma</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" href="/favicon.png?v=2">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card" style="max-width: 600px;">
            <div class="login-header">
                <div
                    style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <img src="/assets/images/logo.png" alt="Prisma" style="height: 48px; width: auto;">
                    <div class="login-logo">Prisma</div>
                </div>
                <h2 style="margin: 0.5rem 0 0 0; font-size: 1.5rem; color: var(--text-primary);">
                    Solicitar Mejora
                </h2>
                <p class="text-muted" style="margin-top: 0.5rem; font-size: 1rem;">
                    <?php echo htmlspecialchars($company['name']); ?>
                </p>
            </div>

            <?php if ($success): ?>
                <div style="text-align: center; padding: 2rem 1rem;">
                    <div
                        style="width: 4rem; height: 4rem; background: rgba(92, 184, 92, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                        <i class="iconoir-check-circle" style="font-size: 2rem; color: #5CB85C;"></i>
                    </div>

                    <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">¡Solicitud enviada correctamente!</h3>

                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; line-height: 1.6;">
                        Hemos recibido tu solicitud de mejora. Nuestro equipo la revisará y te notificaremos por correo
                        cuando:
                    </p>

                    <div
                        style="background: var(--bg-secondary); border-radius: var(--radius-md); padding: 1.25rem; text-align: left; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: start; gap: 0.75rem; margin-bottom: 1rem;">
                            <i class="iconoir-check"
                                style="color: var(--primary-color); font-size: 1.25rem; margin-top: 0.125rem;"></i>
                            <div>
                                <strong style="color: var(--text-primary); display: block; margin-bottom: 0.25rem;">Tu
                                    solicitud sea aprobada</strong>
                                <span style="color: var(--text-secondary); font-size: 0.875rem;">La añadiremos a nuestra
                                    lista de mejoras pendientes</span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: start; gap: 0.75rem; margin-bottom: 1rem;">
                            <i class="iconoir-play"
                                style="color: var(--primary-color); font-size: 1.25rem; margin-top: 0.125rem;"></i>
                            <div>
                                <strong
                                    style="color: var(--text-primary); display: block; margin-bottom: 0.25rem;">Empecemos a
                                    trabajar en ella</strong>
                                <span style="color: var(--text-secondary); font-size: 0.875rem;">Te mantendremos informado
                                    del progreso</span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: start; gap: 0.75rem;">
                            <i class="iconoir-check-circle"
                                style="color: var(--primary-color); font-size: 1.25rem; margin-top: 0.125rem;"></i>
                            <div>
                                <strong style="color: var(--text-primary); display: block; margin-bottom: 0.25rem;">La
                                    mejora esté completada</strong>
                                <span style="color: var(--text-secondary); font-size: 0.875rem;">Podrás empezar a
                                    usarla</span>
                            </div>
                        </div>
                    </div>

                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">
                        <i class="iconoir-mail" style="margin-right: 0.25rem;"></i>
                        Revisa tu correo en las próximas 24-48 horas
                    </p>

                    <a href="?empresa=<?php echo urlencode($company_slug); ?>" class="btn btn-primary"
                        style="display: inline-block; text-decoration: none;">
                        Enviar otra solicitud
                    </a>
                </div>
            <?php else: ?>
                <!-- Info box -->
                <div
                    style="background: linear-gradient(135deg, rgba(0, 201, 183, 0.05), rgba(0, 201, 183, 0.02)); border-left: 4px solid var(--primary-color); border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; gap: 0.875rem; align-items: center;">
                        <i class="iconoir-info-circle"
                            style="color: var(--primary-color); font-size: 1.25rem; flex-shrink: 0;"></i>
                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.875rem; line-height: 1.6;">
                            Cuéntanos qué mejora necesitas rellenando el formulario, lo revisaremos (máx 24-48h) y te
                            avisaremos por correo cuando la mejora sea aprobada/descartada y, si procede, cuando esté lista.
                        </p>
                    </div>
                </div>

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
                        <small style="color: var(--text-muted); font-size: 0.8125rem; display: block; margin-top: 0.25rem;">
                            Te enviaremos actualizaciones sobre tu solicitud
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="app_id">Aplicación afectada *</label>
                        <select id="app_id" name="app_id" required>
                            <option value="">Selecciona una aplicación</option>
                            <?php foreach ($apps as $app): ?>
                                <option value="<?php echo $app['id']; ?>" <?php echo $selected_app_id === $app['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($app['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Título de la mejora *</label>
                        <input type="text" id="title" name="title" required
                            placeholder="Ej: Añadir filtro por fecha en reportes">
                    </div>

                    <div class="form-group">
                        <label for="description">Descripción detallada *</label>
                        <textarea id="description" name="description" rows="6" required
                            placeholder="Describe la mejora que necesitas y por qué sería útil..."></textarea>
                        <small style="color: var(--text-muted); font-size: 0.8125rem; display: block; margin-top: 0.25rem;">
                            Cuanto más detallada, mejor podremos entender tu necesidad
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        <i class="iconoir-send"></i>
                        Enviar Solicitud
                    </button>

                    <p class="text-muted" style="text-align: center; margin-top: 1rem; font-size: 0.8125rem;">
                        <i class="iconoir-shield-check"></i>
                        Tu solicitud será revisada por un administrador antes de ser procesada
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
                    // Show success toast
                    showSuccessToast();

                    // Redirect after short delay
                    setTimeout(() => {
                        window.location.href = '?empresa=<?php echo urlencode($company_slug); ?>&success=1';
                    }, 1500);
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

        function showSuccessToast() {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                bottom: 2rem;
                right: 2rem;
                background: white;
                padding: 1.25rem 1.5rem;
                border-radius: 12px;
                box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08);
                border-left: 4px solid #5CB85C;
                display: flex;
                align-items: center;
                gap: 1rem;
                max-width: 400px;
                z-index: 9999;
                animation: slideIn 0.3s ease-out;
            `;

            toast.innerHTML = `
                <div style="width: 2.5rem; height: 2.5rem; background: rgba(92, 184, 92, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="iconoir-check-circle" style="font-size: 1.5rem; color: #5CB85C;"></i>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 0.9375rem; color: #2c3e50; margin-bottom: 0.25rem;">
                        ¡Genial! Ya la tenemos
                    </div>
                    <div style="font-size: 0.8125rem; color: #64748b; line-height: 1.4;">
                        Revisaremos tu solicitud y te avisaremos pronto por email
                    </div>
                </div>
            `;

            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from {
                        transform: translateX(120%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(toast);
        }

        <?php if (isset($_GET['success'])): ?>
            window.history.replaceState({}, '', '?empresa=<?php echo urlencode($company_slug); ?>');
        <?php endif; ?>
    </script>
</body>

</html>
```
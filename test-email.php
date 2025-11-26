<?php
/**
 * Email Test Page - TEMPORAL
 * Delete this file when email is working
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

// Only admins can access
if (!has_role('admin')) {
    die('Unauthorized');
}

$sent = false;
$error = '';
$user = get_logged_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = $_POST['test_email'] ?? '';

    if (!empty($test_email)) {
        try {
            require_once __DIR__ . '/includes/email.php';

            $content = "
                <p style='font-size: 16px; margin: 0 0 20px 0;'>Hola,</p>
                
                <p style='margin: 0 0 20px 0;'>Este es un email de prueba del sistema Prisma.</p>
                
                <div style='background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 20px; margin: 25px 0; border-radius: 4px;'>
                    <p style='margin: 0; color: #0369a1; font-weight: 600;'>✅ Sistema SMTP funcionando correctamente</p>
                </div>
                
                <p style='margin: 0 0 20px 0;'>Si recibes este email, significa que la configuración SMTP está correcta.</p>
                
                <p style='margin: 0; color: #64748b;'>Saludos,<br><strong>El equipo de Prisma</strong></p>
            ";

            $footer = "Fecha de envío: " . date('d/m/Y H:i:s');

            $result = sendCompanyEmail(
                $user['company_id'],
                $test_email,
                "🧪 Test Email - Prisma",
                getEmailTemplate('Email de Prueba', $content, $footer),
                'Destinatario Test'
            );

            if ($result) {
                $sent = true;
            } else {
                $error = 'Error al enviar. Revisa los logs del servidor.';
            }

        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Por favor, introduce un email';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email - Prisma</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card" style="max-width: 500px;">
            <div class="login-header">
                <div class="login-logo">Prisma</div>
                <h2 style="margin: 0.5rem 0 0 0; font-size: 1.25rem; color: var(--text-primary);">
                    🧪 Test de Email SMTP
                </h2>
                <p class="text-muted" style="margin-top: 0.5rem; font-size: 0.875rem;">
                    Prueba el envío de correos
                </p>
            </div>

            <?php if ($sent): ?>
                <div
                    style="padding: 1.5rem; background: rgba(92, 184, 92, 0.1); border-left: 4px solid #5CB85C; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <i class="iconoir-check" style="font-size: 1.5rem; color: #5CB85C;"></i>
                        <div>
                            <strong style="color: #5CB85C;">¡Email enviado!</strong>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: var(--text-secondary);">
                                Revisa el correo: <?php echo htmlspecialchars($_POST['test_email']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="test_email">Email de prueba</label>
                    <input type="email" id="test_email" name="test_email" required placeholder="tu@email.com"
                        value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>">
                    <small style="color: var(--text-muted); font-size: 0.8125rem; display: block; margin-top: 0.25rem;">
                        Se enviará un email de prueba a esta dirección
                    </small>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    <i class="iconoir-send"></i>
                    Enviar Email de Prueba
                </button>
            </form>

            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                <a href="/index.php"
                    style="color: var(--text-secondary); text-decoration: none; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="iconoir-arrow-left"></i>
                    Volver al Dashboard
                </a>
            </div>

            <div
                style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md);">
                <p style="margin: 0; font-size: 0.8125rem; color: var(--text-secondary);">
                    <strong>⚠️ Archivo temporal</strong><br>
                    Elimina <code>test-email.php</code> cuando confirmes que funciona
                </p>
            </div>
        </div>
    </div>
</body>

</html>
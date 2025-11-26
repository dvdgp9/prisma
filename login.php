<?php
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (login($username, $password, $remember)) {
        header('Location: /index.php');
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Prisma</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" href="/favicon.png?v=2">
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div
                    style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <img src="/assets/images/logo.png" alt="Prisma" style="height: 48px; width: auto;">
                    <div class="login-logo">Prisma</div>
                </div>
                <p class="text-muted">Dashboard centralizado de desarrollo</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="/login.php">
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" required autofocus
                        placeholder="Introduce tu usuario">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: var(--spacing-sm);">
                    <input type="checkbox" id="remember" name="remember" style="width: auto; margin: 0;">
                    <label for="remember" style="margin: 0; font-weight: 500; cursor: pointer;">
                        Recordarme durante 60 días
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
</body>

</html>
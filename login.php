<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prisma - Login</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">Prisma</div>
                <p class="text-muted">Dashboard centralizado de desarrollo</p>
            </div>

            <?php
            require_once __DIR__ . '/includes/auth.php';

            $error = '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';

                if (login($username, $password)) {
                    header('Location: /index.php');
                    exit;
                } else {
                    $error = 'Usuario o contraseña incorrectos.';
                }
            }

            // Redirect if already logged in
            if (is_logged_in()) {
                header('Location: /index.php');
                exit;
            }
            ?>

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
                    <input type="password" id="password" name="password" required placeholder="Introduce tu contraseña">
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
</body>

</html>
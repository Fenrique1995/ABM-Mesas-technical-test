<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    header('Location: /mesas/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $error = 'Completa todos los campos.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, nombre, email, password_hash FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['usuario_id']     = $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre'];
            $_SESSION['usuario_email']  = $user['email'];
            header('Location: /mesas/index.php');
            exit;
        }
        $error = 'Email o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="/css/estilo.css">
</head>
<body>
    <div class="container form-container">
        <h1>Iniciar Sesión</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>
        <p class="text-center">¿No tenés cuenta? <a href="registro.php">Registrate</a></p>
    </div>
</body>
</html>

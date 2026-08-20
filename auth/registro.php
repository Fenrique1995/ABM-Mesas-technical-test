<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    header('Location: /mesas/index.php');
    exit;
}

$error  = '';
$nombre = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password2'] ?? '';

    if ($nombre === '' || $email === '' || $pass === '' || $pass2 === '') {
        $error = 'Completa todos los campos.';
    } elseif ($pass !== $pass2) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($pass) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        $db = getDB();

        $check = $db->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            $error = 'Ese email ya está registrado.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO usuarios (nombre, email, password_hash) VALUES (:nombre, :email, :hash)');
            $stmt->execute([':nombre' => $nombre, ':email' => $email, ':hash' => $hash]);

            $_SESSION['usuario_id']     = $db->lastInsertId();
            $_SESSION['usuario_nombre'] = $nombre;
            $_SESSION['usuario_email']  = $email;
            header('Location: /mesas/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro</title>
    <link rel="stylesheet" href="/css/estilo.css">
</head>
<body>
    <div class="container form-container">
        <h1>Registro</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" required value="<?= htmlspecialchars($nombre) ?>">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required minlength="6">
            <label for="password2">Repetir contraseña</label>
            <input type="password" id="password2" name="password2" required>
            <button type="submit" class="btn btn-primary">Registrarse</button>
        </form>
        <p class="text-center">¿Ya tenés cuenta? <a href="login.php">Iniciá sesión</a></p>
    </div>
</body>
</html>

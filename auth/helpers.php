<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_path', '/tmp');
    session_start();
}

function requireLogin(): void {
    if (empty($_SESSION['usuario_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

function isLoggedIn(): bool {
    return !empty($_SESSION['usuario_id']);
}

function currentUser(): ?array {
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }
    return [
        'id'     => $_SESSION['usuario_id'],
        'nombre' => $_SESSION['usuario_nombre'] ?? '',
        'email'  => $_SESSION['usuario_email'] ?? '',
    ];
}

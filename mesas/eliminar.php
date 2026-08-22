<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $db = getDB();

    $stmt = $db->prepare('SELECT COUNT(*) FROM reserva_mesas WHERE mesa_id = :id');
    $stmt->execute([':id' => $id]);

    if ((int)$stmt->fetchColumn() > 0) {
        header('Location: index.php?error=' . urlencode('No se puede eliminar la mesa porque tiene reservas asociadas.'));
        exit;
    }

    $stmt = $db->prepare('DELETE FROM mesas WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

header('Location: index.php');
exit;

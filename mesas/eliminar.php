<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $db    = getDB();
    $hoy   = date('Y-m-d');
    $ahora = date('H:i:s');

    // Solo bloquear si la mesa tiene reservas activas que aún no finalizaron
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM reserva_mesas rm
        JOIN reservas r ON r.id = rm.reserva_id
        WHERE rm.mesa_id = :id
          AND r.estado = 'activa'
          AND (r.fecha > :hoy OR (r.fecha = :hoy2 AND r.hora_fin > :ahora))
    ");
    $stmt->execute([
        ':id'    => $id,
        ':hoy'   => $hoy,
        ':hoy2'  => $hoy,
        ':ahora' => $ahora,
    ]);

    if ((int)$stmt->fetchColumn() > 0) {
        header('Location: index.php?error=' . urlencode('No se puede eliminar la mesa porque tiene reservas activas pendientes.'));
        exit;
    }

    // Las reservas pasadas/canceladas no impiden el borrado (reserva_mesas se limpia por CASCADE)
    $stmt = $db->prepare('DELETE FROM mesas WHERE id = :id');
    $stmt->execute([':id' => $id]);

    header('Location: index.php?exito=' . urlencode('Mesa eliminada correctamente.'));
    exit;
}

header('Location: index.php');
exit;

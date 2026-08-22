<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

$db            = getDB();
$id            = (int)($_GET['id'] ?? 0);
$fechaRedirect = $_GET['fecha'] ?? date('Y-m-d');

if ($id <= 0) {
    header('Location: /listados/por_fecha.php?fecha=' . urlencode($fechaRedirect));
    exit;
}

$stmt = $db->prepare('SELECT id, fecha, hora_inicio, estado FROM reservas WHERE id = :id');
$stmt->execute([':id' => $id]);
$reserva = $stmt->fetch();

if (!$reserva || $reserva['estado'] !== 'activa') {
    header('Location: /listados/por_fecha.php?fecha=' . urlencode($fechaRedirect) . '&error=' . urlencode("La reserva #$id no existe o ya fue cancelada."));
    exit;
}

// Solo se puede cancelar hasta 15 minutos antes del inicio
$inicioReserva = strtotime($reserva['fecha'] . ' ' . $reserva['hora_inicio']);
if ($inicioReserva === false || $inicioReserva - time() < 900) {
    header('Location: /listados/por_fecha.php?fecha=' . urlencode($fechaRedirect) . '&error=' . urlencode("Solo se puede cancelar la reserva #$id hasta 15 minutos antes de su horario de inicio."));
    exit;
}

$stmt = $db->prepare("UPDATE reservas SET estado = 'cancelada', cancelada_en = NOW() WHERE id = :id AND estado = 'activa'");
$stmt->execute([':id' => $id]);

// Limpiar cache de disponibilidad
foreach ($_SESSION as $key => $val) {
    if (strpos($key, 'dispo_') === 0) {
        unset($_SESSION[$key]);
    }
}

header('Location: /listados/por_fecha.php?fecha=' . urlencode($fechaRedirect) . '&exito=' . urlencode("Reserva #$id cancelada correctamente."));
exit;

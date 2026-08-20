<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';
if (empty($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'No autenticado']));
}

header('Content-Type: application/json');

$fecha     = $_GET['fecha'] ?? '';
$hora      = $_GET['hora'] ?? '';
$ubicacion = $_GET['ubicacion'] ?? '';

if ($fecha === '' || $hora === '') {
    exit(json_encode(['error' => 'Faltan parámetros']));
}

$db = getDB();

// Cache 
$cacheKey = 'dispo_' . $fecha . '_' . $hora;
if (!isset($_SESSION[$cacheKey])) {
    $horaFin = date('H:i:s', strtotime($hora) + 7200);

    $stmt = $db->prepare('
        SELECT m.ubicacion, COUNT(m.id) as total
        FROM mesas m
        WHERE m.id NOT IN (
            SELECT rm.mesa_id
            FROM reserva_mesas rm
            JOIN reservas r ON r.id = rm.reserva_id
            WHERE r.fecha = :fecha
              AND r.hora_inicio < :hora_fin
              AND r.hora_fin > :hora
        )
        GROUP BY m.ubicacion
        ORDER BY m.ubicacion
    ');
    $stmt->execute([':fecha' => $fecha, ':hora_fin' => $horaFin, ':hora' => $hora]);
    $libres = $stmt->fetchAll();

    $_SESSION[$cacheKey] = $libres;
}

$libres = $_SESSION[$cacheKey];

if ($ubicacion !== '') {
    $horaFin = date('H:i:s', strtotime($hora) + 7200);

    $stmt = $db->prepare('
        SELECT m.id, m.numero, m.capacidad, m.seccion, m.ubicacion
        FROM mesas m
        WHERE m.ubicacion = :ubicacion
          AND m.id NOT IN (
              SELECT rm.mesa_id
              FROM reserva_mesas rm
              JOIN reservas r ON r.id = rm.reserva_id
              WHERE r.fecha = :fecha
                AND r.hora_inicio < :hora_fin
                AND r.hora_fin > :hora
          )
        ORDER BY m.numero
    ');
    $stmt->execute([':ubicacion' => $ubicacion, ':fecha' => $fecha, ':hora' => $horaFin, ':hora' => $hora]);
    $mesas = $stmt->fetchAll();

    exit(json_encode(['mesas' => $mesas]));
}

$ubicaciones = [];
foreach ($libres as $row) {
    $ubicaciones[] = [
        'ubicacion'   => $row['ubicacion'],
        'disponibles' => (int)$row['total'],
    ];
}

echo json_encode(['ubicaciones' => $ubicaciones]);

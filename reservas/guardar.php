<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

$db = getDB();

$fecha     = $_POST['fecha'] ?? '';
$hora      = $_POST['hora'] ?? '';
$ubicacion = $_POST['ubicacion'] ?? '';
$cantidad  = (int)($_POST['cantidad'] ?? 0);
$mesasRaw  = $_POST['mesas_seleccionadas'] ?? '';

// Normalizar hora: si el formato no es HH:MM válido se trata como vacía
if ($hora !== '' && !preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $hora)) {
    $hora = '';
}

$errors = [];

if ($fecha === '') $errors[] = 'La fecha es obligatoria.';
if ($hora === '') $errors[] = 'La hora es obligatoria.';
if (!in_array($ubicacion, ['A', 'B', 'C', 'D'])) $errors[] = 'Ubicación inválida.';
if ($cantidad < 1) $errors[] = 'Cantidad de personas inválida.';

$mesasIds = array_filter(array_map('intval', explode(',', $mesasRaw)));
if (count($mesasIds) === 0) $errors[] = 'Seleccioná al menos una mesa.';
if (count($mesasIds) > 3) $errors[] = 'Máximo 3 mesas por reserva.';

// Validar horarios por día
$diaSemana = date('w', strtotime($fecha));
$horaNum   = (int)date('H', strtotime($hora));

switch ($diaSemana) {
    case 0: // Domingo
        if ($horaNum < 12 || $horaNum >= 16) $errors[] = 'Domingo: solo 12:00 a 16:00.';
        break;
    case 6: // Sábado
        if ($horaNum >= 2 && $horaNum < 22) $errors[] = 'Sábado: solo 22:00 a 02:00.';
        break;
    default: // L-V
        if ($horaNum < 10 || $horaNum >= 24) $errors[] = 'L-V: solo 10:00 a 24:00.';
        break;
}

// Validar que la reserva termine dentro del horario de cierre
$minutos = ((int)date('G', strtotime($hora))) * 60 + (int)date('i', strtotime($hora));
$cierres = [0 => 16 * 60, 6 => (2 + 24) * 60];
$cierre  = $cierres[$diaSemana] ?? 24 * 60;
if ($diaSemana === 6 && $horaNum < 6) {
    $minutos += 24 * 60;
}
if ($minutos + 120 > $cierre) {
    $errors[] = 'La reserva debe terminar antes del horario de cierre.';
}

// Validar que sea al menos 15 min antes
$horaReserva = strtotime($fecha . ' ' . $hora);
$ahora       = time();
if ($horaReserva < $ahora) {
    $errors[] = 'La hora indicada ya pasó. Elegí un horario futuro.';
} elseif ($horaReserva - $ahora < 900) {
    $errors[] = 'Debes reservar con al menos 15 minutos de anticipación.';
}

// Validar que las mesas existan y sean de la misma ubicación
if (empty($errors) && count($mesasIds) > 0) {
    $placeholders = implode(',', array_fill(0, count($mesasIds), '?'));
    $stmt = $db->prepare("SELECT id, ubicacion, numero, capacidad FROM mesas WHERE id IN ($placeholders)");
    $stmt->execute($mesasIds);
    $mesas = $stmt->fetchAll();

    if (count($mesas) !== count($mesasIds)) {
        $errors[] = 'Una o más mesas no existen.';
    } else {
        $ubicacionesMesas = array_unique(array_column($mesas, 'ubicacion'));
        if (count($ubicacionesMesas) > 1) {
            $errors[] = 'Todas las mesas deben ser de la misma ubicación.';
        }
        if ($ubicacionesMesas[0] !== $ubicacion) {
            $errors[] = 'Las mesas seleccionadas no pertenecen a la ubicación indicada.';
        }
        $capTotal = array_sum(array_column($mesas, 'capacidad'));
        if ($cantidad > $capTotal) {
            $errors[] = "Las mesas seleccionadas solo tienen capacidad para $capTotal personas.";
        }

        $cantidadMesas   = count($mesas);
        $mesasReservadas = '';
        foreach ($mesas as $m) {
            $mesasReservadas .= ($mesasReservadas === '' ? '' : ', ') . $m['numero'];
        }
    }
}

// Verificar disponibilidad
if (empty($errors)) {
    $horaFin = calcularHoraFin($hora);
    $placeholders = implode(',', array_fill(0, count($mesasIds), '?'));
    $stmt = $db->prepare("
        SELECT rm.mesa_id
        FROM reserva_mesas rm
        JOIN reservas r ON r.id = rm.reserva_id
        WHERE rm.mesa_id IN ($placeholders)
          AND r.estado = 'activa'
          AND r.fecha = ?
          AND r.hora_inicio < ?
          AND r.hora_fin > ?
    ");
    $params = array_merge($mesasIds, [$fecha, $horaFin, $hora]);
    $stmt->execute($params);
    $ocupadas = $stmt->fetchAll();

    if (count($ocupadas) > 0) {
        $errors[] = 'Una o más mesas ya están reservadas para ese horario.';
    }
}

if (!empty($errors)) {
    header('Location: nueva.php?error=' . urlencode(implode(' ', $errors)));
    exit;
}

// Crear reserva
$horaFin = calcularHoraFin($hora);

$db->beginTransaction();
try {
    $stmt = $db->prepare('INSERT INTO reservas (usuario_id, fecha, hora_inicio, hora_fin, cantidad_personas, cantidad_mesas, mesas_reservadas, ubicacion) VALUES (:uid, :fecha, :ini, :fin, :cant, :cmesas, :mesas, :ubi)');
    $stmt->execute([
        ':uid'    => $_SESSION['usuario_id'],
        ':fecha'  => $fecha,
        ':ini'    => $hora,
        ':fin'    => $horaFin,
        ':cant'   => $cantidad,
        ':cmesas' => $cantidadMesas,
        ':mesas'  => $mesasReservadas,
        ':ubi'    => $ubicacion,
    ]);
    $reservaId = $db->lastInsertId();

    $stmtMesa = $db->prepare('INSERT INTO reserva_mesas (reserva_id, mesa_id) VALUES (:rid, :mid)');
    foreach ($mesasIds as $mid) {
        $stmtMesa->execute([':rid' => $reservaId, ':mid' => $mid]);
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    header('Location: nueva.php?error=' . urlencode('Error al crear la reserva.'));
    exit;
}

// Limpiar cache
foreach ($_SESSION as $key => $val) {
    if (strpos($key, 'dispo_') === 0) {
        unset($_SESSION[$key]);
    }
}

header('Location: nueva.php?exito=' . urlencode("Reserva #$reservaId creada correctamente."));
exit;

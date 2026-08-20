<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

$db    = getDB();
$hoy   = date('Y-m-d');
$ahora = date('H:i:s');

$mesas = $db->query('SELECT * FROM mesas ORDER BY ubicacion, numero')->fetchAll();

$reservasActivas = [];
if (!empty($mesas)) {
    $mesasIds = array_column($mesas, 'id');
    $placeholders = implode(',', array_fill(0, count($mesasIds), '?'));
    $stmt = $db->prepare("
        SELECT rm.mesa_id, r.id AS reserva_id, r.fecha, r.hora_inicio, r.hora_fin,
               r.cantidad_personas, u.nombre AS cliente
        FROM reserva_mesas rm
        JOIN reservas r ON r.id = rm.reserva_id
        JOIN usuarios u ON u.id = r.usuario_id
        WHERE rm.mesa_id IN ($placeholders)
          AND (
              (r.fecha = ? AND r.hora_inicio <= ? AND r.hora_fin > ?)
              OR (r.fecha = ? AND r.hora_inicio > ?)
          )
        ORDER BY r.fecha, r.hora_inicio
        LIMIT 50
    ");
    $params = array_merge($mesasIds, [$hoy, $ahora, $ahora, $hoy, $ahora]);
    $stmt->execute($params);
    $all = $stmt->fetchAll();

    foreach ($all as $row) {
        $mid = $row['mesa_id'];
        if (!isset($reservasActivas[$mid])) {
            $reservasActivas[$mid] = [];
        }
        $reservasActivas[$mid][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesas</title>
    <link rel="stylesheet" href="/css/estilo.css">
</head>
<body>
    <div class="container">
        <nav class="nav">
            <span class="nav-brand">Resto</span>
            <div class="nav-links">
                <a href="/mesas/index.php">Mesas</a>
                <a href="/reservas/nueva.php">Nueva Reserva</a>
                <a href="/listados/por_fecha.php">Listados</a>
                <span><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span>
                <a href="/auth/logout.php">Salir</a>
            </div>
        </nav>

        <div class="header">
            <h1>Mesas</h1>
            <a href="form.php" class="btn btn-primary">+ Nueva Mesa</a>
        </div>

        <?php if (empty($mesas)): ?>
            <p>No hay mesas cargadas.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Ubicación</th>
                        <th>Número</th>
                        <th>Capacidad</th>
                        <th>Sección</th>
                        <th>Estado</th>
                        <th>Reserva / Horario</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mesas as $m): ?>
                        <?php
                        $mid      = $m['id'];
                        $reservas = $reservasActivas[$mid] ?? [];
                        $ocupada  = false;
                        $proxima  = null;

                        foreach ($reservas as $r) {
                            if ($r['fecha'] === $hoy && $r['hora_inicio'] <= $ahora && $r['hora_fin'] > $ahora) {
                                $ocupada = true;
                                $proxima = $r;
                                break;
                            }
                            if ($proxima === null) {
                                $proxima = $r;
                            }
                        }

                        if ($ocupada) {
                            $estadoClass = 'estado-ocupada';
                            $estadoTexto = 'Ocupada';
                        } elseif ($proxima) {
                            $estadoClass = 'estado-reservada';
                            $estadoTexto = 'Reservada';
                        } else {
                            $estadoClass = 'estado-libre';
                            $estadoTexto = 'Libre';
                        }
                        ?>
                        <tr class="<?= $estadoClass ?>">
                            <td><?= htmlspecialchars($m['ubicacion']) ?></td>
                            <td><?= (int)$m['numero'] ?></td>
                            <td><?= (int)$m['capacidad'] ?></td>
                            <td><?= htmlspecialchars($m['seccion']) ?></td>
                            <td><span class="badge <?= $estadoClass ?>"><?= $estadoTexto ?></span></td>
                            <td>
                                <?php if ($ocupada && $proxima): ?>
                                    <strong><?= htmlspecialchars($proxima['cliente']) ?></strong><br>
                                    <?= $proxima['fecha'] ?> <?= substr($proxima['hora_inicio'], 0, 5) ?> - <?= substr($proxima['hora_fin'], 0, 5) ?>
                                    <br><small><?= $proxima['cantidad_personas'] ?> personas</small>
                                <?php elseif ($proxima): ?>
                                    <strong><?= htmlspecialchars($proxima['cliente']) ?></strong><br>
                                    <?= $proxima['fecha'] ?> <?= substr($proxima['hora_inicio'], 0, 5) ?> - <?= substr($proxima['hora_fin'], 0, 5) ?>
                                    <br><small><?= $proxima['cantidad_personas'] ?> personas</small>
                                <?php else: ?>
                                    <span class="text-muted">Sin reservas</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="form.php?id=<?= $m['id'] ?>" class="btn btn-small">Editar</a>
                                <a href="eliminar.php?id=<?= $m['id'] ?>" class="btn btn-small btn-danger" onclick="return confirm('¿Eliminar esta mesa?')">Borrar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

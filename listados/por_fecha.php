<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

$db   = getDB();
$fecha = $_GET['fecha'] ?? date('Y-m-d');

$stmt = $db->prepare('
    SELECT
        r.id AS reserva_id,
        r.fecha,
        r.hora_inicio,
        r.hora_fin,
        r.cantidad_personas,
        r.ubicacion,
        u.nombre AS cliente,
        m.numero AS mesa_numero,
        m.capacidad AS mesa_capacidad,
        m.seccion AS mesa_seccion
    FROM reservas r
    INNER JOIN reserva_mesas rm ON rm.reserva_id = r.id
    INNER JOIN mesas m ON m.id = rm.mesa_id
    INNER JOIN usuarios u ON u.id = r.usuario_id
    WHERE r.fecha = :fecha
    ORDER BY r.ubicacion, m.seccion, r.hora_inicio, m.numero
');
$stmt->execute([':fecha' => $fecha]);
$reservas = $stmt->fetchAll();

// Agrupar por ubicación -> sección -> reserva
$agrupado = [];
foreach ($reservas as $row) {
    $ubi    = $row['ubicacion'];
    $seccion = $row['mesa_seccion'];
    $rid    = $row['reserva_id'];

    $agrupado[$ubi][$seccion][$rid] = [
        'hora_inicio' => $row['hora_inicio'],
        'hora_fin'    => $row['hora_fin'],
        'cantidad'    => $row['cantidad_personas'],
        'cliente'     => $row['cliente'],
        'mesas'       => [],
    ];
    $agrupado[$ubi][$seccion][$rid]['mesas'][] = [
        'numero'     => $row['mesa_numero'],
        'capacidad'  => $row['mesa_capacidad'],
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservas por Fecha</title>
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
            <h1>Reservas del <?= date('d/m/Y', strtotime($fecha)) ?></h1>
            <form method="GET" class="form-inline">
                <input type="date" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </form>
        </div>

        <?php if (empty($agrupado)): ?>
            <p>No hay reservas para esta fecha.</p>
        <?php else: ?>
            <?php foreach ($agrupado as $ubi => $secciones): ?>
                <div class="ubicacion-block">
                    <h2>Ubicación <?= $ubi ?></h2>
                    <?php foreach ($secciones as $seccion => $reservasGrupo): ?>
                        <h3>Sección: <?= htmlspecialchars($seccion) ?></h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Reserva</th>
                                    <th>Cliente</th>
                                    <th>Horario</th>
                                    <th>Personas</th>
                                    <th>Mesas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservasGrupo as $rid => $r): ?>
                                    <tr>
                                        <td>#<?= $rid ?></td>
                                        <td><?= htmlspecialchars($r['cliente']) ?></td>
                                        <td><?= substr($r['hora_inicio'], 0, 5) ?> - <?= substr($r['hora_fin'], 0, 5) ?></td>
                                        <td><?= $r['cantidad'] ?></td>
                                        <td>
                                            <?php
                                            $mesasStr = array_map(function($m) {
                                                return '#' . $m['numero'] . ' (' . $m['capacidad'] . 'p)';
                                            }, $r['mesas']);
                                            echo htmlspecialchars(implode(', ', $mesasStr));
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>

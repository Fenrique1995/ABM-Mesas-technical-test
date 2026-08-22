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
        r.estado,
        u.nombre AS cliente,
        m.numero AS mesa_numero,
        m.capacidad AS mesa_capacidad
    FROM reservas r
    INNER JOIN reserva_mesas rm ON rm.reserva_id = r.id
    INNER JOIN mesas m ON m.id = rm.mesa_id
    INNER JOIN usuarios u ON u.id = r.usuario_id
    WHERE r.fecha = :fecha
    ORDER BY r.ubicacion, r.hora_inicio, m.numero
');
$stmt->execute([':fecha' => $fecha]);
$reservas = $stmt->fetchAll();

// Contador de canceladas (ids únicos: una reserva puede tener varias mesas/filas)
$canceladasCount = count(array_unique(array_column(
    array_filter($reservas, function ($r) {
        return $r['estado'] === 'cancelada';
    }),
    'reserva_id'
)));

$error = $_GET['error'] ?? '';
$exito = $_GET['exito'] ?? '';

// Agrupar por ubicación -> reserva
$agrupado = [];
foreach ($reservas as $row) {
    $ubi    = $row['ubicacion'];
    $rid    = $row['reserva_id'];

    if (!isset($agrupado[$ubi][$rid])) {
        $agrupado[$ubi][$rid] = [
            'hora_inicio' => $row['hora_inicio'],
            'hora_fin'    => $row['hora_fin'],
            'cantidad'    => $row['cantidad_personas'],
            'cliente'     => $row['cliente'],
            'estado'      => $row['estado'],
            'mesas'       => [],
        ];
    }
    $agrupado[$ubi][$rid]['mesas'][] = [
        'numero'     => $row['mesa_numero']
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservas por Fecha</title>
    <link rel="stylesheet" href="/css/estilo.css?v=6">
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

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
        <?php endif; ?>

        <?php if ($canceladasCount > 0): ?>
            <p class="text-muted">
                <?= $canceladasCount ?> reserva<?= $canceladasCount === 1 ? '' : 's' ?>
                cancelada<?= $canceladasCount === 1 ? '' : 's' ?> en esta fecha.
            </p>
        <?php endif; ?>

        <?php if (empty($agrupado)): ?>
            <p>No hay reservas para esta fecha.</p>
        <?php else: ?>
            <?php foreach ($agrupado as $ubi => $reservasUbicacion): ?>
                <div class="ubicacion-block">
                    <h2>Ubicación <?= $ubi ?></h2>
                    <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Reserva</th>
                                <th>Cliente</th>
                                <th>Horario</th>
                                <th>Personas</th>
                                <th>Mesas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservasUbicacion as $rid => $r): ?>
                                <tr<?= $r['estado'] === 'cancelada' ? ' class="reserva-cancelada"' : '' ?>>
                                    <td data-label="Reserva">#<?= $rid ?></td>
                                    <td data-label="Cliente"><?= htmlspecialchars($r['cliente']) ?></td>
                                    <td data-label="Horario"><?= substr($r['hora_inicio'], 0, 5) ?> - <?= formatearHora($r['hora_fin']) ?></td>
                                    <td data-label="Personas"><?= $r['cantidad'] ?></td>
                                    <td data-label="Mesas">
                                        <?php
                                        $mesasStr = array_map(function($m) {
                                            return '#' . $m['numero'];
                                        }, $r['mesas']);
                                        echo htmlspecialchars(implode(', ', $mesasStr));
                                        ?>
                                    </td>
                                    <td class="actions">
                                        <?php if ($r['estado'] === 'cancelada'): ?>
                                            <span class="text-muted">Cancelada</span>
                                        <?php elseif (strtotime($fecha . ' ' . $r['hora_inicio']) - time() >= 900): ?>
                                            <a href="/reservas/cancelar.php?id=<?= $rid ?>&fecha=<?= htmlspecialchars($fecha) ?>"
                                               class="btn btn-danger btn-small"
                                               onclick="return confirm('¿Cancelar la reserva #<?= $rid ?> de <?= htmlspecialchars($r['cliente']) ?>?')">Cancelar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>

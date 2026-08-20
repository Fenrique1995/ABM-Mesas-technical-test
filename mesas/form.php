<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

$db    = getDB();
$mesa  = null;
$title = 'Nueva Mesa';
$id    = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt  = $db->prepare('SELECT * FROM mesas WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $mesa  = $stmt->fetch();
    $title = 'Editar Mesa';
    if (!$mesa) {
        header('Location: index.php');
        exit;
    }
}

$error   = '';
$datos   = $mesa ?? ['ubicacion' => 'A', 'numero' => 1, 'capacidad' => 4, 'seccion' => 'General'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos['ubicacion'] = strtoupper(trim($_POST['ubicacion'] ?? 'A'));
    $datos['numero']    = (int)($_POST['numero'] ?? 1);
    $datos['capacidad'] = (int)($_POST['capacidad'] ?? 4);
    $datos['seccion']   = trim($_POST['seccion'] ?? 'General');

    if (!in_array($datos['ubicacion'], ['A', 'B', 'C', 'D'])) {
        $error = 'Ubicación inválida.';
    } elseif ($datos['numero'] < 1) {
        $error = 'Número de mesa inválido.';
    } elseif ($datos['capacidad'] < 1) {
        $error = 'Capacidad inválida.';
    } else {
        try {
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE mesas SET ubicacion = :u, numero = :n, capacidad = :c, seccion = :s WHERE id = :id');
                $stmt->execute([':u' => $datos['ubicacion'], ':n' => $datos['numero'], ':c' => $datos['capacidad'], ':s' => $datos['seccion'], ':id' => $id]);
            } else {
                $stmt = $db->prepare('INSERT INTO mesas (ubicacion, numero, capacidad, seccion) VALUES (:u, :n, :c, :s)');
                $stmt->execute([':u' => $datos['ubicacion'], ':n' => $datos['numero'], ':c' => $datos['capacidad'], ':s' => $datos['seccion']]);
            }
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Ya existe una mesa con esa ubicación y número.';
            } else {
                $error = 'Error al guardar la mesa: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="/css/estilo.css">
</head>
<body>
    <div class="container form-container">
        <h1><?= $title ?></h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="ubicacion">Ubicación</label>
            <select id="ubicacion" name="ubicacion" required>
                <?php foreach (['A', 'B', 'C', 'D'] as $u): ?>
                    <option value="<?= $u ?>" <?= $datos['ubicacion'] === $u ? 'selected' : '' ?>><?= $u ?></option>
                <?php endforeach; ?>
            </select>
            <label for="numero">Número de Mesa</label>
            <input type="number" id="numero" name="numero" min="1" required value="<?= (int)$datos['numero'] ?>">
            <label for="capacidad">Capacidad</label>
            <input type="number" id="capacidad" name="capacidad" min="1" required value="<?= (int)$datos['capacidad'] ?>">
            <label for="seccion">Sección</label>
            <select id="seccion" name="seccion" required>
                <?php foreach (['Patio', 'Interior', 'Terraza', 'VIP'] as $s): ?>
                    <option value="<?= $s ?>" <?= $datos['seccion'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="index.php" class="btn">Cancelar</a>
        </form>
    </div>
</body>
</html>

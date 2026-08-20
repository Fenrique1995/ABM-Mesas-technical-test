<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $db   = getDB();
    $stmt = $db->prepare('DELETE FROM mesas WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

header('Location: index.php');
exit;

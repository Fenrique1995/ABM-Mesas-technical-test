<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

define('DB_HOST', 'localhost');
define('DB_NAME', 'abm_mesas');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function calcularHoraFin(string $hora, int $duracionSegundos = 7200): string {
    $mins = ((int)substr($hora, 0, 2)) * 60 + (int)substr($hora, 3, 2) + intdiv($duracionSegundos, 60);
    return sprintf('%02d:%02d:00', intdiv($mins, 60), $mins % 60);
}

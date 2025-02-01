<?php
// Datos de conexión a la base de datos
$host = 'localhost'; // o el host de tu servidor de base de datos
$db   = 'atlant24_inventario';
$user = 'atlant24_inventario';
$pass = 'Irios.,._1A';
$charset = 'utf8mb4';

// Configuración del Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Muestra el error si la conexión falla
    throw new PDOException($e->getMessage(), (int)$e->getCode());
}
?>

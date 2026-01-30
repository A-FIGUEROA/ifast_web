<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/auth.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();

echo "<h1>Estructura de la tabla usuarios</h1>";

// Mostrar estructura de la tabla
$stmt = $conn->query("DESCRIBE usuarios");
$columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Columnas:</h2>";
echo "<pre>";
print_r($columnas);
echo "</pre>";

// Mostrar todos los usuarios
echo "<h2>Usuarios en la tabla:</h2>";
$stmt = $conn->query("SELECT * FROM usuarios LIMIT 5");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($usuarios);
echo "</pre>";
?>

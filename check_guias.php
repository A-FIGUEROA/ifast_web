<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/auth.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();

echo "<h1>Estructura de la tabla guias_masivas</h1>";

// Mostrar estructura de la tabla
$stmt = $conn->query("DESCRIBE guias_masivas");
$columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Columnas:</h2>";
echo "<pre>";
print_r($columnas);
echo "</pre>";

// Mostrar algunas guías
echo "<h2>Primeras 5 guías:</h2>";
$stmt = $conn->query("SELECT * FROM guias_masivas LIMIT 5");
$guias = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($guias);
echo "</pre>";
?>

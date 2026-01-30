<?php
// Test para ver qué datos devuelven las funciones
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();

echo "<h1>Test de Datos del Dashboard</h1>";

echo "<h2>Embarques por Usuario:</h2>";
$embarques_por_usuario = obtenerEstadisticasEmbarquesPorUsuario($conn);
echo "<pre>";
print_r($embarques_por_usuario);
echo "</pre>";

echo "<h2>Facturación por Usuario:</h2>";
$facturacion_por_usuario = obtenerEstadisticasFacturacionPorUsuario($conn);
echo "<pre>";
print_r($facturacion_por_usuario);
echo "</pre>";

echo "<h2>JSON Embarques:</h2>";
echo "<pre>";
echo json_encode($embarques_por_usuario, JSON_PRETTY_PRINT);
echo "</pre>";

echo "<h2>JSON Facturación:</h2>";
echo "<pre>";
echo json_encode($facturacion_por_usuario, JSON_PRETTY_PRINT);
echo "</pre>";

// Verificar usuarios activos
echo "<h2>Usuarios Activos:</h2>";
$stmt = $conn->query("SELECT id, nombre, apellido, tipo, activo FROM usuarios");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($usuarios);
echo "</pre>";

// Verificar guías con creado_por
echo "<h2>Guías con creado_por:</h2>";
$stmt = $conn->query("SELECT id, nro_guia, creado_por, creado_en FROM guias_masivas ORDER BY creado_en DESC LIMIT 10");
$guias = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($guias);
echo "</pre>";

// Verificar documentos de facturación con creado_por
echo "<h2>Documentos de Facturación con creado_por:</h2>";
$stmt = $conn->query("SELECT id, tipo_documento, total, creado_por, creado_en FROM documentos_facturacion ORDER BY creado_en DESC LIMIT 10");
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($docs);
echo "</pre>";
?>

<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requiereLogin();
header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->query("
    SELECT id, tipo_documento, documento,
           CONCAT(nombre_razon_social, ' ', COALESCE(apellido, '')) as nombre_completo
    FROM clientes
    ORDER BY nombre_razon_social ASC
");

echo json_encode(['success' => true, 'clientes' => $stmt->fetchAll()]);
?>

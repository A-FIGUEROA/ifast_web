<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener archivo del pedido
$stmt = $conn->prepare("SELECT * FROM recibos_pedidos WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$pedido = $stmt->fetch();

if (!$pedido || !file_exists($pedido['ruta'])) {
    die("Archivo no encontrado");
}

// Usar nombre original si existe, sino usar nombre_archivo
$nombre_descarga = isset($pedido['nombre_original']) && !empty($pedido['nombre_original'])
                   ? $pedido['nombre_original']
                   : $pedido['nombre_archivo'];

// Configurar headers para descarga
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $nombre_descarga . '"');
header('Content-Length: ' . filesize($pedido['ruta']));
header('Cache-Control: must-revalidate');

// Leer y enviar archivo
readfile($pedido['ruta']);
exit();
?>
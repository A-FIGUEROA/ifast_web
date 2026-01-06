<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener archivo
$stmt = $conn->prepare("SELECT * FROM archivos_clientes WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$archivo = $stmt->fetch();

if (!$archivo || !file_exists($archivo['ruta'])) {
    die("Archivo no encontrado");
}

// Usar nombre original si existe, sino usar nombre_archivo
$nombre_descarga = isset($archivo['nombre_original']) && !empty($archivo['nombre_original'])
                   ? $archivo['nombre_original']
                   : $archivo['nombre_archivo'];

// Configurar headers para descarga
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $nombre_descarga . '"');
header('Content-Length: ' . filesize($archivo['ruta']));
header('Cache-Control: must-revalidate');

// Leer y enviar archivo
readfile($archivo['ruta']);
exit();
?>
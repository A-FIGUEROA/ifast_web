<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once 'archivos_helper.php';

requiereLogin();

header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

try {
    $id_guia = isset($_GET['id_guia']) ? (int)$_GET['id_guia'] : 0;

    if ($id_guia <= 0) {
        echo json_encode([
            'success' => false,
            'mensaje' => 'ID de guía inválido'
        ]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id_guia FROM guias_embarque WHERE id_guia = :id");
    $stmt->bindParam(':id', $id_guia);
    $stmt->execute();

    if (!$stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'mensaje' => 'Guía no encontrada'
        ]);
        exit;
    }

    $archivos = obtenerArchivosEmbarque($conn, $id_guia);

    echo json_encode([
        'success' => true,
        'archivos' => $archivos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al obtener archivos: ' . $e->getMessage()
    ]);
}
?>

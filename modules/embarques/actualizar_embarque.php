<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();
header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

try {
    $id_guia = (int)$_POST['id_guia'];
    $proveedor = limpiarDatos($_POST['proveedor'] ?? '');
    $contenido = limpiarDatos($_POST['contenido'] ?? '');
    $valor_usd = (float)($_POST['valor_usd'] ?? 0);
    $indicaciones = limpiarDatos($_POST['indicaciones'] ?? '');
    $estado = limpiarDatos($_POST['estado']);

    if ($id_guia <= 0) {
        echo json_encode(['success' => false, 'mensaje' => 'ID inválido']);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE guias_embarque
        SET proveedor = :proveedor,
            contenido = :contenido,
            valor_usd = :valor_usd,
            indicaciones = :indicaciones,
            estado = :estado
        WHERE id_guia = :id
    ");

    $stmt->bindParam(':proveedor', $proveedor);
    $stmt->bindParam(':contenido', $contenido);
    $stmt->bindParam(':valor_usd', $valor_usd);
    $stmt->bindParam(':indicaciones', $indicaciones);
    $stmt->bindParam(':estado', $estado);
    $stmt->bindParam(':id', $id_guia);

    $stmt->execute();

    echo json_encode(['success' => true, 'mensaje' => 'Embarque actualizado exitosamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}
?>

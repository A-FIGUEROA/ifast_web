<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requiereLogin();
header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'mensaje' => 'ID inválido']);
    exit;
}

try {
    $conn->beginTransaction();

    // Obtener tracking_ids antes de eliminar la relación
    $stmt_get = $conn->prepare("SELECT tracking_id FROM guia_pedidos WHERE id_guia = :id");
    $stmt_get->bindParam(':id', $id);
    $stmt_get->execute();
    $tracking_ids = $stmt_get->fetchAll(PDO::FETCH_COLUMN);

    // Eliminar relación entre guía y pedidos
    $stmt = $conn->prepare("DELETE FROM guia_pedidos WHERE id_guia = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    // Eliminar la guía
    $stmt = $conn->prepare("DELETE FROM guias_embarque WHERE id_guia = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    // Revertir estado de los trackings a PENDIENTE
    if (!empty($tracking_ids)) {
        $placeholders = implode(',', array_fill(0, count($tracking_ids), '?'));
        $stmt_update = $conn->prepare("UPDATE pedidos_trackings SET estado_embarque = 'PENDIENTE' WHERE id IN ($placeholders)");
        $stmt_update->execute($tracking_ids);
    }

    $conn->commit();

    echo json_encode(['success' => true, 'mensaje' => 'Embarque eliminado exitosamente']);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}
?>

<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requiereLogin();

header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

// Validar que se envió el cliente_id
if (!isset($_GET['cliente_id']) || empty($_GET['cliente_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de cliente no proporcionado'
    ]);
    exit();
}

$cliente_id = (int)$_GET['cliente_id'];

try {
    // Obtener pedidos pendientes del cliente
    $stmt = $conn->prepare("
        SELECT
            rp.id,
            rp.monto_pendiente,
            rp.subido_en,
            (SELECT COUNT(*) FROM pedidos_trackings pt WHERE pt.recibo_pedido_id = rp.id) as total_trackings
        FROM recibos_pedidos rp
        WHERE rp.cliente_id = :cliente_id
          AND rp.pendiente_pago = 'SI'
          AND rp.monto_pendiente > 0
        ORDER BY rp.subido_en DESC
    ");

    $stmt->bindParam(':cliente_id', $cliente_id);
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'pedidos' => $pedidos
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener pedidos: ' . $e->getMessage()
    ]);
}
?>

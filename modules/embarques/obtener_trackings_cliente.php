<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requiereLogin();

header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

try {
    $cliente_id = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;

    if ($cliente_id <= 0) {
        echo json_encode([
            'success' => false,
            'mensaje' => 'ID de cliente inválido'
        ]);
        exit;
    }

    // Obtener trackings del cliente que NO están en ningún embarque
    $stmt = $conn->prepare("
        SELECT
            pt.id,
            pt.tracking_code,
            COALESCE(rp.nombre_original, rp.nombre_archivo) as nombre_archivo,
            rp.pendiente_pago,
            rp.monto_pendiente,
            DATE_FORMAT(pt.fecha_creacion, '%d/%m/%Y') as fecha
        FROM pedidos_trackings pt
        INNER JOIN recibos_pedidos rp ON pt.recibo_pedido_id = rp.id
        WHERE rp.cliente_id = :cliente_id
        AND pt.id NOT IN (SELECT tracking_id FROM guia_pedidos)
        ORDER BY pt.fecha_creacion DESC
    ");
    $stmt->bindParam(':cliente_id', $cliente_id);
    $stmt->execute();
    $trackings = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'trackings' => $trackings
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al obtener trackings: ' . $e->getMessage()
    ]);
}
?>

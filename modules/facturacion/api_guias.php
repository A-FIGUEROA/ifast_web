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

    // Obtener guías no facturadas del cliente
    $stmt = $conn->prepare("
        SELECT
            id,
            nro_guia,
            consignatario,
            descripcion,
            pcs,
            peso_kg,
            valor_fob_usd,
            fecha_embarque,
            estado
        FROM guias_masivas
        WHERE cliente_id = :cliente_id
        AND facturado = 'NO'
        ORDER BY fecha_embarque DESC, nro_guia DESC
    ");

    $stmt->bindParam(':cliente_id', $cliente_id);
    $stmt->execute();
    $guias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'guias' => $guias
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al obtener guías: ' . $e->getMessage()
    ]);
}
?>

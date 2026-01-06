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
    $nro_guia = limpiarDatos($_POST['nro_guia']);
    $cliente_id = (int)$_POST['cliente_id'];
    $proveedor = limpiarDatos($_POST['proveedor'] ?? '');
    $valor_usd = (float)($_POST['valor_usd'] ?? 0);
    $contenido = limpiarDatos($_POST['contenido'] ?? '');
    $indicaciones = limpiarDatos($_POST['indicaciones'] ?? '');
    $trackings = isset($_POST['trackings']) ? $_POST['trackings'] : [];

    if (empty($nro_guia) || $cliente_id <= 0 || empty($trackings)) {
        echo json_encode(['success' => false, 'mensaje' => 'Datos incompletos']);
        exit;
    }

    $stmt_cliente = $conn->prepare("
        SELECT tipo_documento, documento,
               CONCAT(nombre_razon_social, ' ', COALESCE(apellido, '')) as nombre_completo
        FROM clientes WHERE id = :id
    ");
    $stmt_cliente->bindParam(':id', $cliente_id);
    $stmt_cliente->execute();
    $cliente = $stmt_cliente->fetch();

    if (!$cliente) {
        echo json_encode(['success' => false, 'mensaje' => 'Cliente no encontrado']);
        exit;
    }

    // Obtener usuario actual
    $usuario_id = $_SESSION['usuario_id'];

    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO guias_embarque
        (nro_guia, cliente_id, documento, nombre_completo, tipo_documento, consignatario,
         proveedor, contenido, valor_usd, indicaciones, estado, creado_por)
        VALUES
        (:nro_guia, :cliente_id, :documento, :nombre_completo, :tipo_documento, :consignatario,
         :proveedor, :contenido, :valor_usd, :indicaciones, 'ACTIVO', :creado_por)
    ");

    $stmt->bindParam(':nro_guia', $nro_guia);
    $stmt->bindParam(':cliente_id', $cliente_id);
    $stmt->bindParam(':documento', $cliente['documento']);
    $stmt->bindParam(':nombre_completo', $cliente['nombre_completo']);
    $stmt->bindParam(':tipo_documento', $cliente['tipo_documento']);
    $stmt->bindParam(':consignatario', $cliente['nombre_completo']);
    $stmt->bindParam(':proveedor', $proveedor);
    $stmt->bindParam(':contenido', $contenido);
    $stmt->bindParam(':valor_usd', $valor_usd);
    $stmt->bindParam(':indicaciones', $indicaciones);
    $stmt->bindParam(':creado_por', $usuario_id);

    $stmt->execute();
    $id_guia = $conn->lastInsertId();

    $stmt_tracking = $conn->prepare("INSERT INTO guia_pedidos (id_guia, tracking_id) VALUES (:id_guia, :tracking_id)");
    foreach ($trackings as $tracking_id) {
        $stmt_tracking->bindParam(':id_guia', $id_guia);
        $stmt_tracking->bindParam(':tracking_id', $tracking_id);
        $stmt_tracking->execute();
    }

    // Actualizar estado de los trackings a EMBARCADO
    $placeholders = implode(',', array_fill(0, count($trackings), '?'));
    $stmt_update = $conn->prepare("UPDATE pedidos_trackings SET estado_embarque = 'EMBARCADO' WHERE id IN ($placeholders)");
    $stmt_update->execute($trackings);

    $conn->commit();

    echo json_encode(['success' => true, 'mensaje' => 'Embarque creado exitosamente', 'id' => $id_guia]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}
?>

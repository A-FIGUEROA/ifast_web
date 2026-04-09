<?php
/**
 * API: Detalle de rendimiento de un vendedor por período
 * GET ?usuario_id=X&fecha_desde=Y&fecha_hasta=Z
 */

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/database.php';
require_once '../includes/auth.php';

// Solo ADMIN puede consultar esto
if (!estaLogueado() || $_SESSION['usuario_tipo'] !== 'ADMIN') {
    http_response_code(401);
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
    exit;
}

$usuario_id  = isset($_GET['usuario_id'])  ? (int)$_GET['usuario_id']          : 0;
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde']               : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta']               : date('Y-m-t');

if ($usuario_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'usuario_id inválido']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Datos del vendedor
    $stmt = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre, tipo, email FROM usuarios WHERE id = :uid LIMIT 1");
    $stmt->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $vendedor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendedor) {
        echo json_encode(['success' => false, 'mensaje' => 'Vendedor no encontrado']);
        exit;
    }

    // Clientes nuevos del período
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM clientes
        WHERE creado_por = :uid
          AND DATE(creado_en) >= :fd AND DATE(creado_en) <= :fh
    ");
    $stmt->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':fd', $fecha_desde);
    $stmt->bindParam(':fh', $fecha_hasta);
    $stmt->execute();
    $clientes = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Pedidos del período (vía clientes del vendedor)
    $stmt = $conn->prepare("
        SELECT COUNT(rp.id) as total
        FROM recibos_pedidos rp
        JOIN clientes c ON rp.cliente_id = c.id
        WHERE c.creado_por = :uid
          AND DATE(rp.subido_en) >= :fd AND DATE(rp.subido_en) <= :fh
    ");
    $stmt->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':fd', $fecha_desde);
    $stmt->bindParam(':fh', $fecha_hasta);
    $stmt->execute();
    $pedidos = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Embarques del período
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM guias_embarque
        WHERE creado_por = :uid
          AND DATE(fecha_creacion) >= :fd AND DATE(fecha_creacion) <= :fh
    ");
    $stmt->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':fd', $fecha_desde);
    $stmt->bindParam(':fh', $fecha_hasta);
    $stmt->execute();
    $embarques = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Guías del período
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total, COALESCE(SUM(peso_kg), 0) as peso_total
        FROM guias_masivas
        WHERE creado_por = :uid
          AND DATE(fecha_embarque) >= :fd AND DATE(fecha_embarque) <= :fh
    ");
    $stmt->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':fd', $fecha_desde);
    $stmt->bindParam(':fh', $fecha_hasta);
    $stmt->execute();
    $guias_row   = $stmt->fetch(PDO::FETCH_ASSOC);
    $guias       = (int)$guias_row['total'];
    $peso_total  = (float)$guias_row['peso_total'];

    // Facturación del período (desglosada)
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) as total_docs,
            COUNT(CASE WHEN tipo_documento = 'FACTURA' THEN 1 END) as facturas,
            COUNT(CASE WHEN tipo_documento = 'BOLETA'  THEN 1 END) as boletas,
            COUNT(CASE WHEN tipo_documento = 'RECIBO'  THEN 1 END) as recibos,
            COALESCE(SUM(CASE WHEN tipo_documento = 'FACTURA' THEN total ELSE 0 END), 0) as monto_facturas,
            COALESCE(SUM(CASE WHEN tipo_documento = 'BOLETA'  THEN total ELSE 0 END), 0) as monto_boletas,
            COALESCE(SUM(CASE WHEN tipo_documento = 'RECIBO'  THEN total ELSE 0 END), 0) as monto_recibos,
            COALESCE(SUM(total), 0) as total_facturado
        FROM documentos_facturacion
        WHERE creado_por = :uid
          AND DATE(creado_en) >= :fd AND DATE(creado_en) <= :fh
    ");
    $stmt->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':fd', $fecha_desde);
    $stmt->bindParam(':fh', $fecha_hasta);
    $stmt->execute();
    $fact = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'         => true,
        'vendedor'        => $vendedor['nombre'],
        'rol'             => $vendedor['tipo'],
        'email'           => $vendedor['email'],
        'fecha_desde'     => $fecha_desde,
        'fecha_hasta'     => $fecha_hasta,
        'clientes'        => $clientes,
        'pedidos'         => $pedidos,
        'embarques'       => $embarques,
        'guias'           => $guias,
        'peso_total'      => round($peso_total, 2),
        'facturas'        => (int)$fact['facturas'],
        'boletas'         => (int)$fact['boletas'],
        'recibos'         => (int)$fact['recibos'],
        'monto_facturas'  => (float)$fact['monto_facturas'],
        'monto_boletas'   => (float)$fact['monto_boletas'],
        'monto_recibos'   => (float)$fact['monto_recibos'],
        'total_facturado' => (float)$fact['total_facturado'],
        'total_docs'      => (int)$fact['total_docs'],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error del servidor']);
}

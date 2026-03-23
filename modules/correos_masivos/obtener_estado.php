<?php
// modules/correos_masivos/obtener_estado.php
// Devuelve el estado de progreso de una campaña (polling AJAX)

error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requiereLogin();
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'mensaje' => 'ID inválido']);
    exit;
}

$database = new Database();
$conn     = $database->getConnection();

try {
    $stmt = $conn->prepare("SELECT estado, total_destinatarios, enviados, fallidos FROM campanas_correo WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $campana = $stmt->fetch();

    if (!$campana) {
        echo json_encode(['success' => false, 'mensaje' => 'Campaña no encontrada']);
        exit;
    }

    $pendientes = $campana['total_destinatarios'] - $campana['enviados'] - $campana['fallidos'];

    // Obtener el último email que se está procesando (PENDIENTE más reciente)
    $stmtActual = $conn->prepare("SELECT email FROM campana_destinatarios
        WHERE campana_id = :id AND estado = 'PENDIENTE' ORDER BY id ASC LIMIT 1");
    $stmtActual->bindParam(':id', $id);
    $stmtActual->execute();
    $actual = $stmtActual->fetchColumn();

    echo json_encode([
        'success'   => true,
        'estado'    => $campana['estado'],
        'total'     => (int)$campana['total_destinatarios'],
        'enviados'  => (int)$campana['enviados'],
        'fallidos'  => (int)$campana['fallidos'],
        'pendientes'=> max(0, (int)$pendientes),
        'actual'    => $actual ?: '',
    ]);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);
}

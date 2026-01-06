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

$stmt = $conn->prepare("SELECT * FROM guias_embarque WHERE id_guia = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$guia = $stmt->fetch();

if (!$guia) {
    echo json_encode(['success' => false, 'mensaje' => 'No encontrado']);
    exit;
}

$stmt_trackings = $conn->prepare("
    SELECT pt.tracking_code,
           COALESCE(rp.nombre_original, rp.nombre_archivo) as nombre_archivo
    FROM guia_pedidos gp
    INNER JOIN pedidos_trackings pt ON gp.tracking_id = pt.id
    INNER JOIN recibos_pedidos rp ON pt.recibo_pedido_id = rp.id
    WHERE gp.id_guia = :id
");
$stmt_trackings->bindParam(':id', $id);
$stmt_trackings->execute();
$trackings = $stmt_trackings->fetchAll();

echo json_encode(['success' => true, 'guia' => $guia, 'trackings' => $trackings]);
?>

<?php
// modules/correos_masivos/eliminar.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requiereLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$conn     = $database->getConnection();

try {
    // Eliminar archivos físicos
    $stmtAdj = $conn->prepare("SELECT ruta FROM campana_adjuntos WHERE campana_id = :id");
    $stmtAdj->bindParam(':id', $id);
    $stmtAdj->execute();
    foreach ($stmtAdj->fetchAll() as $adj) {
        $ruta = '../../' . $adj['ruta'];
        if (file_exists($ruta)) @unlink($ruta);
    }
    // Eliminar carpeta de uploads de la campaña
    $dir = '../../uploads/correos_masivos/' . $id . '/';
    if (is_dir($dir)) @rmdir($dir);

    // Eliminar registros en BD (en orden de FK)
    $conn->prepare("DELETE FROM campana_adjuntos     WHERE campana_id = :id")->execute([':id'=>$id]);
    $conn->prepare("DELETE FROM campana_destinatarios WHERE campana_id = :id")->execute([':id'=>$id]);
    $conn->prepare("DELETE FROM campanas_correo       WHERE id = :id")->execute([':id'=>$id]);

    header('Location: index.php?eliminado=1');
} catch (\Throwable $e) {
    header('Location: index.php?error=' . urlencode($e->getMessage()));
}
exit;

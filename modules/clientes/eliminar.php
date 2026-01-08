<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN', 'SUPERVISOR', 'VENTAS']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php?error=ID inválido");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Obtener archivos del cliente para eliminarlos
    $stmt = $conn->prepare("SELECT ruta FROM archivos_clientes WHERE cliente_id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $archivos = $stmt->fetchAll();

    // Eliminar archivos físicos
    foreach ($archivos as $archivo) {
        if (file_exists($archivo['ruta'])) {
            unlink($archivo['ruta']);
        }
    }

    // Eliminar cliente (los archivos en BD se eliminan por CASCADE)
    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = :id");
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute()) {
        header("Location: index.php?success=Cliente y archivos eliminados exitosamente");
    } else {
        header("Location: index.php?error=Error al eliminar el cliente");
    }
} catch(PDOException $e) {
    header("Location: index.php?error=Error al eliminar: " . $e->getMessage());
}
exit();
?>
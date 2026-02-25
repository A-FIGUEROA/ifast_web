<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Solo ADMIN puede eliminar pedidos (antes: ADMIN, SUPERVISOR, VENTAS)
requierePermiso(['ADMIN']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php?error=ID inválido");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Obtener ruta del archivo
    $stmt = $conn->prepare("SELECT ruta FROM recibos_pedidos WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $pedido = $stmt->fetch();

    if ($pedido) {
        // Eliminar archivo físico
        if (file_exists($pedido['ruta'])) {
            unlink($pedido['ruta']);
        }

        // Eliminar pedido de BD
        $stmt = $conn->prepare("DELETE FROM recibos_pedidos WHERE id = :id");
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            header("Location: index.php?success=Pedido eliminado exitosamente");
        } else {
            header("Location: index.php?error=Error al eliminar el pedido");
        }
    } else {
        header("Location: index.php?error=Pedido no encontrado");
    }
} catch(PDOException $e) {
    header("Location: index.php?error=Error al eliminar: " . $e->getMessage());
}
exit();
?>

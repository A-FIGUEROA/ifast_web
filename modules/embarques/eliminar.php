<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requierePermiso(['ADMIN']);

$database = new Database();
$conn = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    header("Location: index.php?error=ID inválido");
    exit();
}

try {
    $conn->beginTransaction();

    // Eliminar relaciones de trackings primero
    $stmt = $conn->prepare("DELETE FROM guia_pedidos WHERE id_guia = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    // Eliminar embarque
    $stmt = $conn->prepare("DELETE FROM guias_embarque WHERE id_guia = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $conn->commit();

    header("Location: index.php?success=eliminado");
    exit();

} catch (PDOException $e) {
    $conn->rollBack();
    header("Location: index.php?error=Error al eliminar: " . $e->getMessage());
    exit();
}
?>

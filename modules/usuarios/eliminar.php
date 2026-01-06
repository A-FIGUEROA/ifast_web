<!-- ============================================
     ARCHIVO 4: modules/usuarios/eliminar.php
     ============================================ -->
<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php?error=ID inválido");
    exit();
}

// Verificar que no se elimine a sí mismo
if ($id == $_SESSION['usuario_id']) {
    header("Location: index.php?error=No puedes eliminar tu propia cuenta");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute()) {
        header("Location: index.php?success=Usuario eliminado exitosamente");
    } else {
        header("Location: index.php?error=Error al eliminar el usuario");
    }
} catch(PDOException $e) {
    header("Location: index.php?error=Error al eliminar el usuario");
}
exit();
?>
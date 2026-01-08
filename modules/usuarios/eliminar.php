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
    // Verificar si el usuario tiene registros asociados
    $stmt_check = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM clientes WHERE creado_por = :id1) as clientes,
            (SELECT COUNT(*) FROM pedidos WHERE creado_por = :id2) as pedidos,
            (SELECT COUNT(*) FROM guias_masivas WHERE creado_por = :id3) as guias
    ");
    $stmt_check->bindParam(':id1', $id);
    $stmt_check->bindParam(':id2', $id);
    $stmt_check->bindParam(':id3', $id);
    $stmt_check->execute();
    $registros = $stmt_check->fetch();

    // Si tiene registros asociados, no permitir eliminar
    if ($registros['clientes'] > 0 || $registros['pedidos'] > 0 || $registros['guias'] > 0) {
        $mensaje = "No se puede eliminar el usuario porque tiene registros asociados: ";
        $detalles = [];
        if ($registros['clientes'] > 0) $detalles[] = "{$registros['clientes']} cliente(s)";
        if ($registros['pedidos'] > 0) $detalles[] = "{$registros['pedidos']} pedido(s)";
        if ($registros['guias'] > 0) $detalles[] = "{$registros['guias']} guía(s)";
        $mensaje .= implode(", ", $detalles);
        header("Location: index.php?error=" . urlencode($mensaje));
        exit();
    }

    // Si no tiene registros asociados, proceder con la eliminación
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        header("Location: index.php?success=Usuario eliminado exitosamente");
    } else {
        header("Location: index.php?error=Error al eliminar el usuario");
    }
} catch(PDOException $e) {
    // Capturar error de foreign key constraint
    if (strpos($e->getMessage(), 'foreign key constraint') !== false || $e->getCode() == '23000') {
        header("Location: index.php?error=" . urlencode("No se puede eliminar el usuario porque tiene registros asociados en el sistema"));
    } else {
        header("Location: index.php?error=Error al eliminar el usuario: " . $e->getMessage());
    }
}
exit();
?>
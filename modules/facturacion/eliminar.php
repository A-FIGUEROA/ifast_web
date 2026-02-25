<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Solo ADMIN puede eliminar documentos de facturación (antes: ADMIN, SUPERVISOR)
requierePermiso(['ADMIN']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php?error=ID inválido");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Obtener ruta del archivo PDF e imagen antes de eliminar
    $stmt = $conn->prepare("SELECT ruta_archivo, imagen_adjunta, numero_documento FROM documentos_facturacion WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $documento = $stmt->fetch();

    if ($documento) {
        // Eliminar archivo PDF si existe
        if (!empty($documento['ruta_archivo']) && file_exists($documento['ruta_archivo'])) {
            unlink($documento['ruta_archivo']);
        }

        // Eliminar imagen adjunta si existe
        if (!empty($documento['imagen_adjunta']) && file_exists($documento['imagen_adjunta'])) {
            unlink($documento['imagen_adjunta']);
        }

        // Eliminar documento de BD
        $stmt = $conn->prepare("DELETE FROM documentos_facturacion WHERE id = :id");
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            header("Location: index.php?success=Documento " . $documento['numero_documento'] . " eliminado exitosamente");
        } else {
            header("Location: index.php?error=Error al eliminar el documento");
        }
    } else {
        header("Location: index.php?error=Documento no encontrado");
    }
} catch(PDOException $e) {
    header("Location: index.php?error=Error: " . $e->getMessage());
}
exit();
?>

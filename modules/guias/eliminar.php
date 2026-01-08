<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Solo ADMIN y SUPERVISOR pueden eliminar
requierePermiso(['ADMIN', 'SUPERVISOR']);

$database = new Database();
$conn = $database->getConnection();

// Obtener ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    header("Location: index.php");
    exit();
}

try {
    // Soft delete: cambiar estado a OBSERVADO
    $stmt = $conn->prepare("UPDATE guias_masivas SET estado = 'OBSERVADO' WHERE id = :id");
    $stmt->bindParam(':id', $id);

    // O hard delete (descomentar si prefieres eliminar definitivamente):
    // $stmt = $conn->prepare("DELETE FROM guias_masivas WHERE id = :id");
    // $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        header("Location: index.php?success=eliminado");
        exit();
    } else {
        header("Location: index.php?error=no_eliminado");
        exit();
    }
} catch (PDOException $e) {
    header("Location: index.php?error=no_eliminado");
    exit();
}

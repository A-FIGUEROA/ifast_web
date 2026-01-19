<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Todos los usuarios logueados pueden cambiar estado
requiereLogin();

header('Content-Type: application/json');

$response = [
    'success' => false,
    'mensaje' => ''
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nuevo_estado = isset($_POST['estado']) ? $_POST['estado'] : '';

    if ($id === 0) {
        throw new Exception('ID no válido');
    }

    // Validar que el estado sea válido
    $estados_validos = ['ENTREGADO', 'PENDIENTE', 'OBSERVADO', 'LIQUIDADO'];
    if (!in_array($nuevo_estado, $estados_validos)) {
        throw new Exception('Estado no válido');
    }

    $database = new Database();
    $conn = $database->getConnection();

    // Actualizar estado
    $stmt = $conn->prepare("UPDATE guias_masivas SET estado = :estado WHERE id = :id");
    $stmt->bindParam(':estado', $nuevo_estado);
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['mensaje'] = 'Estado actualizado correctamente';
        $response['nuevo_estado'] = $nuevo_estado;
    } else {
        throw new Exception('Error al actualizar el estado');
    }

} catch (Exception $e) {
    $response['mensaje'] = $e->getMessage();
}

echo json_encode($response);

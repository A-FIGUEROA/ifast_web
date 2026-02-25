<?php
/**
 * API: Registrar cambio de estado
 * Endpoint para cambiar el estado del usuario (CONECTADO, REFRIGERIO, DESCONECTADO)
 */

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/control_horario_functions.php';

// Verificar que el usuario esté logueado
if (!estaLogueado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Obtener datos del request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['estado'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Estado no especificado']);
    exit;
}

$nuevo_estado = strtoupper($data['estado']);
$notas = $data['notas'] ?? null;

// Validar estado
$estados_validos = ['CONECTADO', 'REFRIGERIO', 'DESCONECTADO'];
if (!in_array($nuevo_estado, $estados_validos)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Estado inválido']);
    exit;
}

// Conectar a la base de datos
$database = new Database();
$conn = $database->getConnection();

try {
    // Obtener estado actual antes del cambio
    $estado_anterior = obtenerEstadoActual($conn, $usuario_id);

    // Validar transiciones de estado
    $estado_prev = $estado_anterior['estado_actual'];

    // No permitir transición de DESCONECTADO a REFRIGERIO directamente
    if ($estado_prev === 'DESCONECTADO' && $nuevo_estado === 'REFRIGERIO') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'mensaje' => 'Debe iniciar jornada antes de ir a refrigerio'
        ]);
        exit;
    }

    // Registrar el cambio de estado
    $resultado = registrarCambioEstado($conn, $usuario_id, $nuevo_estado, $notas);

    if ($resultado) {
        // Obtener tiempo actualizado
        $tiempos = calcularTiempoTrabajadoHoy($conn, $usuario_id);

        echo json_encode([
            'success' => true,
            'mensaje' => 'Estado actualizado correctamente',
            'estado' => $nuevo_estado,
            'tiempo_trabajado' => $tiempos['tiempo_trabajado'],
            'tiempo_refrigerio' => $tiempos['tiempo_refrigerio'],
            'tiempo_trabajado_format' => $tiempos['tiempo_trabajado_format'],
            'tiempo_refrigerio_format' => $tiempos['tiempo_refrigerio_format'],
            'hora_inicio' => $tiempos['hora_inicio']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'mensaje' => 'Error al registrar el estado'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

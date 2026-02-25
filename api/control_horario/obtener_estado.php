<?php
/**
 * API: Obtener estado actual del usuario
 * Retorna el estado actual y tiempos trabajados del día
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

// Conectar a la base de datos
$database = new Database();
$conn = $database->getConnection();

try {
    // Obtener estado actual
    $estado = obtenerEstadoActual($conn, $usuario_id);

    // Calcular tiempos en tiempo real
    $tiempos = calcularTiempoTrabajadoHoy($conn, $usuario_id);

    echo json_encode([
        'success' => true,
        'estado' => $tiempos['estado_actual'],
        'hora_inicio' => $tiempos['hora_inicio'],
        'tiempo_trabajado' => $tiempos['tiempo_trabajado'],
        'tiempo_refrigerio' => $tiempos['tiempo_refrigerio'],
        'tiempo_trabajado_format' => $tiempos['tiempo_trabajado_format'],
        'tiempo_refrigerio_format' => $tiempos['tiempo_refrigerio_format'],
        'fecha' => date('Y-m-d')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

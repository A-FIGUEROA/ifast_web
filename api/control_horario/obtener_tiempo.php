<?php
/**
 * API: Obtener tiempo trabajado en tiempo real
 * Endpoint para actualizar el cronómetro del widget
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
    // Calcular tiempos en tiempo real
    $tiempos = calcularTiempoTrabajadoHoy($conn, $usuario_id);

    // Calcular tiempo transcurrido desde última actualización para el cronómetro
    $estado = obtenerEstadoActual($conn, $usuario_id);
    $tiempo_cronometro = 0;

    if ($estado['ultima_actualizacion'] && $estado['estado_actual'] !== 'DESCONECTADO') {
        $ultima = new DateTime($estado['ultima_actualizacion']);
        $ahora = new DateTime();
        $diferencia = $ahora->getTimestamp() - $ultima->getTimestamp();
        $tiempo_cronometro = floor($diferencia); // Segundos
    }

    echo json_encode([
        'success' => true,
        'estado' => $tiempos['estado_actual'],
        'tiempo_trabajado' => $tiempos['tiempo_trabajado'],
        'tiempo_refrigerio' => $tiempos['tiempo_refrigerio'],
        'tiempo_trabajado_format' => $tiempos['tiempo_trabajado_format'],
        'tiempo_refrigerio_format' => $tiempos['tiempo_refrigerio_format'],
        'tiempo_cronometro_segundos' => $tiempo_cronometro,
        'hora_inicio' => $tiempos['hora_inicio'],
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

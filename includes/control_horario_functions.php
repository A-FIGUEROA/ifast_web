<?php
/**
 * FUNCIONES PARA CONTROL DE HORARIOS Y ASISTENCIA
 * Sistema de registro de tiempo de trabajo
 */

/**
 * Obtiene el estado actual del usuario en el día de hoy
 * @param PDO $conn Conexión a la base de datos
 * @param int $usuario_id ID del usuario
 * @return array|false Estado actual o false si no existe
 */
function obtenerEstadoActual($conn, $usuario_id) {
    try {
        $stmt = $conn->prepare("
            SELECT
                estado_actual,
                hora_inicio,
                hora_fin,
                tiempo_trabajado,
                tiempo_refrigerio,
                fecha,
                ultima_actualizacion
            FROM sesiones_trabajo
            WHERE usuario_id = ? AND fecha = CURDATE()
        ");
        $stmt->execute([$usuario_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resultado) {
            return [
                'estado_actual' => 'DESCONECTADO',
                'hora_inicio' => null,
                'hora_fin' => null,
                'tiempo_trabajado' => 0,
                'tiempo_refrigerio' => 0,
                'fecha' => date('Y-m-d'),
                'ultima_actualizacion' => null
            ];
        }

        return $resultado;
    } catch (PDOException $e) {
        error_log("Error en obtenerEstadoActual: " . $e->getMessage());
        return false;
    }
}

/**
 * Registra un cambio de estado del usuario
 * @param PDO $conn Conexión a la base de datos
 * @param int $usuario_id ID del usuario
 * @param string $nuevo_estado Estado nuevo (CONECTADO, REFRIGERIO, DESCONECTADO)
 * @param string $notas Notas opcionales
 * @return bool True si se registró correctamente
 */
function registrarCambioEstado($conn, $usuario_id, $nuevo_estado, $notas = null) {
    try {
        $conn->beginTransaction();

        // Obtener estado actual antes del cambio
        $estado_anterior = obtenerEstadoActual($conn, $usuario_id);

        // Insertar en registros_tiempo (log)
        $stmt = $conn->prepare("
            INSERT INTO registros_tiempo
            (usuario_id, estado, fecha_hora, ip_address, user_agent, notas)
            VALUES (?, ?, NOW(), ?, ?, ?)
        ");

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $stmt->execute([
            $usuario_id,
            $nuevo_estado,
            $ip,
            $user_agent,
            $notas
        ]);

        // Actualizar o crear sesión de trabajo
        actualizarSesionTrabajo($conn, $usuario_id, $nuevo_estado, $estado_anterior);

        $conn->commit();
        return true;

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error en registrarCambioEstado: " . $e->getMessage());
        return false;
    }
}

/**
 * Actualiza la sesión de trabajo del día actual
 * @param PDO $conn Conexión a la base de datos
 * @param int $usuario_id ID del usuario
 * @param string $nuevo_estado Estado nuevo
 * @param array $estado_anterior Estado anterior
 */
function actualizarSesionTrabajo($conn, $usuario_id, $nuevo_estado, $estado_anterior) {
    try {
        // Calcular tiempo transcurrido desde el último cambio
        $tiempo_transcurrido = 0;
        if ($estado_anterior['ultima_actualizacion']) {
            $ultima = new DateTime($estado_anterior['ultima_actualizacion']);
            $ahora = new DateTime();
            $tiempo_transcurrido = ($ahora->getTimestamp() - $ultima->getTimestamp()) / 60; // Minutos
        }

        // Determinar qué campo actualizar según el estado anterior
        $tiempo_trabajado_add = 0;
        $tiempo_refrigerio_add = 0;

        if ($estado_anterior['estado_actual'] === 'CONECTADO') {
            $tiempo_trabajado_add = $tiempo_transcurrido;
        } elseif ($estado_anterior['estado_actual'] === 'REFRIGERIO') {
            $tiempo_refrigerio_add = $tiempo_transcurrido;
        }

        // Verificar si existe una sesión hoy
        $stmt = $conn->prepare("
            SELECT id FROM sesiones_trabajo
            WHERE usuario_id = ? AND fecha = CURDATE()
        ");
        $stmt->execute([$usuario_id]);
        $existe = $stmt->fetch();

        if ($existe) {
            // Actualizar sesión existente
            $stmt = $conn->prepare("
                UPDATE sesiones_trabajo
                SET
                    estado_actual = ?,
                    tiempo_trabajado = tiempo_trabajado + ?,
                    tiempo_refrigerio = tiempo_refrigerio + ?,
                    ultima_actualizacion = NOW(),
                    hora_fin = CASE WHEN ? = 'DESCONECTADO' THEN NOW() ELSE hora_fin END
                WHERE usuario_id = ? AND fecha = CURDATE()
            ");
            $stmt->execute([
                $nuevo_estado,
                round($tiempo_trabajado_add),
                round($tiempo_refrigerio_add),
                $nuevo_estado,
                $usuario_id
            ]);
        } else {
            // Crear nueva sesión
            $stmt = $conn->prepare("
                INSERT INTO sesiones_trabajo
                (usuario_id, fecha, hora_inicio, estado_actual, ultima_actualizacion)
                VALUES (?, CURDATE(), NOW(), ?, NOW())
            ");
            $stmt->execute([$usuario_id, $nuevo_estado]);
        }

    } catch (PDOException $e) {
        error_log("Error en actualizarSesionTrabajo: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Calcula tiempo trabajado en tiempo real
 * @param PDO $conn Conexión a la base de datos
 * @param int $usuario_id ID del usuario
 * @return array Tiempos calculados
 */
function calcularTiempoTrabajadoHoy($conn, $usuario_id) {
    $estado = obtenerEstadoActual($conn, $usuario_id);

    if (!$estado) {
        return [
            'tiempo_trabajado' => 0,
            'tiempo_refrigerio' => 0,
            'tiempo_trabajado_format' => '0h 0m',
            'tiempo_refrigerio_format' => '0h 0m'
        ];
    }

    $tiempo_trabajado = $estado['tiempo_trabajado'];
    $tiempo_refrigerio = $estado['tiempo_refrigerio'];

    // Si está actualmente conectado o en refrigerio, sumar tiempo actual
    if ($estado['ultima_actualizacion'] && $estado['estado_actual'] !== 'DESCONECTADO') {
        $ultima = new DateTime($estado['ultima_actualizacion']);
        $ahora = new DateTime();
        $tiempo_actual = ($ahora->getTimestamp() - $ultima->getTimestamp()) / 60;

        if ($estado['estado_actual'] === 'CONECTADO') {
            $tiempo_trabajado += $tiempo_actual;
        } elseif ($estado['estado_actual'] === 'REFRIGERIO') {
            $tiempo_refrigerio += $tiempo_actual;
        }
    }

    return [
        'tiempo_trabajado' => round($tiempo_trabajado),
        'tiempo_refrigerio' => round($tiempo_refrigerio),
        'tiempo_trabajado_format' => formatearTiempo(round($tiempo_trabajado)),
        'tiempo_refrigerio_format' => formatearTiempo(round($tiempo_refrigerio)),
        'estado_actual' => $estado['estado_actual'],
        'hora_inicio' => $estado['hora_inicio']
    ];
}

/**
 * Obtiene reporte diario de todos los usuarios
 * @param PDO $conn Conexión a la base de datos
 * @param string $fecha Fecha en formato Y-m-d
 * @return array Lista de usuarios con sus datos
 */
function obtenerReporteDiario($conn, $fecha = null) {
    if (!$fecha) {
        $fecha = date('Y-m-d');
    }

    try {
        $stmt = $conn->prepare("
            SELECT
                u.id,
                u.nombre,
                u.apellido,
                u.email,
                u.tipo,
                st.hora_inicio,
                st.hora_fin,
                st.tiempo_trabajado,
                st.tiempo_refrigerio,
                st.estado_actual,
                st.ultima_actualizacion
            FROM usuarios u
            LEFT JOIN sesiones_trabajo st ON u.id = st.usuario_id AND st.fecha = ?
            WHERE u.tipo IN ('SUPERVISOR', 'VENTAS', 'ASESOR')
            ORDER BY u.nombre ASC, u.apellido ASC
        ");
        $stmt->execute([$fecha]);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formatear tiempos
        foreach ($usuarios as &$usuario) {
            $usuario['tiempo_trabajado_format'] = formatearTiempo($usuario['tiempo_trabajado'] ?? 0);
            $usuario['tiempo_refrigerio_format'] = formatearTiempo($usuario['tiempo_refrigerio'] ?? 0);
            $usuario['estado_actual'] = $usuario['estado_actual'] ?? 'DESCONECTADO';
        }

        return $usuarios;

    } catch (PDOException $e) {
        error_log("Error en obtenerReporteDiario: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene reporte semanal de un usuario
 * @param PDO $conn Conexión a la base de datos
 * @param int $usuario_id ID del usuario
 * @param string $fecha_inicio Fecha de inicio de la semana
 * @return array Datos de la semana
 */
function obtenerReporteSemanal($conn, $usuario_id, $fecha_inicio = null) {
    if (!$fecha_inicio) {
        $fecha_inicio = date('Y-m-d', strtotime('monday this week'));
    }

    $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . ' +6 days'));

    try {
        $stmt = $conn->prepare("
            SELECT
                fecha,
                hora_inicio,
                hora_fin,
                tiempo_trabajado,
                tiempo_refrigerio,
                estado_actual
            FROM sesiones_trabajo
            WHERE usuario_id = ?
              AND fecha BETWEEN ? AND ?
            ORDER BY fecha ASC
        ");
        $stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
        $dias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular totales
        $total_trabajado = 0;
        $total_refrigerio = 0;

        foreach ($dias as &$dia) {
            $dia['tiempo_trabajado_format'] = formatearTiempo($dia['tiempo_trabajado']);
            $dia['tiempo_refrigerio_format'] = formatearTiempo($dia['tiempo_refrigerio']);
            $total_trabajado += $dia['tiempo_trabajado'];
            $total_refrigerio += $dia['tiempo_refrigerio'];
        }

        return [
            'dias' => $dias,
            'total_trabajado' => $total_trabajado,
            'total_refrigerio' => $total_refrigerio,
            'total_trabajado_format' => formatearTiempo($total_trabajado),
            'total_refrigerio_format' => formatearTiempo($total_refrigerio),
            'promedio_diario' => count($dias) > 0 ? formatearTiempo($total_trabajado / count($dias)) : '0h 0m'
        ];

    } catch (PDOException $e) {
        error_log("Error en obtenerReporteSemanal: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene reporte mensual de todos los usuarios
 * @param PDO $conn Conexión a la base de datos
 * @param string $mes Mes en formato Y-m
 * @return array Lista de usuarios con sus totales
 */
function obtenerReporteMensual($conn, $mes = null) {
    if (!$mes) {
        $mes = date('Y-m');
    }

    try {
        $stmt = $conn->prepare("
            SELECT
                u.id,
                u.nombre,
                u.apellido,
                u.email,
                u.tipo,
                COUNT(st.id) as dias_trabajados,
                SUM(st.tiempo_trabajado) as total_trabajado,
                SUM(st.tiempo_refrigerio) as total_refrigerio,
                AVG(st.tiempo_trabajado) as promedio_diario
            FROM usuarios u
            LEFT JOIN sesiones_trabajo st ON u.id = st.usuario_id
                AND DATE_FORMAT(st.fecha, '%Y-%m') = ?
                AND st.tiempo_trabajado > 0
            WHERE u.tipo IN ('SUPERVISOR', 'VENTAS', 'ASESOR')
            GROUP BY u.id, u.nombre, u.apellido, u.email, u.tipo
            ORDER BY total_trabajado DESC
        ");
        $stmt->execute([$mes]);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formatear datos
        foreach ($usuarios as &$usuario) {
            $usuario['total_trabajado_format'] = formatearTiempo($usuario['total_trabajado'] ?? 0);
            $usuario['total_refrigerio_format'] = formatearTiempo($usuario['total_refrigerio'] ?? 0);
            $usuario['promedio_diario_format'] = formatearTiempo($usuario['promedio_diario'] ?? 0);
        }

        return $usuarios;

    } catch (PDOException $e) {
        error_log("Error en obtenerReporteMensual: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene historial de registros de un usuario en un día
 * @param PDO $conn Conexión a la base de datos
 * @param int $usuario_id ID del usuario
 * @param string $fecha Fecha en formato Y-m-d
 * @return array Lista de registros
 */
function obtenerHistorialDia($conn, $usuario_id, $fecha) {
    try {
        $stmt = $conn->prepare("
            SELECT
                estado,
                fecha_hora,
                notas
            FROM registros_tiempo
            WHERE usuario_id = ? AND DATE(fecha_hora) = ?
            ORDER BY fecha_hora ASC
        ");
        $stmt->execute([$usuario_id, $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error en obtenerHistorialDia: " . $e->getMessage());
        return [];
    }
}

/**
 * Formatea minutos a formato legible (Xh Ym)
 * @param int $minutos Total de minutos
 * @return string Tiempo formateado
 */
function formatearTiempo($minutos) {
    if ($minutos <= 0) return '0h 0m';

    $horas = floor($minutos / 60);
    $mins = $minutos % 60;

    return "{$horas}h {$mins}m";
}

/**
 * Formatea minutos a formato HH:MM:SS para cronómetro
 * @param int $minutos Total de minutos
 * @return string Tiempo en formato HH:MM:SS
 */
function formatearTiempoCronometro($minutos) {
    $horas = floor($minutos / 60);
    $mins = floor($minutos % 60);
    $segs = floor(($minutos - floor($minutos)) * 60);

    return sprintf('%02d:%02d:%02d', $horas, $mins, $segs);
}

/**
 * Obtiene el conteo de usuarios por estado en tiempo real
 * @param PDO $conn Conexión a la base de datos
 * @return array Conteo por estado
 */
function obtenerConteoEstados($conn) {
    try {
        $stmt = $conn->query("
            SELECT
                COALESCE(estado_actual, 'DESCONECTADO') as estado,
                COUNT(*) as cantidad
            FROM vista_estado_usuarios
            GROUP BY estado_actual
        ");

        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $conteo = [
            'CONECTADO' => 0,
            'REFRIGERIO' => 0,
            'DESCONECTADO' => 0
        ];

        foreach ($resultado as $row) {
            $conteo[$row['estado']] = $row['cantidad'];
        }

        return $conteo;

    } catch (PDOException $e) {
        error_log("Error en obtenerConteoEstados: " . $e->getMessage());
        return ['CONECTADO' => 0, 'REFRIGERIO' => 0, 'DESCONECTADO' => 0];
    }
}

<?php
// includes/functions.php
// Funciones generales del sistema

// Función para limpiar datos de entrada
function limpiarDatos($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Función para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Función para validar DNI (8 dígitos)
function validarDNI($dni) {
    return preg_match('/^\d{8}$/', $dni);
}

// Función para validar RUC (11 dígitos)
function validarRUC($ruc) {
    return preg_match('/^\d{11}$/', $ruc);
}

// Función para formatear fecha
function formatearFecha($fecha, $formato = 'd/m/Y H:i') {
    return date($formato, strtotime($fecha));
}

// Función para subir archivo
function subirArchivo($archivo, $carpeta_destino, $tipos_permitidos = ['pdf', 'xlsx', 'xls']) {
    $resultado = [
        'exito' => false,
        'mensaje' => '',
        'nombre_archivo' => '',
        'nombre_original' => '',
        'ruta' => ''
    ];

    // Verificar si hay errores en la carga
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $resultado['mensaje'] = 'Error al subir el archivo';
        return $resultado;
    }

    // Guardar nombre original del archivo
    $nombre_original = basename($archivo['name']);

    // Obtener extensión del archivo
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    // Validar tipo de archivo
    if (!in_array($extension, $tipos_permitidos)) {
        $resultado['mensaje'] = 'Tipo de archivo no permitido. Solo: ' . implode(', ', $tipos_permitidos);
        return $resultado;
    }

    // Validar tamaño (máximo 50MB)
    if ($archivo['size'] > 52428800) {
        $resultado['mensaje'] = 'El archivo es demasiado grande. Máximo 50MB';
        return $resultado;
    }

    // Crear carpeta si no existe
    if (!file_exists($carpeta_destino)) {
        mkdir($carpeta_destino, 0777, true);
    }

    // Generar nombre único para el archivo
    $nombre_unico = uniqid() . '_' . time() . '.' . $extension;
    $ruta_completa = $carpeta_destino . '/' . $nombre_unico;

    // Mover archivo
    if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
        $resultado['exito'] = true;
        $resultado['mensaje'] = 'Archivo subido correctamente';
        $resultado['nombre_archivo'] = $nombre_unico;
        $resultado['nombre_original'] = $nombre_original;
        $resultado['ruta'] = $ruta_completa;
    } else {
        $resultado['mensaje'] = 'Error al guardar el archivo';
    }

    return $resultado;
}

// Función para eliminar archivo
function eliminarArchivo($ruta) {
    if (file_exists($ruta)) {
        return unlink($ruta);
    }
    return false;
}

// Función para obtener el tipo de archivo
function obtenerTipoArchivo($nombre_archivo) {
    $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
    
    $tipos = [
        'pdf' => 'PDF',
        'xlsx' => 'Excel',
        'xls' => 'Excel',
        'doc' => 'Word',
        'docx' => 'Word',
        'jpg' => 'Imagen',
        'jpeg' => 'Imagen',
        'png' => 'Imagen'
    ];
    
    return $tipos[$extension] ?? 'Desconocido';
}

// Función para generar contraseña hasheada
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Función para mostrar alertas
function mostrarAlerta($tipo, $mensaje) {
    $clase = '';
    $icono = '';
    
    switch($tipo) {
        case 'success':
            $clase = 'alert-success';
            $icono = '✓';
            break;
        case 'error':
            $clase = 'alert-danger';
            $icono = '✗';
            break;
        case 'warning':
            $clase = 'alert-warning';
            $icono = '⚠';
            break;
        case 'info':
            $clase = 'alert-info';
            $icono = 'ℹ';
            break;
        default:
            $clase = 'alert-secondary';
            $icono = '';
    }
    
    echo '<div class="alert ' . $clase . ' alert-dismissible fade show" role="alert">';
    echo '<strong>' . $icono . '</strong> ' . $mensaje;
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
}

// Función para obtener estadísticas del dashboard
function obtenerEstadisticas($conn, $tipo_usuario = null) {
    // Inicializar stats con valores por defecto
    $stats = [
        'total_usuarios' => 0,
        'total_clientes' => 0,
        'total_pedidos' => 0,
        'total_archivos' => 0,
        'pedidos_recientes' => [],
        'clientes_hoy' => 0,
        'clientes_ayer' => 0,
        'clientes_ultimos_7_dias' => 0,
        'clientes_ultimos_30_dias' => 0,
        'pedidos_hoy' => 0,
        'pedidos_ayer' => 0,
        'pedidos_ultimos_7_dias' => 0,
        'pedidos_ultimos_30_dias' => 0,
        'embarques_hoy' => 0,
        'embarques_ayer' => 0,
        'embarques_ultimos_7_dias' => 0,
        'embarques_ultimos_30_dias' => 0,
        'total_facturacion_hoy' => 0,
        'total_facturacion_ayer' => 0,
        'guias_pendientes' => 0,
        'guias_entregadas' => 0,
        'guias_observadas' => 0,
        'total_guias' => 0,
        'top_clientes_pedidos' => [],
        'top_clientes_facturacion' => [],
        'tendencia_clientes' => [],
        'tendencia_pedidos' => [],
        'tendencia_embarques' => [],
        'tendencia_facturacion' => [],
        'embarques_recientes' => [],
        'documentos_recientes' => [],
        'clientes_recientes' => [],
        'total_embarques' => 0,
        'total_documentos_facturacion' => 0,
        'usuarios_activos' => 0,
        // Facturación por tipo y periodo
        'facturas_hoy' => 0,
        'boletas_hoy' => 0,
        'recibos_hoy' => 0,
        'monto_facturas_hoy' => 0,
        'monto_boletas_hoy' => 0,
        'monto_recibos_hoy' => 0,
        'facturas_ultimos_7_dias' => 0,
        'boletas_ultimos_7_dias' => 0,
        'recibos_ultimos_7_dias' => 0,
        'monto_facturas_ultimos_7_dias' => 0,
        'monto_boletas_ultimos_7_dias' => 0,
        'monto_recibos_ultimos_7_dias' => 0,
        'total_facturacion_ultimos_7_dias' => 0,
        'facturas_ultimos_30_dias' => 0,
        'boletas_ultimos_30_dias' => 0,
        'recibos_ultimos_30_dias' => 0,
        'monto_facturas_ultimos_30_dias' => 0,
        'monto_boletas_ultimos_30_dias' => 0,
        'monto_recibos_ultimos_30_dias' => 0,
        'total_facturacion_ultimos_30_dias' => 0
    ];

    try {
        // ========================================
        // ESTADÍSTICAS BÁSICAS (Para todos)
        // ========================================

        // Total de usuarios
        $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios");
        $stats['total_usuarios'] = $stmt->fetch()['total'];

        // Total de clientes
        $stmt = $conn->query("SELECT COUNT(*) as total FROM clientes");
        $stats['total_clientes'] = $stmt->fetch()['total'];

        // Total de pedidos
        $stmt = $conn->query("SELECT COUNT(*) as total FROM recibos_pedidos");
        $stats['total_pedidos'] = $stmt->fetch()['total'];

        // Total de archivos
        $stmt = $conn->query("SELECT COUNT(*) as total FROM archivos_clientes");
        $stats['total_archivos'] = $stmt->fetch()['total'];

        // Pedidos recientes
        $stmt = $conn->query("
            SELECT
                rp.*,
                c.nombre_razon_social,
                c.apellido,
                GROUP_CONCAT(pt.tracking_code SEPARATOR ', ') as tracking_pedido
            FROM recibos_pedidos rp
            INNER JOIN clientes c ON rp.cliente_id = c.id
            LEFT JOIN pedidos_trackings pt ON rp.id = pt.recibo_pedido_id
            GROUP BY rp.id
            ORDER BY rp.subido_en DESC
            LIMIT 5
        ");
        $stats['pedidos_recientes'] = $stmt->fetchAll();

        // ========================================
        // ESTADÍSTICAS AVANZADAS SOLO PARA ADMIN
        // ========================================

        if ($tipo_usuario === 'ADMIN') {

            // ===== CLIENTES =====
            // Clientes hoy
            $stmt = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE DATE(creado_en) = CURDATE()");
            $stats['clientes_hoy'] = $stmt->fetch()['total'];

            // Clientes últimos 7 días
            $stmt = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stats['clientes_ultimos_7_dias'] = $stmt->fetch()['total'];

            // Clientes últimos 30 días
            $stmt = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
            $stats['clientes_ultimos_30_dias'] = $stmt->fetch()['total'];

            // Clientes ayer (para comparación)
            $stmt = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE DATE(creado_en) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
            $stats['clientes_ayer'] = $stmt->fetch()['total'];

            // ===== PEDIDOS =====
            // Pedidos hoy
            $stmt = $conn->query("SELECT COUNT(*) as total FROM recibos_pedidos WHERE DATE(subido_en) = CURDATE()");
            $stats['pedidos_hoy'] = $stmt->fetch()['total'];

            // Pedidos últimos 7 días
            $stmt = $conn->query("SELECT COUNT(*) as total FROM recibos_pedidos WHERE subido_en >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stats['pedidos_ultimos_7_dias'] = $stmt->fetch()['total'];

            // Pedidos últimos 30 días
            $stmt = $conn->query("SELECT COUNT(*) as total FROM recibos_pedidos WHERE subido_en >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
            $stats['pedidos_ultimos_30_dias'] = $stmt->fetch()['total'];

            // Pedidos ayer
            $stmt = $conn->query("SELECT COUNT(*) as total FROM recibos_pedidos WHERE DATE(subido_en) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
            $stats['pedidos_ayer'] = $stmt->fetch()['total'];

            // ===== EMBARQUES/GUÍAS =====
            // Embarques hoy
            $stmt = $conn->query("SELECT COUNT(*) as total FROM guias_masivas WHERE DATE(creado_en) = CURDATE()");
            $stats['embarques_hoy'] = $stmt->fetch()['total'];

            // Embarques últimos 7 días
            $stmt = $conn->query("SELECT COUNT(*) as total FROM guias_masivas WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stats['embarques_ultimos_7_dias'] = $stmt->fetch()['total'];

            // Embarques últimos 30 días
            $stmt = $conn->query("SELECT COUNT(*) as total FROM guias_masivas WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
            $stats['embarques_ultimos_30_dias'] = $stmt->fetch()['total'];

            // Embarques ayer
            $stmt = $conn->query("SELECT COUNT(*) as total FROM guias_masivas WHERE DATE(creado_en) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
            $stats['embarques_ayer'] = $stmt->fetch()['total'];

            // ===== FACTURACIÓN =====
            // Facturación hoy por tipo
            $stmt = $conn->query("
                SELECT
                    tipo_documento,
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total), 0) as monto_total
                FROM documentos_facturacion
                WHERE DATE(creado_en) = CURDATE()
                GROUP BY tipo_documento
            ");
            $facturas_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stats['facturas_hoy'] = 0;
            $stats['boletas_hoy'] = 0;
            $stats['recibos_hoy'] = 0;
            $stats['monto_facturas_hoy'] = 0;
            $stats['monto_boletas_hoy'] = 0;
            $stats['monto_recibos_hoy'] = 0;
            foreach ($facturas_hoy as $doc) {
                if ($doc['tipo_documento'] == 'FACTURA') {
                    $stats['facturas_hoy'] = $doc['cantidad'];
                    $stats['monto_facturas_hoy'] = $doc['monto_total'];
                } elseif ($doc['tipo_documento'] == 'BOLETA') {
                    $stats['boletas_hoy'] = $doc['cantidad'];
                    $stats['monto_boletas_hoy'] = $doc['monto_total'];
                } elseif ($doc['tipo_documento'] == 'RECIBO') {
                    $stats['recibos_hoy'] = $doc['cantidad'];
                    $stats['monto_recibos_hoy'] = $doc['monto_total'];
                }
            }
            $stats['total_facturacion_hoy'] = $stats['monto_facturas_hoy'] + $stats['monto_boletas_hoy'] + $stats['monto_recibos_hoy'];

            // Facturación últimos 7 días por tipo
            $stmt = $conn->query("
                SELECT
                    tipo_documento,
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total), 0) as monto_total
                FROM documentos_facturacion
                WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY tipo_documento
            ");
            $facturas_ultimos_7_dias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stats['facturas_ultimos_7_dias'] = 0;
            $stats['boletas_ultimos_7_dias'] = 0;
            $stats['recibos_ultimos_7_dias'] = 0;
            $stats['monto_facturas_ultimos_7_dias'] = 0;
            $stats['monto_boletas_ultimos_7_dias'] = 0;
            $stats['monto_recibos_ultimos_7_dias'] = 0;
            foreach ($facturas_ultimos_7_dias as $doc) {
                if ($doc['tipo_documento'] == 'FACTURA') {
                    $stats['facturas_ultimos_7_dias'] = $doc['cantidad'];
                    $stats['monto_facturas_ultimos_7_dias'] = $doc['monto_total'];
                } elseif ($doc['tipo_documento'] == 'BOLETA') {
                    $stats['boletas_ultimos_7_dias'] = $doc['cantidad'];
                    $stats['monto_boletas_ultimos_7_dias'] = $doc['monto_total'];
                } elseif ($doc['tipo_documento'] == 'RECIBO') {
                    $stats['recibos_ultimos_7_dias'] = $doc['cantidad'];
                    $stats['monto_recibos_ultimos_7_dias'] = $doc['monto_total'];
                }
            }
            $stats['total_facturacion_ultimos_7_dias'] = $stats['monto_facturas_ultimos_7_dias'] + $stats['monto_boletas_ultimos_7_dias'] + $stats['monto_recibos_ultimos_7_dias'];

            // Facturación últimos 30 días por tipo
            $stmt = $conn->query("
                SELECT
                    tipo_documento,
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total), 0) as monto_total
                FROM documentos_facturacion
                WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY tipo_documento
            ");
            $facturas_ultimos_30_dias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stats['facturas_ultimos_30_dias'] = 0;
            $stats['boletas_ultimos_30_dias'] = 0;
            $stats['recibos_ultimos_30_dias'] = 0;
            $stats['monto_facturas_ultimos_30_dias'] = 0;
            $stats['monto_boletas_ultimos_30_dias'] = 0;
            $stats['monto_recibos_ultimos_30_dias'] = 0;
            foreach ($facturas_ultimos_30_dias as $doc) {
                if ($doc['tipo_documento'] == 'FACTURA') {
                    $stats['facturas_ultimos_30_dias'] = $doc['cantidad'];
                    $stats['monto_facturas_ultimos_30_dias'] = $doc['monto_total'];
                } elseif ($doc['tipo_documento'] == 'BOLETA') {
                    $stats['boletas_ultimos_30_dias'] = $doc['cantidad'];
                    $stats['monto_boletas_ultimos_30_dias'] = $doc['monto_total'];
                } elseif ($doc['tipo_documento'] == 'RECIBO') {
                    $stats['recibos_ultimos_30_dias'] = $doc['cantidad'];
                    $stats['monto_recibos_ultimos_30_dias'] = $doc['monto_total'];
                }
            }
            $stats['total_facturacion_ultimos_30_dias'] = $stats['monto_facturas_ultimos_30_dias'] + $stats['monto_boletas_ultimos_30_dias'] + $stats['monto_recibos_ultimos_30_dias'];

            // Facturación ayer (para comparación)
            $stmt = $conn->query("SELECT COALESCE(SUM(total), 0) as total FROM documentos_facturacion WHERE DATE(creado_en) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
            $stats['total_facturacion_ayer'] = $stmt->fetch()['total'];

            // ===== ESTADOS DE GUÍAS/EMBARQUES =====
            // Guías por estado
            $stmt = $conn->query("SELECT estado, COUNT(*) as total FROM guias_masivas GROUP BY estado");
            $guias_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stats['guias_pendientes'] = 0;
            $stats['guias_entregadas'] = 0;
            $stats['guias_observadas'] = 0;
            foreach ($guias_estado as $guia) {
                if ($guia['estado'] == 'PENDIENTE') {
                    $stats['guias_pendientes'] = $guia['total'];
                } elseif ($guia['estado'] == 'ENTREGADO') {
                    $stats['guias_entregadas'] = $guia['total'];
                } elseif ($guia['estado'] == 'OBSERVADO') {
                    $stats['guias_observadas'] = $guia['total'];
                }
            }
            $stats['total_guias'] = $stats['guias_pendientes'] + $stats['guias_entregadas'] + $stats['guias_observadas'];

            // ===== TOP 10 CLIENTES CON MÁS PEDIDOS =====
            $stmt = $conn->query("
                SELECT
                    c.id,
                    c.nombre_razon_social,
                    c.apellido,
                    COUNT(rp.id) as total_pedidos
                FROM clientes c
                LEFT JOIN recibos_pedidos rp ON c.id = rp.cliente_id
                GROUP BY c.id, c.nombre_razon_social, c.apellido
                HAVING total_pedidos > 0
                ORDER BY total_pedidos DESC
                LIMIT 10
            ");
            $stats['top_clientes_pedidos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ===== TOP 10 CLIENTES CON MAYOR FACTURACIÓN =====
            $stmt = $conn->query("
                SELECT
                    c.id,
                    c.nombre_razon_social,
                    c.apellido,
                    COALESCE(SUM(df.total), 0) as total_facturado,
                    COUNT(df.id) as total_documentos
                FROM clientes c
                LEFT JOIN documentos_facturacion df ON c.id = df.cliente_id
                GROUP BY c.id, c.nombre_razon_social, c.apellido
                HAVING total_facturado > 0
                ORDER BY total_facturado DESC
                LIMIT 10
            ");
            $stats['top_clientes_facturacion'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ===== TENDENCIA MENSUAL (ÚLTIMOS 6 MESES) =====
            // Clientes por mes
            $stmt = $conn->query("
                SELECT
                    DATE_FORMAT(creado_en, '%Y-%m') as mes,
                    DATE_FORMAT(creado_en, '%b') as mes_nombre,
                    COUNT(*) as total
                FROM clientes
                WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY mes, mes_nombre
                ORDER BY mes ASC
            ");
            $stats['tendencia_clientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Pedidos por mes
            $stmt = $conn->query("
                SELECT
                    DATE_FORMAT(subido_en, '%Y-%m') as mes,
                    DATE_FORMAT(subido_en, '%b') as mes_nombre,
                    COUNT(*) as total
                FROM recibos_pedidos
                WHERE subido_en >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY mes, mes_nombre
                ORDER BY mes ASC
            ");
            $stats['tendencia_pedidos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Embarques por mes
            $stmt = $conn->query("
                SELECT
                    DATE_FORMAT(creado_en, '%Y-%m') as mes,
                    DATE_FORMAT(creado_en, '%b') as mes_nombre,
                    COUNT(*) as total
                FROM guias_masivas
                WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY mes, mes_nombre
                ORDER BY mes ASC
            ");
            $stats['tendencia_embarques'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Facturación por mes
            $stmt = $conn->query("
                SELECT
                    DATE_FORMAT(creado_en, '%Y-%m') as mes,
                    DATE_FORMAT(creado_en, '%b') as mes_nombre,
                    COALESCE(SUM(total), 0) as total
                FROM documentos_facturacion
                WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY mes, mes_nombre
                ORDER BY mes ASC
            ");
            $stats['tendencia_facturacion'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ===== EMBARQUES/GUÍAS RECIENTES =====
            $stmt = $conn->query("
                SELECT
                    gm.*,
                    c.nombre_razon_social,
                    c.apellido
                FROM guias_masivas gm
                LEFT JOIN clientes c ON gm.cliente_id = c.id
                ORDER BY gm.creado_en DESC
                LIMIT 5
            ");
            $stats['embarques_recientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ===== DOCUMENTOS DE FACTURACIÓN RECIENTES =====
            $stmt = $conn->query("
                SELECT
                    df.*,
                    c.nombre_razon_social,
                    c.apellido
                FROM documentos_facturacion df
                LEFT JOIN clientes c ON df.cliente_id = c.id
                ORDER BY df.creado_en DESC
                LIMIT 5
            ");
            $stats['documentos_recientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ===== CLIENTES REGISTRADOS RECIENTEMENTE =====
            $stmt = $conn->query("
                SELECT
                    id,
                    nombre_razon_social,
                    apellido,
                    email,
                    telefono,
                    tipo_documento,
                    numero_documento,
                    creado_en
                FROM clientes
                ORDER BY creado_en DESC
                LIMIT 5
            ");
            $stats['clientes_recientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ===== TOTALES ACUMULADOS =====
            $stmt = $conn->query("SELECT COUNT(*) as total FROM guias_masivas");
            $stats['total_embarques'] = $stmt->fetch()['total'];

            $stmt = $conn->query("SELECT COUNT(*) as total FROM documentos_facturacion");
            $stats['total_documentos_facturacion'] = $stmt->fetch()['total'];

            $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1");
            $stats['usuarios_activos'] = $stmt->fetch()['total'];
        }

    } catch(PDOException $e) {
        $stats['error'] = $e->getMessage();
    }

    return $stats;
}

// Función para paginar resultados
function paginar($total_registros, $registros_por_pagina, $pagina_actual) {
    $total_paginas = ceil($total_registros / $registros_por_pagina);
    $offset = ($pagina_actual - 1) * $registros_por_pagina;
    
    return [
        'total_paginas' => $total_paginas,
        'offset' => $offset,
        'pagina_actual' => $pagina_actual,
        'registros_por_pagina' => $registros_por_pagina
    ];
}
?>
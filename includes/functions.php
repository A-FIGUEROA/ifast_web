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

    // Validar tamaño (máximo 5MB)
    if ($archivo['size'] > 5242880) {
        $resultado['mensaje'] = 'El archivo es demasiado grande. Máximo 5MB';
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
function obtenerEstadisticas($conn) {
    $stats = [];
    
    try {
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
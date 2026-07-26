<?php
// Helper compartido: determina qué archivos pertenecen legítimamente a una guía.
// Nunca confiar en rutas que llegan del cliente (POST/GET) para adjuntar/leer archivos;
// esta función es la única fuente de verdad sobre qué 'key' -> ruta es válida por id_guia.

require_once __DIR__ . '/generar_excel.php';

function obtenerArchivosEmbarque($conn, $id_guia) {
    $stmt = $conn->prepare("SELECT * FROM guias_embarque WHERE id_guia = :id");
    $stmt->bindParam(':id', $id_guia);
    $stmt->execute();
    $guia = $stmt->fetch();

    if (!$guia) {
        return [];
    }

    $archivos = [];

    // 1. EXCEL DEL EMBARQUE (Auto-generar si no existe)
    try {
        $ruta_excel = $guia['ruta_excel'];

        if (empty($ruta_excel) || !file_exists($ruta_excel)) {
            $ruta_excel = generarExcelEmbarque($conn, $id_guia, true);
        }

        if (!empty($ruta_excel) && file_exists($ruta_excel)) {
            $archivos[] = [
                'key' => 'excel',
                'tipo' => 'excel',
                'nombre' => 'Excel Embarque - ' . $guia['nro_guia'],
                'descripcion' => 'Guía de embarque en formato Excel',
                'ruta' => $ruta_excel,
                'tamano' => formatBytesArchivo(filesize($ruta_excel)),
                'auto_seleccionar' => true
            ];
        }
    } catch (Exception $e) {
        // Si falla generar Excel, continuar sin él
    }

    // 2. DNI/RUC DEL CLIENTE
    try {
        $stmt_cliente = $conn->prepare("
            SELECT c.id,
                   ac.ruta,
                   COALESCE(ac.nombre_original, ac.nombre_archivo) as nombre_archivo
            FROM clientes c
            LEFT JOIN archivos_clientes ac ON c.id = ac.cliente_id
            WHERE c.documento = :documento
            LIMIT 1
        ");
        $stmt_cliente->bindParam(':documento', $guia['documento']);
        $stmt_cliente->execute();
        $cliente = $stmt_cliente->fetch();

        if ($cliente && !empty($cliente['ruta']) && file_exists($cliente['ruta'])) {
            $archivos[] = [
                'key' => 'dni',
                'tipo' => 'dni',
                'nombre' => 'DNI/RUC Cliente',
                'descripcion' => $cliente['nombre_archivo'],
                'ruta' => $cliente['ruta'],
                'tamano' => formatBytesArchivo(filesize($cliente['ruta'])),
                'auto_seleccionar' => true
            ];
        }
    } catch (Exception $e) {
        // Si falla cargar DNI, continuar sin él
    }

    // 3. RECIBOS DE LOS TRACKINGS
    try {
        $stmt_recibos = $conn->prepare("
            SELECT DISTINCT
                rp.id,
                rp.ruta,
                COALESCE(rp.nombre_original, rp.nombre_archivo) as nombre_archivo
            FROM guia_pedidos gp
            INNER JOIN pedidos_trackings pt ON gp.tracking_id = pt.id
            INNER JOIN recibos_pedidos rp ON pt.recibo_pedido_id = rp.id
            WHERE gp.id_guia = :id_guia
            AND rp.ruta IS NOT NULL
        ");
        $stmt_recibos->bindParam(':id_guia', $id_guia);
        $stmt_recibos->execute();
        $recibos = $stmt_recibos->fetchAll();

        foreach ($recibos as $recibo) {
            if (!empty($recibo['ruta']) && file_exists($recibo['ruta'])) {
                $archivos[] = [
                    'key' => 'recibo_' . $recibo['id'],
                    'tipo' => 'recibo',
                    'nombre' => 'Recibo #' . $recibo['id'],
                    'descripcion' => $recibo['nombre_archivo'],
                    'ruta' => $recibo['ruta'],
                    'tamano' => formatBytesArchivo(filesize($recibo['ruta'])),
                    'auto_seleccionar' => true
                ];
            }
        }
    } catch (Exception $e) {
        // Si falla cargar recibos, continuar sin ellos
    }

    return $archivos;
}

function formatBytesArchivo($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

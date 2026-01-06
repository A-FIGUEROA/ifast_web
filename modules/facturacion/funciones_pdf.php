<?php
require_once '../../vendor/autoload.php';
require_once '../../includes/functions.php';

/**
 * Genera el contenido HTML del documento de facturación
 * @param array $doc Datos del documento
 * @return string HTML del documento
 */
function generarHTMLDocumento($doc) {
    // Convertir logo a base64 para incluirlo en el PDF
    $logo_path = __DIR__ . '/../../assets/logo/logo_fact.png';
    $logo_base64 = '';
    if (file_exists($logo_path)) {
        $logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
    }

    $html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            line-height: 1.4;
        }

        .factura-container {
            padding: 15px;
        }

        .header-table {
            width: 100%;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #00296b;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .header-table td.logo-cell {
            width: 280px;
            text-align: center;
            padding-right: 15px;
        }

        .header-table td.logo-cell img {
            max-width: 250px;
            height: auto;
        }

        .header-table td.info-cell {
            text-align: right;
        }

        .factura-info {
            border: 2px solid #00296b;
            padding: 12px 15px;
            text-align: center;
            background: white;
            display: inline-block;
            min-width: 230px;
        }

        .factura-info h1 {
            color: #00296b;
            font-size: 13pt;
            margin: 0 0 8px 0;
            font-weight: bold;
        }

        .factura-info .numero {
            font-size: 11pt;
            font-weight: bold;
            color: #00509d;
            margin: 0 0 8px 0;
        }

        .factura-info .ruc {
            font-size: 8pt;
            margin: 0 0 5px 0;
        }

        .factura-info .fecha {
            font-size: 8pt;
            color: #666;
            margin: 0;
        }

        .empresa-section {
            background: #00296b;
            color: white;
            padding: 6px 12px;
            margin-bottom: 4px;
            font-weight: bold;
            font-size: 9pt;
        }

        .empresa-datos {
            background: #f8f9fa;
            padding: 8px 12px;
            margin-bottom: 15px;
            font-size: 9pt;
            line-height: 1.5;
        }

        .cliente-section {
            background: #00296b;
            color: white;
            padding: 6px 12px;
            margin-bottom: 4px;
            font-weight: bold;
            font-size: 9pt;
        }

        .cliente-datos {
            background: #f8f9fa;
            padding: 8px 12px;
            margin-bottom: 15px;
            font-size: 9pt;
        }

        .cliente-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .cliente-grid td {
            padding: 5px;
            vertical-align: top;
            border: none;
        }

        .dato-label {
            font-weight: bold;
            color: #00296b;
            font-size: 8pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        thead {
            background: #00296b;
            color: white;
        }

        th {
            padding: 10px 6px;
            text-align: left;
            font-size: 9pt;
            font-weight: 600;
        }

        th.center {
            text-align: center;
        }

        th.right {
            text-align: right;
        }

        td {
            padding: 8px 6px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 9pt;
        }

        td.center {
            text-align: center;
        }

        td.right {
            text-align: right;
        }

        .observaciones {
            margin: 15px 0;
        }

        .observaciones-title {
            background: #00296b;
            color: white;
            padding: 6px 12px;
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 4px;
        }

        .observaciones-content {
            background: #f8f9fa;
            padding: 8px 12px;
            min-height: 50px;
            font-size: 9pt;
        }

        .totales-section {
            float: right;
            width: 250px;
            margin-top: 15px;
        }

        .total-row {
            display: table;
            width: 100%;
            padding: 6px 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 9pt;
        }

        .total-row span:first-child {
            display: table-cell;
            text-align: left;
        }

        .total-row span:last-child {
            display: table-cell;
            text-align: right;
        }

        .total-row.subtotal {
            background: #f8f9fa;
        }

        .total-row.final {
            background: #00296b;
            color: white;
            font-weight: bold;
            font-size: 11pt;
            border: none;
        }

        .footer-text {
            clear: both;
            margin-top: 60px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            font-size: 8pt;
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="factura-container">
        <!-- HEADER -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">';
                    if (!empty($logo_base64)) {
                        $html .= '<img src="' . $logo_base64 . '" alt="Logo IFAST">';
                    }
                $html .= '
                </td>
                <td class="info-cell">
                    <div class="factura-info">
                        <h1>' . htmlspecialchars($doc['tipo_documento']) . '</h1>
                        <div class="numero">' . htmlspecialchars($doc['numero_documento']) . '</div>
                        <div class="ruc">RUC: 20611227982</div>
                        <div class="fecha">Fecha: ' . formatearFecha($doc['creado_en'], 'd/m/Y') . '</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- DATOS DE LA EMPRESA -->
        <div class="empresa-section">DATOS DEL EMISOR</div>
        <div class="empresa-datos">
            <strong>INTERNATIONAL COURIER SERVICE S.A.C.</strong><br>
            Dirección:  MZA. A LOTE. 10 URB. LOS PRODUCTORES LIMA - LIMA - SANTA ANITA<br>
            Cel: (+51) 902 937 040 | Email: info@ifast.com.pe
        </div>

        <!-- DATOS DEL CLIENTE -->
        <div class="cliente-section">DATOS DEL CLIENTE</div>
        <div class="cliente-datos">
            <table class="cliente-grid">
                <tr>
                    <td style="width: 50%;">
                        <span class="dato-label">Nombre/Razón Social:</span><br>
                        ' . htmlspecialchars($doc['nombre_razon_social']);
                        if ($doc['apellido']) {
                            $html .= ' ' . htmlspecialchars($doc['apellido']);
                        }
    $html .= '
                    </td>
                    <td style="width: 50%;">
                        <span class="dato-label">' . htmlspecialchars($doc['cliente_tipo_doc']) . ':</span><br>
                        ' . htmlspecialchars($doc['documento']) . '
                    </td>
                </tr>';

if ($doc['direccion'] || $doc['email'] || $doc['celular']) {
    $html .= '<tr>';

    if ($doc['direccion']) {
        $html .= '
                    <td colspan="2">
                        <span class="dato-label">Dirección:</span><br>
                        ' . htmlspecialchars($doc['direccion']);
                        if ($doc['distrito']) $html .= ', ' . htmlspecialchars($doc['distrito']);
                        if ($doc['provincia']) $html .= ', ' . htmlspecialchars($doc['provincia']);
                        if ($doc['departamento']) $html .= ', ' . htmlspecialchars($doc['departamento']);
        $html .= '
                    </td>';
    }

    $html .= '</tr>';
}

if ($doc['email'] || $doc['celular']) {
    $html .= '<tr>
                    <td colspan="2">';
                        if ($doc['email']) {
                            $html .= '<span class="dato-label">Email:</span> ' . htmlspecialchars($doc['email']);
                        }
                        if ($doc['email'] && $doc['celular']) {
                            $html .= ' | ';
                        }
                        if ($doc['celular']) {
                            $html .= '<span class="dato-label">Teléfono:</span> ' . htmlspecialchars($doc['celular']);
                        }
    $html .= '
                    </td>
                </tr>';
}

$html .= '
            </table>
        </div>

        <!-- TABLA DE SERVICIOS -->
        <table>
            <thead>
                <tr>
                    <th class="center" style="width: 60px;">CANT.</th>
                    <th>DESCRIPCIÓN</th>
                    <th class="right" style="width: 100px;">P.UNIT</th>
                    <th class="right" style="width: 100px;">IMPORTE</th>
                </tr>
            </thead>
            <tbody>';

if ($doc['peso_total'] > 0) {
    $html .= '
                <tr>
                    <td class="center">' . number_format($doc['peso_total'], 3) . ' kg</td>
                    <td>Servicio de Envío por Peso</td>
                    <td class="right">$10.00</td>
                    <td class="right">$' . number_format($doc['costo_peso'], 2) . '</td>
                </tr>';
}

if ($doc['total_paquetes'] > 0) {
    $html .= '
                <tr>
                    <td class="center">' . $doc['total_paquetes'] . '</td>
                    <td>Total de Paquetes (Informativo)</td>
                    <td class="right">-</td>
                    <td class="right">-</td>
                </tr>';
}

if ($doc['total_guias'] > 0) {
    $html .= '
                <tr>
                    <td class="center">' . $doc['total_guias'] . '</td>
                    <td>Servicio de Desaduanaje</td>
                    <td class="right">$5.00</td>
                    <td class="right">$' . number_format($doc['costo_desaduanaje'], 2) . '</td>
                </tr>';
}

if ($doc['cantidad_cambio_consignatario'] > 0) {
    $html .= '
                <tr>
                    <td class="center">' . $doc['cantidad_cambio_consignatario'] . '</td>
                    <td>Cambio de Consignatario</td>
                    <td class="right">$3.00</td>
                    <td class="right">$' . number_format($doc['costo_cambio_consignatario'], 2) . '</td>
                </tr>';
}

if ($doc['cantidad_reempaque'] > 0) {
    $html .= '
                <tr>
                    <td class="center">' . $doc['cantidad_reempaque'] . '</td>
                    <td>Servicio de Reempaque</td>
                    <td class="right">$5.00</td>
                    <td class="right">$' . number_format($doc['costo_reempaque'], 2) . '</td>
                </tr>';
}

if ($doc['envio_provincia'] === 'SI') {
    $html .= '
                <tr>
                    <td class="center">1</td>
                    <td>Envío a Provincia</td>
                    <td class="right">$3.00</td>
                    <td class="right">$' . number_format($doc['costo_envio_provincia'], 2) . '</td>
                </tr>';
}

if ($doc['pendiente_pago'] > 0) {
    $html .= '
                <tr>
                    <td class="center">-</td>
                    <td>Pendiente de Pago</td>
                    <td class="right">-</td>
                    <td class="right">$' . number_format($doc['pendiente_pago'], 2) . '</td>
                </tr>';
}

if ($doc['gastos_adicionales'] > 0) {
    $html .= '
                <tr>
                    <td class="center">-</td>
                    <td>
                        Gastos Adicionales';
                        if ($doc['detalle_gastos_adicionales']) {
                            $html .= '<br><small style="color: #666;">(' . htmlspecialchars($doc['detalle_gastos_adicionales']) . ')</small>';
                        }
    $html .= '
                    </td>
                    <td class="right">-</td>
                    <td class="right">$' . number_format($doc['gastos_adicionales'], 2) . '</td>
                </tr>';
}

if ($doc['descuento'] > 0) {
    $html .= '
                <tr style="background: #ffe6e6;">
                    <td class="center">-</td>
                    <td>
                        <strong style="color: #c0392b;">Descuento</strong>';
                        if ($doc['detalle_descuento']) {
                            $html .= '<br><small style="color: #666;">(' . htmlspecialchars($doc['detalle_descuento']) . ')</small>';
                        }
    $html .= '
                    </td>
                    <td class="right">-</td>
                    <td class="right" style="color: #c0392b;"><strong>-$' . number_format($doc['descuento'], 2) . '</strong></td>
                </tr>';
}

$html .= '
            </tbody>
        </table>

        <!-- OBSERVACIONES -->
        <div class="observaciones">
            <div class="observaciones-title">OBSERVACIONES</div>
            <div class="observaciones-content">';
                if ($doc['canal_aduanas']) {
                    $html .= '<strong>Canal de Aduanas:</strong> ';
                    if ($doc['canal_aduanas'] === 'VERDE') $html .= 'CANAL VERDE';
                    elseif ($doc['canal_aduanas'] === 'NARANJA') $html .= 'CANAL NARANJA';
                    elseif ($doc['canal_aduanas'] === 'ROJO') $html .= 'CANAL ROJO';
                    $html .= '<br>';
                }
$html .= '
                Documento generado electrónicamente por el Sistema de Gestión.
            </div>
        </div>

        <!-- TOTALES -->
        <div class="totales-section">';
            if ($doc['tipo_documento'] === 'FACTURA' || $doc['tipo_documento'] === 'BOLETA') {
                $html .= '
            <div class="total-row subtotal">
                <span>SUBTOTAL:</span>
                <span>$' . number_format($doc['subtotal'], 2) . '</span>
            </div>
            <div class="total-row">
                <span>IGV (18%):</span>
                <span>$' . number_format($doc['igv'], 2) . '</span>
            </div>';
            }
$html .= '
            <div class="total-row final">
                <span>TOTAL:</span>
                <span>$' . number_format($doc['total'], 2) . '</span>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer-text">
            SON: ';
            $total_entero = floor($doc['total']);
            $total_decimales = round(($doc['total'] - $total_entero) * 100);
            $html .= strtoupper("DÓLARES AMERICANOS CON $total_decimales/100");
$html .= '<br>
            Documento generado electrónicamente - Sistema de Gestión ' . date('Y') . '
        </div>
    </div>
</body>
</html>';

    return $html;
}

/**
 * Genera y guarda el PDF del documento
 * @param PDO $conn Conexión a la base de datos
 * @param int $id_documento ID del documento
 * @param string $ruta_destino Ruta donde guardar el PDF
 * @return bool True si se generó correctamente
 */
function generarYGuardarPDF($conn, $id_documento, $ruta_destino) {
    // Obtener datos del documento con joins
    $stmt = $conn->prepare("SELECT df.*, c.nombre_razon_social, c.apellido, c.tipo_documento as cliente_tipo_doc,
                                  c.documento, c.direccion, c.distrito, c.provincia, c.departamento,
                                  c.email, c.celular
                           FROM documentos_facturacion df
                           INNER JOIN clientes c ON df.cliente_id = c.id
                           WHERE df.id = :id");
    $stmt->bindParam(':id', $id_documento);
    $stmt->execute();
    $doc = $stmt->fetch();

    if (!$doc) {
        return false;
    }

    // Generar HTML
    $html = generarHTMLDocumento($doc);

    // Crear instancia de mPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_header' => 0,
        'margin_footer' => 0
    ]);

    // Escribir HTML al PDF
    $mpdf->WriteHTML($html);

    // Asegurar que el directorio existe
    $directorio = dirname($ruta_destino);
    if (!file_exists($directorio)) {
        mkdir($directorio, 0777, true);
    }

    // Guardar PDF en el servidor
    $mpdf->Output($ruta_destino, \Mpdf\Output\Destination::FILE);

    return file_exists($ruta_destino);
}
?>

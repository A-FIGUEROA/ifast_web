<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN']);

$database = new Database();
$conn = $database->getConnection();

// Obtener todos los pedidos con información del cliente
$stmt = $conn->query("
    SELECT
        rp.id,
        rp.tracking_pedido,
        c.nombre_razon_social,
        c.apellido,
        c.tipo_documento,
        c.documento,
        c.email,
        c.celular,
        rp.nombre_original,
        rp.nombre_archivo,
        rp.pendiente_pago,
        rp.monto_pendiente,
        rp.subido_en
    FROM recibos_pedidos rp
    INNER JOIN clientes c ON rp.cliente_id = c.id
    ORDER BY rp.subido_en DESC
");
$pedidos = $stmt->fetchAll();

if (count($pedidos) === 0) {
    header("Location: ../../dashboard.php?error=No hay pedidos para exportar");
    exit();
}

$filename = "pedidos_" . date('Y-m-d_His') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Inicio del XML para Excel
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:html="http://www.w3.org/TR/REC-html40">';

// Estilos
echo '<Styles>';

// Estilo para encabezados
echo '<Style ss:ID="header">';
echo '<Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="12"/>';
echo '<Interior ss:Color="#00509D" ss:Pattern="Solid"/>';
echo '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
echo '<Borders>';
echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
echo '</Borders>';
echo '</Style>';

// Estilo para datos (filas pares)
echo '<Style ss:ID="even">';
echo '<Interior ss:Color="#F8F9FA" ss:Pattern="Solid"/>';
echo '<Borders>';
echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '</Borders>';
echo '</Style>';

// Estilo para datos (filas impares)
echo '<Style ss:ID="odd">';
echo '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
echo '<Borders>';
echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '</Borders>';
echo '</Style>';

// Estilo para pago pendiente SI (amarillo)
echo '<Style ss:ID="pago_si_even">';
echo '<Interior ss:Color="#FFF3CD" ss:Pattern="Solid"/>';
echo '<Font ss:Bold="1" ss:Color="#856404"/>';
echo '<Borders>';
echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '</Borders>';
echo '<Alignment ss:Horizontal="Center"/>';
echo '</Style>';

echo '<Style ss:ID="pago_si_odd">';
echo '<Interior ss:Color="#FFF3CD" ss:Pattern="Solid"/>';
echo '<Font ss:Bold="1" ss:Color="#856404"/>';
echo '<Borders>';
echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '</Borders>';
echo '<Alignment ss:Horizontal="Center"/>';
echo '</Style>';

// Estilo para pago pendiente NO (verde)
echo '<Style ss:ID="pago_no_even">';
echo '<Interior ss:Color="#D4EDDA" ss:Pattern="Solid"/>';
echo '<Font ss:Color="#155724"/>';
echo '<Borders>';
echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '</Borders>';
echo '<Alignment ss:Horizontal="Center"/>';
echo '</Style>';

echo '<Style ss:ID="pago_no_odd">';
echo '<Interior ss:Color="#D4EDDA" ss:Pattern="Solid"/>';
echo '<Font ss:Color="#155724"/>';
echo '<Borders>';
echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '</Borders>';
echo '<Alignment ss:Horizontal="Center"/>';
echo '</Style>';

// Estilo para monto (amarillo)
echo '<Style ss:ID="monto_si_even">';
echo '<Interior ss:Color="#FFF3CD" ss:Pattern="Solid"/>';
echo '<Font ss:Bold="1" ss:Color="#856404"/>';
echo '<NumberFormat ss:Format="&quot;S/. &quot;#,##0.00"/>';
echo '<Borders>';
echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '</Borders>';
echo '<Alignment ss:Horizontal="Right"/>';
echo '</Style>';

echo '<Style ss:ID="monto_si_odd">';
echo '<Interior ss:Color="#FFF3CD" ss:Pattern="Solid"/>';
echo '<Font ss:Bold="1" ss:Color="#856404"/>';
echo '<NumberFormat ss:Format="&quot;S/. &quot;#,##0.00"/>';
echo '<Borders>';
echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>';
echo '</Borders>';
echo '<Alignment ss:Horizontal="Right"/>';
echo '</Style>';

echo '</Styles>';

// Hoja de trabajo
echo '<Worksheet ss:Name="Pedidos">';
echo '<Table>';

// Columnas
$columnas = ['ID', 'Tracking', 'Cliente', 'Apellido', 'Tipo Doc', 'Documento', 'Email', 'Celular', 'Archivo', 'Pendiente Pago', 'Monto Pendiente', 'Fecha Registro'];
foreach ($columnas as $index => $columna) {
    echo '<Column ss:AutoFitWidth="1"/>';
}

// Fila de encabezados
echo '<Row ss:Height="25">';
foreach ($columnas as $columna) {
    echo '<Cell ss:StyleID="header"><Data ss:Type="String">' . htmlspecialchars($columna) . '</Data></Cell>';
}
echo '</Row>';

// Datos
$rowIndex = 0;
foreach ($pedidos as $pedido) {
    $rowIndex++;
    $estiloBase = ($rowIndex % 2 == 0) ? 'even' : 'odd';

    // Usar nombre original si existe
    $nombreArchivo = !empty($pedido['nombre_original']) ? $pedido['nombre_original'] : $pedido['nombre_archivo'];

    echo '<Row>';
    echo '<Cell ss:StyleID="' . $estiloBase . '"><Data ss:Type="Number">' . $pedido['id'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estiloBase . '"><Data ss:Type="String">' . htmlspecialchars($pedido['tracking_pedido']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estiloBase . '"><Data ss:Type="String">' . htmlspecialchars($pedido['nombre_razon_social']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estiloBase . '"><Data ss:Type="String">' . htmlspecialchars($pedido['apellido']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estiloBase . '"><Data ss:Type="String">' . htmlspecialchars($pedido['tipo_documento']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estiloBase . '"><Data ss:Type="String">' . htmlspecialchars($pedido['documento']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estiloBase . '"><Data ss:Type="String">' . htmlspecialchars($pedido['email']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estiloBase . '"><Data ss:Type="String">' . htmlspecialchars($pedido['celular']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estiloBase . '"><Data ss:Type="String">' . htmlspecialchars($nombreArchivo) . '</Data></Cell>';

    // Pendiente de Pago con formato condicional
    if ($pedido['pendiente_pago'] === 'SI') {
        $estiloPago = 'pago_si_' . $estiloBase;
        $estiloMonto = 'monto_si_' . $estiloBase;
    } else {
        $estiloPago = 'pago_no_' . $estiloBase;
        $estiloMonto = $estiloBase;
    }

    echo '<Cell ss:StyleID="' . $estiloPago . '"><Data ss:Type="String">' . htmlspecialchars($pedido['pendiente_pago']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estiloMonto . '"><Data ss:Type="Number">' . number_format($pedido['monto_pendiente'], 2, '.', '') . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estiloBase . '"><Data ss:Type="String">' . htmlspecialchars(formatearFecha($pedido['subido_en'], 'd/m/Y H:i')) . '</Data></Cell>';
    echo '</Row>';
}

echo '</Table>';

// Opciones de la hoja
echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">';
echo '<FreezePanes/>';
echo '<FrozenNoSplit/>';
echo '<SplitHorizontal>1</SplitHorizontal>';
echo '<TopRowBottomPane>1</TopRowBottomPane>';
echo '<ActivePane>2</ActivePane>';
echo '</WorksheetOptions>';

echo '</Worksheet>';
echo '</Workbook>';

exit();
?>

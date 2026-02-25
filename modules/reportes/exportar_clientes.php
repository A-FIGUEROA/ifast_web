<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Solo ADMIN
requierePermiso(['ADMIN']);

$database = new Database();
$conn = $database->getConnection();

// Obtener todos los clientes
$stmt = $conn->query("
    SELECT
        id,
        tipo_documento,
        documento,
        nombre_razon_social,
        apellido,
        email,
        telif,
        celular,
        direccion,
        distrito,
        provincia,
        departamento,
        creado_en
    FROM clientes
    ORDER BY creado_en DESC
");
$clientes = $stmt->fetchAll();

// Verificar si hay datos
if (count($clientes) === 0) {
    header("Location: ../../dashboard.php?error=No hay clientes para exportar");
    exit();
}

// Nombre del archivo
$filename = "clientes_" . date('Y-m-d_His') . ".xls";

// Headers para descarga Excel
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
echo '<Interior ss:Color="#00296B" ss:Pattern="Solid"/>';
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

// Estilo para centrado
echo '<Style ss:ID="center">';
echo '<Alignment ss:Horizontal="Center"/>';
echo '</Style>';

echo '</Styles>';

// Hoja de trabajo
echo '<Worksheet ss:Name="Clientes">';
echo '<Table>';

// Columnas
$columnas = ['ID', 'Tipo Documento', 'Documento', 'Nombre/Razón Social', 'Apellido', 'Email', 'Teléfono Fijo', 'Celular', 'Dirección', 'Distrito', 'Provincia', 'Departamento', 'Fecha Registro'];
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
foreach ($clientes as $cliente) {
    $rowIndex++;
    $estilo = ($rowIndex % 2 == 0) ? 'even' : 'odd';

    echo '<Row>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="Number">' . $cliente['id'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars($cliente['tipo_documento']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars($cliente['documento']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars($cliente['nombre_razon_social']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars($cliente['apellido']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars($cliente['email']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars($cliente['telif']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars($cliente['celular']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars($cliente['direccion']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars($cliente['distrito']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars($cliente['provincia']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars($cliente['departamento']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="' . $estilo . '"><Data ss:Type="String">' . htmlspecialchars(formatearFecha($cliente['creado_en'], 'd/m/Y H:i')) . '</Data></Cell>';
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

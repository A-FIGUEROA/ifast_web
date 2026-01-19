<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';
require_once 'funciones_pdf.php';

requiereLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("ID inválido");
}

$database = new Database();
$conn = $database->getConnection();

// Obtener datos del documento
$stmt = $conn->prepare("SELECT df.*, c.nombre_razon_social, c.apellido, c.tipo_documento as cliente_tipo_doc,
                              c.documento, c.direccion, c.distrito, c.provincia, c.departamento,
                              c.email, c.celular
                       FROM documentos_facturacion df
                       INNER JOIN clientes c ON df.cliente_id = c.id
                       WHERE df.id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$doc = $stmt->fetch();

if (!$doc) {
    die("Documento no encontrado");
}

// Si es modo DESDE_GUIA, obtener las guías asociadas
$guias = [];
if ($doc['modo_creacion'] === 'DESDE_GUIA' && !empty($doc['guias_asociadas'])) {
    $guias_ids = explode(',', $doc['guias_asociadas']);
    $placeholders = implode(',', array_fill(0, count($guias_ids), '?'));

    $stmt_guias = $conn->prepare("
        SELECT consignatario, pcs, peso_kg
        FROM guias_masivas
        WHERE id IN ($placeholders)
        ORDER BY consignatario ASC
    ");
    $stmt_guias->execute($guias_ids);
    $guias = $stmt_guias->fetchAll();
}

// Usar la función centralizada para generar HTML
$html = generarHTMLDocumento($doc, $guias);

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

// Salida del PDF como descarga
$mpdf->Output($doc['nombre_archivo'], \Mpdf\Output\Destination::DOWNLOAD);
exit();
?>

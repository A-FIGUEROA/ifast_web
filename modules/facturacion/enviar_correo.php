<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

requiereLogin();

header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

try {
    $id_documento = isset($_POST['id_documento']) ? (int)$_POST['id_documento'] : 0;
    $correos_destino = isset($_POST['correos_destino']) ? trim($_POST['correos_destino']) : '';
    $mensaje_personalizado = isset($_POST['mensaje']) ? trim($_POST['mensaje']) : '';
    $incluir_imagen = isset($_POST['incluir_imagen']) && $_POST['incluir_imagen'] === '1';

    if ($id_documento <= 0) {
        echo json_encode(['success' => false, 'mensaje' => 'ID de documento inválido']);
        exit;
    }

    if (empty($correos_destino)) {
        echo json_encode(['success' => false, 'mensaje' => 'Debe proporcionar al menos un correo destino']);
        exit;
    }

    // Obtener datos del documento (incluyendo guias_asociadas y modo_creacion)
    $stmt = $conn->prepare("SELECT df.*, c.nombre_razon_social, c.apellido, c.email
                           FROM documentos_facturacion df
                           INNER JOIN clientes c ON df.cliente_id = c.id
                           WHERE df.id = :id");
    $stmt->bindParam(':id', $id_documento);
    $stmt->execute();
    $doc = $stmt->fetch();

    if (!$doc) {
        echo json_encode(['success' => false, 'mensaje' => 'Documento no encontrado']);
        exit;
    }

    $conn->beginTransaction();

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'ifast.com.pe';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'facturacion@ifast.com.pe';
    $mail->Password   = 'B#mmQYyp@=;kSARF';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('facturacion@ifast.com.pe', 'IFAST - Sistema de Facturación');

    // Agregar destinatarios
    $correos_array = array_map('trim', explode(',', $correos_destino));
    foreach ($correos_array as $correo) {
        if (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress($correo);
        }
    }

    // Nombre del cliente
    $nombre_cliente = $doc['nombre_razon_social'];
    if (!empty($doc['apellido'])) {
        $nombre_cliente .= ' ' . $doc['apellido'];
    }

    // Asunto del correo
    $mail->Subject = 'Documento ' . $doc['tipo_documento'] . ' ' . $doc['numero_documento'] . ' - ' . $nombre_cliente . ' - IFAST';

    // Cuerpo del correo HTML
    $body = '<!DOCTYPE html><html><head><style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #00296b 0%, #00509d 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 20px; }
        .footer { background: #2c3e50; color: white; padding: 15px; text-align: center; font-size: 0.85rem; border-radius: 0 0 10px 10px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #00509d; border-radius: 5px; }
        .info-label { font-weight: bold; color: #00296b; }
        .total-box { background: #00296b; color: white; padding: 15px; margin: 15px 0; border-radius: 5px; text-align: center; font-size: 1.2rem; font-weight: bold; }
    </style></head><body><div class="container">
        <div class="header">
            <h1>📄 ' . htmlspecialchars($doc['tipo_documento']) . '</h1>
            <p>International Courier Service S.A.C.</p>
        </div>
        <div class="content">
            <p>Estimado/a <strong>' . htmlspecialchars($nombre_cliente) . '</strong>,</p>
            <p>Adjuntamos su ' . strtolower($doc['tipo_documento']) . ' <strong>' . htmlspecialchars($doc['numero_documento']) . '</strong> correspondiente a los servicios prestados.</p>
            <div class="info-box">
                <p><span class="info-label">N° Documento:</span> ' . htmlspecialchars($doc['numero_documento']) . '</p>
                <p><span class="info-label">Tipo:</span> ' . htmlspecialchars($doc['tipo_documento']) . '</p>
                <p><span class="info-label">Fecha:</span> ' . date('d/m/Y', strtotime($doc['creado_en'])) . '</p>
            </div>
            <div class="total-box">
                TOTAL: $' . number_format($doc['total'], 2) . ' USD
            </div>';

    if (!empty($mensaje_personalizado)) {
        $body .= '<div class="info-box"><p><span class="info-label">Mensaje:</span></p><p>' . nl2br(htmlspecialchars($mensaje_personalizado)) . '</p></div>';
    }

    $body .= '<p>Por favor, revise el documento adjunto.</p>
            <p>Si tiene alguna consulta, no dude en contactarnos.</p>
        </div>
        <div class="footer">
            <p>INTERNATIONAL COURIER SERVICE S.A.C.</p>
            <p>Tel: (+51) 902 937 040 | Email: info@ifast.com.pe</p>
            <p>MZA. A LOTE. 10 URB. LOS PRODUCTORES - LIMA</p>
        </div>
    </div></body></html>';

    $mail->isHTML(true);
    $mail->Body = $body;

    // Adjuntar el PDF del documento
    $ruta_pdf = __DIR__ . '/../../uploads/facturas/' . $doc['nombre_archivo'];
    if (file_exists($ruta_pdf)) {
        $mail->addAttachment($ruta_pdf, $doc['nombre_archivo']);
    } else {
        echo json_encode(['success' => false, 'mensaje' => 'El archivo PDF no existe. Por favor, regenere el documento.']);
        exit;
    }

    // Adjuntar imagen si existe y el usuario lo solicitó
    if ($incluir_imagen && !empty($doc['imagen_adjunta']) && file_exists($doc['imagen_adjunta'])) {
        $nombre_imagen = basename($doc['imagen_adjunta']);
        $mail->addAttachment($doc['imagen_adjunta'], $nombre_imagen);
    }

    if ($mail->send()) {
        // Actualizar el documento en BD con los datos de envío
        $stmt_update = $conn->prepare("
            UPDATE documentos_facturacion
            SET estado_envio = 'ENVIADO',
                correo_enviado_a = :correos,
                fecha_envio = NOW()
            WHERE id = :id_documento
        ");

        $stmt_update->bindParam(':correos', $correos_destino);
        $stmt_update->bindParam(':id_documento', $id_documento);
        $stmt_update->execute();

        // Actualizar estado_facturacion de guías asociadas a LIQUIDADO
        if (!empty($doc['guias_asociadas']) && $doc['modo_creacion'] === 'DESDE_GUIA') {
            $guias_ids = explode(',', $doc['guias_asociadas']);
            $placeholders = implode(',', array_fill(0, count($guias_ids), '?'));

            $stmt_guias = $conn->prepare("
                UPDATE guias_masivas
                SET estado_facturacion = 'LIQUIDADO'
                WHERE id IN ($placeholders)
            ");
            $stmt_guias->execute($guias_ids);
        }

        $conn->commit();

        echo json_encode(['success' => true, 'mensaje' => 'Correo enviado exitosamente']);
    } else {
        throw new Exception('Error al enviar el correo');
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}
?>

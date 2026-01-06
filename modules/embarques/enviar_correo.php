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
    $id_guia = isset($_POST['id_guia']) ? (int)$_POST['id_guia'] : 0; //CAMBIAR POR CONSIGNATARIO
    $correos_destino = isset($_POST['correos_destino']) ? trim($_POST['correos_destino']) : '';
    $mensaje_personalizado = isset($_POST['mensaje']) ? trim($_POST['mensaje']) : '';

    if ($id_guia <= 0) {
        echo json_encode(['success' => false, 'mensaje' => 'ID de guía inválido']);
        exit;
    }

    if (empty($correos_destino)) {
        echo json_encode(['success' => false, 'mensaje' => 'Debe proporcionar al menos un correo destino']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM guias_embarque WHERE id_guia = :id");
    $stmt->bindParam(':id', $id_guia);
    $stmt->execute();
    $guia = $stmt->fetch();

    if (!$guia) {
        echo json_encode(['success' => false, 'mensaje' => 'Guía no encontrada']);
        exit;
    }

    $conn->beginTransaction();

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ventasifast2@gmail.com';
    $mail->Password   = 'hbld olsj vghe ofvs';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('ventasifast2@gmail.com', 'IFAST - Sistema de Embarques');

    $correos_array = array_map('trim', explode(',', $correos_destino));
    foreach ($correos_array as $correo) {
        if (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress($correo);
        }
    }

    $mail->Subject = $guia['consignatario'] . ' - IFAST';

    $body = '<!DOCTYPE html><html><head><style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #00296b 0%, #00509d 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 20px; }
        .footer { background: #2c3e50; color: white; padding: 15px; text-align: center; font-size: 0.85rem; border-radius: 0 0 10px 10px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #00509d; border-radius: 5px; }
        .info-label { font-weight: bold; color: #00296b; }
    </style></head><body><div class="container">
        <div class="header"><h1>📦 Embarque ' . htmlspecialchars($guia['nro_guia']) . '</h1><p>International Courier Service S.A.C.</p></div>
        <div class="content">
            <p>Estimados Conti Express </p>
            <p>Adjuntamos la documentación de: <strong>' . htmlspecialchars($guia['nombre_completo']) . '</strong>,</p>
            <div class="info-box">
                <p><span class="info-label">N° Guía:</span> ' . htmlspecialchars($guia['nro_guia']) . '</p>
                <p><span class="info-label">Cliente:</span> ' . htmlspecialchars($guia['nombre_completo']) . '</p>
                <p><span class="info-label">Documento:</span> ' . htmlspecialchars($guia['documento']) . '</p>
                <p><span class="info-label">Valor USD:</span> $' . number_format($guia['valor_usd'], 2) . '</p>
                <p><span class="info-label">Fecha:</span> ' . date('d/m/Y', strtotime($guia['fecha_creacion'])) . '</p>
            </div>';

    if (!empty($mensaje_personalizado)) {
        $body .= '<div class="info-box"><p><span class="info-label">Mensaje:</span></p><p>' . nl2br(htmlspecialchars($mensaje_personalizado)) . '</p></div>';
    }

    $body .= '<p>Por favor, revise los archivos adjuntos.</p><p>Si tiene alguna consulta, no dude en contactarnos.</p>
        </div>
        <div class="footer">
            <p>INTERNATIONAL COURIER SERVICE S.A.C.</p>
            <p>Tel: (+51) 902 937 040 | Email: info@ifast.com.pe</p>
            <p>MZA. A LOTE. 10 URB. LOS PRODUCTORES - LIMA</p>
        </div>
    </div></body></html>';

    $mail->isHTML(true);
    $mail->Body = $body;

    $archivos_adjuntos = [];
    if (isset($_POST['archivos']) && is_array($_POST['archivos'])) {
        foreach ($_POST['archivos'] as $archivo_ruta) {
            if (file_exists($archivo_ruta)) {
                $mail->addAttachment($archivo_ruta);
                $archivos_adjuntos[] = $archivo_ruta;
            }
        }
    }

    if (isset($_FILES['archivos_manuales']) && !empty($_FILES['archivos_manuales']['name'][0])) {
        $upload_dir = '../../uploads/embarques/temp/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['archivos_manuales']['tmp_name'] as $key => $tmp_name) {
            if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                $filename = basename($_FILES['archivos_manuales']['name'][$key]);
                $filepath = $upload_dir . time() . '_' . $filename;

                if (move_uploaded_file($tmp_name, $filepath)) {
                    $mail->addAttachment($filepath);
                    $archivos_adjuntos[] = $filepath;
                }
            }
        }
    }

    if ($mail->send()) {
        $stmt_update = $conn->prepare("
            UPDATE guias_embarque
            SET estado_envio = 'ENVIADO',
                correo_enviado_a = :correos,
                fecha_envio = NOW(),
                archivos_adjuntos = :archivos
            WHERE id_guia = :id_guia
        ");

        $stmt_update->bindParam(':correos', $correos_destino);
        $stmt_update->bindValue(':archivos', json_encode($archivos_adjuntos));
        $stmt_update->bindParam(':id_guia', $id_guia);
        $stmt_update->execute();

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

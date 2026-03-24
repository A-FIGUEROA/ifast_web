<?php
// modules/correos_masivos/enviar.php
// Backend AJAX - gestiona guardado, envío masivo e importación de Excel

error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

requiereLogin();
header('Content-Type: application/json');

$database = new Database();
$conn     = $database->getConnection();

$accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';

// ── Importar emails desde Excel/CSV ──────────────────────────────
if ($accion === 'importar_emails') {
    try {
        if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'mensaje' => 'No se recibió archivo']);
            exit;
        }
        $tmp   = $_FILES['archivo_excel']['tmp_name'];
        $orig  = strtolower($_FILES['archivo_excel']['name']);
        $ext   = pathinfo($orig, PATHINFO_EXTENSION);

        $emails = [];

        if ($ext === 'csv') {
            $handle = fopen($tmp, 'r');
            while (($row = fgetcsv($handle)) !== false) {
                foreach ($row as $cell) {
                    $cell = trim($cell);
                    if (filter_var($cell, FILTER_VALIDATE_EMAIL)) $emails[] = $cell;
                }
            }
            fclose($handle);
        } else {
            $reader     = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmp);
            $sheet       = $spreadsheet->getActiveSheet();
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $val = trim((string)$cell->getValue());
                    if (filter_var($val, FILTER_VALIDATE_EMAIL)) $emails[] = $val;
                }
            }
        }

        echo json_encode(['success' => true, 'emails' => $emails]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'mensaje' => 'Error al leer archivo: ' . $e->getMessage()]);
    }
    exit;
}

// ── Eliminar adjunto de BD ────────────────────────────────────────
if ($accion === 'eliminar_adjunto') {
    $id_adj = (int)($_POST['id_adjunto'] ?? 0);
    if ($id_adj <= 0) { echo json_encode(['success'=>false,'mensaje'=>'ID inválido']); exit; }
    try {
        $stmt = $conn->prepare("SELECT ruta FROM campana_adjuntos WHERE id = :id");
        $stmt->bindParam(':id', $id_adj);
        $stmt->execute();
        $adj = $stmt->fetch();
        if ($adj && file_exists('../../' . $adj['ruta'])) @unlink('../../' . $adj['ruta']);
        $conn->prepare("DELETE FROM campana_adjuntos WHERE id = :id")->execute([':id' => $id_adj]);
        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);
    }
    exit;
}

// ── Guardar borrador ──────────────────────────────────────────────
if ($accion === 'guardar_borrador' || $accion === 'enviar') {

    $nombre_campana = trim($_POST['nombre_campana'] ?? '');
    $asunto         = trim($_POST['asunto']         ?? '');
    $cuerpo_html    = $_POST['cuerpo_html']          ?? '';
    $cuerpo_texto   = trim($_POST['cuerpo_texto']   ?? '');
    $id_editar      = (int)($_POST['id_editar']     ?? 0);
    $dests_json     = $_POST['destinatarios_json']   ?? '[]';
    $usuario_id     = $_SESSION['usuario_id'];

    if (empty($nombre_campana) || empty($asunto)) {
        echo json_encode(['success' => false, 'mensaje' => 'Nombre y asunto son obligatorios']);
        exit;
    }

    $destinatarios = json_decode($dests_json, true);
    if (!is_array($destinatarios)) $destinatarios = [];

    // Quitar duplicados
    $emails_vistos = [];
    $destinatarios = array_filter($destinatarios, function($d) use (&$emails_vistos) {
        $e = strtolower(trim($d['email'] ?? ''));
        if (!filter_var($e, FILTER_VALIDATE_EMAIL) || in_array($e, $emails_vistos)) return false;
        $emails_vistos[] = $e;
        return true;
    });
    $destinatarios = array_values($destinatarios);
    $total_dests   = count($destinatarios);

    try {
        $conn->beginTransaction();

        if ($id_editar > 0) {
            // Actualizar borrador existente
            $stmt = $conn->prepare("UPDATE campanas_correo SET
                nombre_campana=:nc, asunto=:as, cuerpo_html=:ch, cuerpo_texto=:ct,
                total_destinatarios=:td
                WHERE id=:id AND estado='BORRADOR'");
            $stmt->execute([':nc'=>$nombre_campana,':as'=>$asunto,
                            ':ch'=>$cuerpo_html,':ct'=>$cuerpo_texto,
                            ':td'=>$total_dests,':id'=>$id_editar]);
            $campana_id = $id_editar;

            // Limpiar destinatarios anteriores y reinsertar
            $conn->prepare("DELETE FROM campana_destinatarios WHERE campana_id=:id")->execute([':id'=>$campana_id]);
        } else {
            // Nueva campaña
            $stmt = $conn->prepare("INSERT INTO campanas_correo
                (nombre_campana, asunto, cuerpo_html, cuerpo_texto,
                 total_destinatarios, enviados, fallidos, estado, creado_por, creado_en)
                VALUES (:nc,:as,:ch,:ct,:td,0,0,'BORRADOR',:cp,NOW())");
            $stmt->execute([':nc'=>$nombre_campana,':as'=>$asunto,
                            ':ch'=>$cuerpo_html,':ct'=>$cuerpo_texto,
                            ':td'=>$total_dests,':cp'=>$usuario_id]);
            $campana_id = $conn->lastInsertId();
        }

        // Insertar destinatarios
        if (!empty($destinatarios)) {
            $stmtD = $conn->prepare("INSERT INTO campana_destinatarios
                (campana_id, email, nombre, estado) VALUES (:cid,:em,:no,'PENDIENTE')");
            foreach ($destinatarios as $d) {
                $stmtD->execute([':cid'=>$campana_id,
                                 ':em'=>strtolower(trim($d['email'])),
                                 ':no'=>trim($d['nombre'] ?? '')]);
            }
        }

        // Procesar nuevos adjuntos
        $upload_base = '../../uploads/correos_masivos/' . $campana_id . '/';
        if (!is_dir($upload_base)) mkdir($upload_base, 0755, true);

        if (!empty($_FILES['adjuntos']['name'][0])) {
            $stmtA = $conn->prepare("INSERT INTO campana_adjuntos
                (campana_id, nombre_archivo, ruta, tipo_mime, tamanio)
                VALUES (:cid,:na,:rt,:tm,:ts)");

            foreach ($_FILES['adjuntos']['tmp_name'] as $k => $tmp) {
                if (empty($tmp) || !is_uploaded_file($tmp)) continue;
                if ($_FILES['adjuntos']['error'][$k] !== UPLOAD_ERR_OK) continue;
                if ($_FILES['adjuntos']['size'][$k] > 10 * 1024 * 1024) continue;

                $orig_name = basename($_FILES['adjuntos']['name'][$k]);
                $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
                $ruta_rel  = 'uploads/correos_masivos/' . $campana_id . '/' . $safe_name;
                $ruta_abs  = '../../' . $ruta_rel;

                if (move_uploaded_file($tmp, $ruta_abs)) {
                    $stmtA->execute([
                        ':cid' => $campana_id,
                        ':na'  => $orig_name,
                        ':rt'  => $ruta_rel,
                        ':tm'  => mime_content_type($ruta_abs),
                        ':ts'  => $_FILES['adjuntos']['size'][$k],
                    ]);
                }
            }
        }

        if ($accion === 'guardar_borrador') {
            $conn->commit();
            echo json_encode(['success' => true, 'id' => $campana_id, 'mensaje' => 'Borrador guardado']);
            exit;
        }

        // ── Iniciar envío ─────────────────────────────────────────
        if ($total_dests === 0) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'mensaje' => 'No hay destinatarios válidos']);
            exit;
        }

        $conn->prepare("UPDATE campanas_correo SET estado='ENVIANDO', enviados=0, fallidos=0
                        WHERE id=:id")->execute([':id'=>$campana_id]);
        $conn->commit();

        // Cargar adjuntos de BD
        $stmtAdj = $conn->prepare("SELECT * FROM campana_adjuntos WHERE campana_id=:id");
        $stmtAdj->execute([':id'=>$campana_id]);
        $adjuntos_bd = $stmtAdj->fetchAll();

        // Obtener destinatarios PENDIENTES
        $stmtPend = $conn->prepare("SELECT * FROM campana_destinatarios
                                    WHERE campana_id=:id AND estado='PENDIENTE'
                                    ORDER BY id");
        $stmtPend->execute([':id'=>$campana_id]);
        $pendientes = $stmtPend->fetchAll();

        $enviados_count  = 0;
        $fallidos_count  = 0;

        foreach ($pendientes as $dest) {
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'ifast.com.pe';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'info@ifast.com.pe';
                $mail->Password   = '*Z*FZg_Z.9h~&zBZ';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom('info@ifast.com.pe', 'IFAST - International Courier');
                $mail->addAddress($dest['email'], $dest['nombre'] ?: '');
                $mail->Subject = $asunto;
                $mail->isHTML(true);

                // Cuerpo HTML con wrapper corporativo
                $nombre_dest = !empty($dest['nombre']) ? htmlspecialchars($dest['nombre']) : 'Cliente';
                $html_body = '<!DOCTYPE html><html><head><meta charset="UTF-8">
                <style>
                    body{font-family:Arial,sans-serif;background:#f5f7fa;margin:0;padding:0;}
                    .wrapper{max-width:600px;margin:20px auto;background:white;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);}
                    .top-bar{background:linear-gradient(135deg,#00296b,#00509d);padding:20px 28px;color:white;text-align:center;}
                    .top-bar img{max-width:140px;margin-bottom:6px;}
                    .top-bar h2{margin:0;font-size:1.1rem;opacity:.9;}
                    .body-content{padding:28px 32px;color:#333;line-height:1.6;}
                    .footer-bar{background:#2c3e50;color:#aaa;padding:14px 28px;text-align:center;font-size:.8rem;}
                    .footer-bar a{color:#FDC500;text-decoration:none;}
                </style></head><body>
                <div class="wrapper">
                    <div class="top-bar">
                        <h2>IFAST · International Courier Service</h2>
                    </div>
                    <div class="body-content">' . $cuerpo_html . '</div>
                    <div class="footer-bar">
                        INTERNATIONAL COURIER SERVICE S.A.C. &nbsp;|&nbsp;
                        <a href="mailto:info@ifast.com.pe">info@ifast.com.pe</a> &nbsp;|&nbsp;
                        Tel: (+51) 902 937 040
                    </div>
                </div></body></html>';

                $mail->Body    = $html_body;
                $mail->AltBody = !empty($cuerpo_texto)
                    ? $cuerpo_texto
                    : strip_tags($cuerpo_html);

                // Adjuntar archivos
                foreach ($adjuntos_bd as $adj) {
                    $ruta_abs = '../../' . $adj['ruta'];
                    if (file_exists($ruta_abs)) {
                        $mail->addAttachment($ruta_abs, $adj['nombre_archivo']);
                    }
                }

                $mail->send();

                $conn->prepare("UPDATE campana_destinatarios
                    SET estado='ENVIADO', enviado_en=NOW(), error_mensaje=NULL
                    WHERE id=:id")->execute([':id'=>$dest['id']]);

                $enviados_count++;

            } catch (\Throwable $e) {
                $err = substr($e->getMessage(), 0, 500);
                $conn->prepare("UPDATE campana_destinatarios
                    SET estado='FALLIDO', error_mensaje=:err
                    WHERE id=:id")->execute([':err'=>$err,':id'=>$dest['id']]);
                $fallidos_count++;
            }

            // Actualizar contadores en la campaña
            $conn->prepare("UPDATE campanas_correo SET enviados=:en, fallidos=:fa WHERE id=:id")
                 ->execute([':en'=>$enviados_count,':fa'=>$fallidos_count,':id'=>$campana_id]);
        }

        // Marcar como completado
        $estado_final = $fallidos_count > 0 && $enviados_count === 0 ? 'ERROR' : 'COMPLETADO';
        $conn->prepare("UPDATE campanas_correo SET estado=:st WHERE id=:id")
             ->execute([':st'=>$estado_final,':id'=>$campana_id]);

        echo json_encode([
            'success'    => true,
            'campana_id' => $campana_id,
            'enviados'   => $enviados_count,
            'fallidos'   => $fallidos_count,
            'mensaje'    => 'Envío completado'
        ]);

    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'mensaje' => 'Acción no reconocida']);

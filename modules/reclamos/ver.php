<?php
// modules/reclamos/ver.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

requierePermiso(['ADMIN']);

$database = new Database();
$conn = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$error_envio = '';

try {
    // Guardar estado / respuesta
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nuevo_estado = isset($_POST['estado']) ? limpiarDatos($_POST['estado']) : '';
        $nueva_respuesta = isset($_POST['respuesta_proveedor']) ? trim($_POST['respuesta_proveedor']) : '';
        $estados_validos = ['pendiente', 'en_proceso', 'atendido'];

        if (in_array($nuevo_estado, $estados_validos)) {
            // Traer la respuesta actual para saber si el texto cambió (evita reenviar el mismo correo)
            $stmt = $conn->prepare("SELECT respuesta_proveedor, consumidor_email, consumidor_nombres, codigo, tipo FROM libro_reclamaciones WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $actual = $stmt->fetch();

            $respuesta_es_nueva = $nueva_respuesta !== '' && $nueva_respuesta !== trim((string)$actual['respuesta_proveedor']);

            if ($respuesta_es_nueva) {
                $stmt = $conn->prepare("UPDATE libro_reclamaciones SET estado = :estado, respuesta_proveedor = :respuesta, fecha_respuesta = NOW() WHERE id = :id");
                $stmt->bindParam(':estado', $nuevo_estado);
                $stmt->bindParam(':respuesta', $nueva_respuesta);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();

                $enviado = enviarRespuestaCliente(
                    $actual['consumidor_email'],
                    $actual['consumidor_nombres'],
                    $actual['codigo'],
                    $actual['tipo'],
                    $nueva_respuesta
                );

                if (!$enviado) {
                    $error_envio = 'La respuesta se guardó, pero no se pudo enviar el correo al cliente. Verifica la conexión SMTP.';
                }
            } else {
                $stmt = $conn->prepare("UPDATE libro_reclamaciones SET estado = :estado WHERE id = :id");
                $stmt->bindParam(':estado', $nuevo_estado);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }

        if ($error_envio === '') {
            header("Location: ver.php?id={$id}&updated=1");
            exit;
        }
    }

    // Obtener reclamo
    $stmt = $conn->prepare("SELECT * FROM libro_reclamaciones WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $r = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    echo '<div style="font-family:monospace;background:#fdecea;color:#c62828;padding:30px;margin:30px;border-radius:10px;white-space:pre-wrap;">'
        . "ERROR DE BASE DE DATOS EN modules/reclamos/ver.php:\n\n"
        . htmlspecialchars($e->getMessage())
        . '</div>';
    exit;
}

if (!$r) {
    header('Location: index.php');
    exit;
}

$estados_colores = [
    'pendiente'  => ['bg' => '#e3f2fd', 'color' => '#1565c0', 'label' => 'Pendiente'],
    'en_proceso' => ['bg' => '#fff3e0', 'color' => '#e65100', 'label' => 'En proceso'],
    'atendido'   => ['bg' => '#e8f5e9', 'color' => '#2e7d32', 'label' => 'Atendido'],
];

$vencido = (strtotime($r['fecha_limite_respuesta']) < strtotime('today')) && $r['estado'] !== 'atendido';

/**
 * Envía la respuesta de IFAST al consumidor por correo (cuenta de Libro de Reclamaciones).
 */
function enviarRespuestaCliente(string $email, string $nombres, string $codigo, string $tipo, string $respuesta): bool {
    $mailConfig = require '../../config/mail_reclamos.php';
    $tipoLabel = $tipo === 'queja' ? 'queja' : 'reclamo';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $mailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailConfig['username'];
        $mail->Password   = $mailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $mailConfig['port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
        $mail->addAddress($email);
        $mail->addReplyTo($mailConfig['from_email'], $mailConfig['from_name']);

        $mail->isHTML(false);
        $mail->Subject = "Respuesta a tu {$tipoLabel} {$codigo} - IFAST Shipping";
        $mail->Body =
            "Hola {$nombres},\n\n" .
            "Te escribimos con la respuesta a tu {$tipoLabel} con código {$codigo}, registrado en el Libro de Reclamaciones de INTERNATIONAL COURIER SERVICE S.A.C. (IFAST Shipping).\n\n" .
            "Respuesta:\n{$respuesta}\n\n" .
            "Si tienes alguna consulta adicional, puedes responder directamente a este correo.\n\n" .
            "Atentamente,\nIFAST Shipping";

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('Error enviando respuesta de reclamo ' . $codigo . ': ' . $mail->ErrorInfo);
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reclamo <?php echo htmlspecialchars($r['codigo']); ?> — IFAST</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:#f5f7fa; }
        .container { display:flex; min-height:100vh; }
        .main-content { flex:1; margin-left:260px; }
        .header { background:white; padding:20px 30px; box-shadow:0 2px 10px rgba(0,0,0,.05); display:flex; justify-content:space-between; align-items:center; }
        .header h1 { font-size:1.5rem; color:#2c3e50; }
        .content { padding:30px; display:grid; grid-template-columns:380px 1fr; gap:25px; align-items:start; }

        .btn { padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all .3s; font-size:.9rem; }
        .btn-back { background:#FDC500; color:#fff; }
        .btn-back:hover { background:#e6b000; }
        .btn-primary { background:linear-gradient(135deg,#00296b,#00509d); color:white; width:100%; justify-content:center; padding:13px; font-size:1rem; }
        .btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,80,157,.3); }

        .panel-left { display:flex; flex-direction:column; gap:20px; }
        .card { background:white; border-radius:15px; padding:25px; box-shadow:0 5px 20px rgba(0,0,0,.08); }

        .avatar { width:70px; height:70px; background:linear-gradient(135deg,#00296b,#00509d); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.8rem; color:white; font-weight:700; margin:0 auto 15px; }
        .r-nombre { text-align:center; }
        .r-nombre h2 { font-size:1.2rem; color:#2c3e50; }
        .r-nombre p { color:#888; font-size:.9rem; margin-top:4px; }

        .badge { display:inline-block; padding:5px 14px; border-radius:20px; font-size:.8rem; font-weight:600; }
        .estado-center { text-align:center; margin-top:12px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
        .badge-vencido { background:#fdecea; color:#c62828; }

        .info-list { list-style:none; }
        .info-list li { display:flex; align-items:flex-start; gap:10px; padding:10px 0; border-bottom:1px solid #f0f0f0; font-size:.9rem; }
        .info-list li:last-child { border-bottom:none; }
        .info-list .icon { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
        .info-list .label { font-size:.75rem; color:#aaa; display:block; }
        .info-list .value { color:#2c3e50; font-weight:500; }

        .card-title { font-size:1rem; font-weight:700; color:#2c3e50; margin-bottom:16px; padding-bottom:10px; border-bottom:2px solid #f5f7fa; }

        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:.82rem; font-weight:600; color:#2c3e50; margin-bottom:6px; }
        .form-group select, .form-group textarea {
            width:100%; padding:10px 14px; border:2px solid #e0e6ed; border-radius:8px; font-size:.9rem;
            background:white; font-family:inherit;
        }
        .form-group select:focus, .form-group textarea:focus { outline:none; border-color:#00509D; }
        .form-group textarea { min-height:140px; resize:vertical; line-height:1.5; }
        .form-hint { font-size:.78rem; color:#999; margin-top:6px; }

        .panel-right .card { display:flex; flex-direction:column; gap:18px; }
        .detalle-block h3 { font-size:.85rem; text-transform:uppercase; letter-spacing:.05em; color:#888; margin-bottom:8px; }
        .detalle-block p { color:#2c3e50; line-height:1.6; font-size:.94rem; white-space:pre-wrap; }
        .detalle-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

        .respuesta-previa { background:#f5f9ff; border:1px solid #d6e6fb; border-radius:10px; padding:14px 16px; }
        .respuesta-previa .meta { font-size:.78rem; color:#5b7ba3; margin-bottom:6px; font-weight:600; }
        .respuesta-previa p { color:#2c3e50; white-space:pre-wrap; }

        .alert-success { background:#d4edda; border-left:4px solid #28a745; color:#155724; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
        .alert-error { background:#fdecea; border-left:4px solid #c62828; color:#c62828; padding:12px 16px; border-radius:8px; margin-bottom:20px; }

        @media(max-width:900px) { .content{grid-template-columns:1fr;} .main-content{margin-left:0;} .detalle-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<main class="main-content">
    <header class="header">
        <h1>Reclamo <?php echo htmlspecialchars($r['codigo']); ?></h1>
        <a href="index.php" class="btn btn-back">← Volver a lista</a>
    </header>

    <div class="content">

        <!-- Panel izquierdo: datos del consumidor + estado -->
        <div class="panel-left">

            <?php if (isset($_GET['updated'])): ?>
            <div class="alert-success">✓ Guardado correctamente<?php echo trim((string)$r['respuesta_proveedor']) !== '' ? ' y respuesta enviada al cliente.' : '.'; ?></div>
            <?php endif; ?>
            <?php if ($error_envio !== ''): ?>
            <div class="alert-error">⚠ <?php echo htmlspecialchars($error_envio); ?></div>
            <?php endif; ?>

            <!-- Perfil -->
            <div class="card">
                <?php
                $partes = explode(' ', trim($r['consumidor_nombres']));
                $iniciales = strtoupper(substr($partes[0] ?? '', 0, 1) . substr($partes[count($partes) - 1] ?? '', 0, 1));
                $cfg = $estados_colores[$r['estado']] ?? ['bg' => '#eee', 'color' => '#333', 'label' => $r['estado']];
                ?>
                <div class="avatar"><?php echo $iniciales; ?></div>
                <div class="r-nombre">
                    <h2><?php echo htmlspecialchars($r['consumidor_nombres']); ?></h2>
                    <p><?php echo $r['tipo'] === 'queja' ? 'Queja' : 'Reclamo'; ?> · <?php echo htmlspecialchars($r['codigo']); ?></p>
                    <div class="estado-center">
                        <span class="badge" style="background:<?php echo $cfg['bg']; ?>;color:<?php echo $cfg['color']; ?>">
                            <?php echo $cfg['label']; ?>
                        </span>
                        <?php if ($vencido): ?>
                            <span class="badge badge-vencido">⚠ Plazo vencido</span>
                        <?php endif; ?>
                    </div>
                </div>

                <ul class="info-list" style="margin-top:20px;">
                    <li>
                        <span class="icon">🪪</span>
                        <div><span class="label">Documento</span><span class="value"><?php echo htmlspecialchars($r['consumidor_tipo_documento'] . ' ' . $r['consumidor_numero_documento']); ?></span></div>
                    </li>
                    <li>
                        <span class="icon">📧</span>
                        <div><span class="label">Correo</span><span class="value"><?php echo htmlspecialchars($r['consumidor_email']); ?></span></div>
                    </li>
                    <li>
                        <span class="icon">📱</span>
                        <div><span class="label">Teléfono</span><span class="value"><?php echo htmlspecialchars($r['consumidor_telefono']); ?></span></div>
                    </li>
                    <li>
                        <span class="icon">🏠</span>
                        <div><span class="label">Domicilio</span><span class="value"><?php echo htmlspecialchars($r['consumidor_domicilio']); ?></span></div>
                    </li>
                    <?php if ((int)$r['es_menor_edad'] === 1): ?>
                    <li>
                        <span class="icon">👪</span>
                        <div>
                            <span class="label">Apoderado (menor de edad)</span>
                            <span class="value"><?php echo htmlspecialchars($r['apoderado_nombres'] . ' — ' . $r['apoderado_documento']); ?></span>
                        </div>
                    </li>
                    <?php endif; ?>
                    <li>
                        <span class="icon">📅</span>
                        <div><span class="label">Fecha de registro</span><span class="value"><?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?></span></div>
                    </li>
                    <li>
                        <span class="icon">⏳</span>
                        <div><span class="label">Plazo máximo de respuesta (15 días hábiles)</span><span class="value" style="<?php echo $vencido ? 'color:#c62828;' : ''; ?>"><?php echo date('d/m/Y', strtotime($r['fecha_limite_respuesta'])); ?></span></div>
                    </li>
                </ul>
            </div>

            <!-- Estado + respuesta -->
            <div class="card">
                <div class="card-title">💬 Estado y respuesta al cliente</div>
                <form method="POST">
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado">
                            <?php foreach ($estados_colores as $key => $c): ?>
                                <option value="<?php echo $key; ?>" <?php echo $r['estado'] === $key ? 'selected' : ''; ?>>
                                    <?php echo $c['label']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Respuesta para el cliente</label>
                        <textarea name="respuesta_proveedor" placeholder="Escribe la respuesta que recibirá el cliente por correo..."><?php echo htmlspecialchars($r['respuesta_proveedor'] ?? ''); ?></textarea>
                        <p class="form-hint">Si cambias este texto y guardas, se le enviará por correo automáticamente a <?php echo htmlspecialchars($r['consumidor_email']); ?>. Si lo dejas igual, solo se actualiza el estado.</p>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </form>
            </div>

        </div>

        <!-- Panel derecho: detalle del caso -->
        <div class="panel-right">
            <div class="card">
                <div class="detalle-grid">
                    <div class="detalle-block">
                        <h3>Referencia (guía / orden)</h3>
                        <p><?php echo htmlspecialchars($r['referencia_pedido'] ?: '—'); ?></p>
                    </div>
                    <div class="detalle-block">
                        <h3>Monto reclamado</h3>
                        <p><?php echo $r['monto_reclamado'] !== null ? 'S/ ' . number_format((float)$r['monto_reclamado'], 2) : '—'; ?></p>
                    </div>
                </div>

                <div class="detalle-block">
                    <h3>Detalle del <?php echo $r['tipo'] === 'queja' ? 'queja' : 'reclamo'; ?></h3>
                    <p><?php echo htmlspecialchars($r['detalle']); ?></p>
                </div>

                <div class="detalle-block">
                    <h3>Pedido concreto del consumidor</h3>
                    <p><?php echo htmlspecialchars($r['pedido_consumidor']); ?></p>
                </div>

                <?php if (trim((string)$r['respuesta_proveedor']) !== ''): ?>
                <div class="detalle-block">
                    <h3>Última respuesta enviada</h3>
                    <div class="respuesta-previa">
                        <div class="meta">
                            <?php echo $r['fecha_respuesta'] ? date('d/m/Y H:i', strtotime($r['fecha_respuesta'])) : ''; ?>
                        </div>
                        <p><?php echo htmlspecialchars($r['respuesta_proveedor']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</body>
</html>

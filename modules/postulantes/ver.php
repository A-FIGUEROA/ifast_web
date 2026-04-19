<?php
// modules/postulantes/ver.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN']);

$database = new Database();
$conn = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Cambio de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['estado'])) {
    $nuevo_estado = limpiarDatos($_POST['estado']);
    $estados_validos = ['NUEVO', 'REVISADO', 'CONTACTADO', 'DESCARTADO'];
    if (in_array($nuevo_estado, $estados_validos)) {
        $stmt = $conn->prepare("UPDATE postulantes SET estado = :estado WHERE id = :id");
        $stmt->bindParam(':estado', $nuevo_estado);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
    header("Location: ver.php?id={$id}&updated=1");
    exit;
}

// Obtener postulante
$stmt = $conn->prepare("SELECT * FROM postulantes WHERE id = :id LIMIT 1");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$p = $stmt->fetch();

if (!$p) {
    header('Location: index.php');
    exit;
}

$estados_colores = [
    'NUEVO'      => ['bg' => '#e3f2fd', 'color' => '#1565c0', 'label' => 'Nuevo'],
    'REVISADO'   => ['bg' => '#fff3e0', 'color' => '#e65100', 'label' => 'Revisado'],
    'CONTACTADO' => ['bg' => '#e8f5e9', 'color' => '#2e7d32', 'label' => 'Contactado'],
    'DESCARTADO' => ['bg' => '#fce4ec', 'color' => '#c62828', 'label' => 'Descartado'],
];

$cv_path = '../../uploads/postulantes/' . $p['cv_archivo'];
$cv_url  = '/ifast_web/uploads/postulantes/' . rawurlencode($p['cv_archivo']);
$es_pdf  = strtolower($p['cv_tipo']) === 'pdf';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Postulante — <?php echo htmlspecialchars($p['nombres'] . ' ' . $p['apellidos']); ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:#f5f7fa; }
        .container { display:flex; min-height:100vh; }
        .main-content { flex:1; margin-left:260px; }
        .header { background:white; padding:20px 30px; box-shadow:0 2px 10px rgba(0,0,0,.05); display:flex; justify-content:space-between; align-items:center; }
        .header h1 { font-size:1.6rem; color:#2c3e50; }
        .content { padding:30px; display:grid; grid-template-columns:380px 1fr; gap:25px; align-items:start; }

        .btn { padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all .3s; font-size:.9rem; }
        .btn-back { background:#FDC500; color:#fff; }
        .btn-back:hover { background:#e6b000; }
        .btn-primary { background:linear-gradient(135deg,#00296b,#00509d); color:white; }
        .btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,80,157,.3); }
        .btn-download { background:linear-gradient(135deg,#27ae60,#2ecc71); color:white; width:100%; justify-content:center; padding:13px; font-size:1rem; border-radius:10px; margin-bottom:10px; }
        .btn-download:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(39,174,96,.3); }

        /* Panel izquierdo */
        .panel-left { display:flex; flex-direction:column; gap:20px; }

        .card { background:white; border-radius:15px; padding:25px; box-shadow:0 5px 20px rgba(0,0,0,.08); }

        .avatar { width:70px; height:70px; background:linear-gradient(135deg,#00296b,#00509d); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.8rem; color:white; font-weight:700; margin:0 auto 15px; }

        .postulante-nombre { text-align:center; }
        .postulante-nombre h2 { font-size:1.3rem; color:#2c3e50; }
        .postulante-nombre p { color:#888; font-size:.9rem; margin-top:4px; }

        .badge { display:inline-block; padding:5px 14px; border-radius:20px; font-size:.8rem; font-weight:600; }
        .estado-center { text-align:center; margin-top:12px; }

        .info-list { list-style:none; }
        .info-list li { display:flex; align-items:flex-start; gap:10px; padding:10px 0; border-bottom:1px solid #f0f0f0; font-size:.9rem; }
        .info-list li:last-child { border-bottom:none; }
        .info-list .icon { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
        .info-list .label { font-size:.75rem; color:#aaa; display:block; }
        .info-list .value { color:#2c3e50; font-weight:500; }

        .card-title { font-size:1rem; font-weight:700; color:#2c3e50; margin-bottom:16px; padding-bottom:10px; border-bottom:2px solid #f5f7fa; }

        /* Selector de estado */
        .estado-form { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .estado-form select { flex:1; padding:10px 14px; border:2px solid #e0e6ed; border-radius:8px; font-size:.9rem; background:white; cursor:pointer; }
        .estado-form select:focus { outline:none; border-color:#00509D; }

        /* Panel derecho (CV) */
        .panel-right .card { height:100%; }
        .cv-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding-bottom:12px; border-bottom:2px solid #f5f7fa; flex-wrap:wrap; gap:10px; }
        .cv-header h3 { font-size:1rem; font-weight:700; color:#2c3e50; }
        .cv-meta { font-size:.82rem; color:#888; margin-top:3px; }

        .pdf-viewer { width:100%; height:700px; border:1px solid #e0e6ed; border-radius:10px; }

        .word-preview { text-align:center; padding:60px 30px; }
        .word-preview .doc-icon { font-size:4rem; margin-bottom:16px; }
        .word-preview p { color:#666; margin-bottom:6px; font-size:.95rem; }
        .word-preview .file-name { font-weight:600; color:#2c3e50; font-size:1rem; margin-bottom:25px; word-break:break-all; }

        .alert-success { background:#d4edda; border-left:4px solid #28a745; color:#155724; padding:12px 16px; border-radius:8px; margin-bottom:20px; }

        @media(max-width:900px) { .content{grid-template-columns:1fr;} .main-content{margin-left:0;} }
    </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<main class="main-content">
    <header class="header">
        <h1>Detalle del Postulante</h1>
        <a href="index.php" class="btn btn-back">← Volver a lista</a>
    </header>

    <div class="content">

        <!-- Panel izquierdo: datos -->
        <div class="panel-left">

            <?php if (isset($_GET['updated'])): ?>
            <div class="alert-success">✓ Estado actualizado correctamente.</div>
            <?php endif; ?>

            <!-- Perfil -->
            <div class="card">
                <?php
                $iniciales = strtoupper(substr($p['nombres'], 0, 1) . substr($p['apellidos'], 0, 1));
                $cfg = $estados_colores[$p['estado']] ?? ['bg' => '#eee', 'color' => '#333', 'label' => $p['estado']];
                ?>
                <div class="avatar"><?php echo $iniciales; ?></div>
                <div class="postulante-nombre">
                    <h2><?php echo htmlspecialchars($p['nombres'] . ' ' . $p['apellidos']); ?></h2>
                    <p>Postulante #<?php echo $p['id']; ?></p>
                    <div class="estado-center">
                        <span class="badge" style="background:<?php echo $cfg['bg']; ?>;color:<?php echo $cfg['color']; ?>">
                            <?php echo $cfg['label']; ?>
                        </span>
                    </div>
                </div>

                <ul class="info-list" style="margin-top:20px;">
                    <li>
                        <span class="icon">🪪</span>
                        <div><span class="label">DNI</span><span class="value"><?php echo htmlspecialchars($p['dni']); ?></span></div>
                    </li>
                    <li>
                        <span class="icon">📧</span>
                        <div><span class="label">Correo</span><span class="value"><?php echo htmlspecialchars($p['correo']); ?></span></div>
                    </li>
                    <li>
                        <span class="icon">📱</span>
                        <div><span class="label">Celular</span><span class="value"><?php echo htmlspecialchars($p['celular']); ?></span></div>
                    </li>
                    <li>
                        <span class="icon">📅</span>
                        <div>
                            <span class="label">Fecha de postulación</span>
                            <span class="value"><?php echo date('d/m/Y H:i', strtotime($p['fecha_postulacion'])); ?></span>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Cambiar estado -->
            <div class="card">
                <div class="card-title">📋 Cambiar Estado</div>
                <form method="POST" class="estado-form">
                    <select name="estado">
                        <?php foreach ($estados_colores as $key => $c): ?>
                            <option value="<?php echo $key; ?>" <?php echo $p['estado'] === $key ? 'selected' : ''; ?>>
                                <?php echo $c['label']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </form>
            </div>

            <!-- Descargar CV -->
            <div class="card">
                <div class="card-title">📥 Descargar CV</div>
                <a href="descargar_cv.php?id=<?php echo $p['id']; ?>" class="btn btn-download">
                    ⬇ Descargar <?php echo strtoupper($p['cv_tipo']); ?>
                </a>
                <p style="font-size:.8rem;color:#aaa;text-align:center;">
                    <?php echo htmlspecialchars($p['cv_nombre_orig']); ?>
                </p>
            </div>

        </div>

        <!-- Panel derecho: visor CV -->
        <div class="panel-right">
            <div class="card">
                <div class="cv-header">
                    <div>
                        <h3>CV del Postulante</h3>
                        <div class="cv-meta"><?php echo htmlspecialchars($p['cv_nombre_orig']); ?> &nbsp;·&nbsp; <?php echo strtoupper($p['cv_tipo']); ?></div>
                    </div>
                </div>

                <?php if ($es_pdf && file_exists($cv_path)): ?>
                    <iframe src="<?php echo $cv_url; ?>" class="pdf-viewer" title="CV PDF"></iframe>
                <?php elseif (file_exists($cv_path)): ?>
                    <div class="word-preview">
                        <div class="doc-icon">📝</div>
                        <p>Vista previa no disponible para archivos Word.</p>
                        <div class="file-name"><?php echo htmlspecialchars($p['cv_nombre_orig']); ?></div>
                        <a href="descargar_cv.php?id=<?php echo $p['id']; ?>" class="btn btn-download" style="width:auto;display:inline-flex;">
                            ⬇ Descargar para visualizar
                        </a>
                    </div>
                <?php else: ?>
                    <div class="word-preview">
                        <div class="doc-icon">⚠️</div>
                        <p style="color:#e74c3c;">El archivo CV no fue encontrado en el servidor.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</body>
</html>

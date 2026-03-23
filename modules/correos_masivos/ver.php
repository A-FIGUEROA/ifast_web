<?php
// modules/correos_masivos/ver.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();

$database = new Database();
$conn     = $database->getConnection();

$tipo_usuario   = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $conn->prepare("SELECT cc.*, CONCAT(u.nombre,' ',u.apellido) AS creador
    FROM campanas_correo cc
    LEFT JOIN usuarios u ON cc.creado_por = u.id
    WHERE cc.id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$campana = $stmt->fetch();
if (!$campana) { header('Location: index.php'); exit; }

// Filtro de destinatarios
$filtro_estado = isset($_GET['filtro']) ? $_GET['filtro'] : 'TODOS';
$buscar_email  = isset($_GET['buscar']) ? limpiarDatos($_GET['buscar']) : '';

$pagina              = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 20;

$where_parts = ["campana_id = :id"];
$params      = [':id' => $id];
if ($filtro_estado !== 'TODOS') {
    $where_parts[] = "estado = :est";
    $params[':est'] = $filtro_estado;
}
if (!empty($buscar_email)) {
    $where_parts[] = "email LIKE :bus";
    $params[':bus'] = "%{$buscar_email}%";
}
$where_sql = implode(' AND ', $where_parts);

$stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM campana_destinatarios WHERE {$where_sql}");
foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
$stmtCount->execute();
$total_dests = $stmtCount->fetch()['total'];
$paginacion  = paginar($total_dests, $registros_por_pagina, $pagina);

$stmtDests = $conn->prepare("SELECT * FROM campana_destinatarios
    WHERE {$where_sql} ORDER BY id ASC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) $stmtDests->bindValue($k, $v);
$stmtDests->bindValue(':lim', $paginacion['registros_por_pagina'], PDO::PARAM_INT);
$stmtDests->bindValue(':off', $paginacion['offset'],               PDO::PARAM_INT);
$stmtDests->execute();
$dests = $stmtDests->fetchAll();

// Adjuntos
$stmtAdj = $conn->prepare("SELECT * FROM campana_adjuntos WHERE campana_id = :id");
$stmtAdj->bindParam(':id', $id);
$stmtAdj->execute();
$adjuntos = $stmtAdj->fetchAll();

$badge_map = [
    'BORRADOR'   => ['badge-borrador',   '📝 Borrador'],
    'ENVIANDO'   => ['badge-enviando',   '📤 Enviando'],
    'COMPLETADO' => ['badge-completado', '✅ Completado'],
    'ERROR'      => ['badge-error',      '❌ Error'],
];
[$badge_class, $badge_label] = $badge_map[$campana['estado']] ?? ['badge-borrador','—'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Campaña - iFast</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:#f5f7fa; }
        .container { display:flex; min-height:100vh; }
        .main-content { flex:1; margin-left:260px; }
        .header { background:white; padding:20px 30px; box-shadow:0 2px 10px rgba(0,0,0,0.05);
                  display:flex; justify-content:space-between; align-items:center; }
        .header h1 { font-size:1.8rem; color:#2c3e50; font-weight:600; }
        .header-right { display:flex; align-items:center; gap:20px; }
        .user-info { display:flex; align-items:center; gap:15px; }
        .user-avatar { width:45px; height:45px; border-radius:50%;
                       background:linear-gradient(135deg,#FDC500 0%,#FFD500 100%);
                       display:flex; align-items:center; justify-content:center;
                       color:white; font-weight:600; font-size:1.1rem; }
        .user-details { text-align:right; }
        .user-name { font-weight:600; color:#2c3e50; font-size:.95rem; }
        .user-role { font-size:.8rem; color:#7f8c8d; display:inline-block;
                     padding:2px 8px; background:#ecf0f1; border-radius:10px; margin-top:2px; }
        .btn-logout { padding:10px 20px; background:#e74c3c; color:white; border:none;
                      border-radius:8px; cursor:pointer; font-weight:600; transition:all .3s; }
        .btn-logout:hover { background:#c0392b; transform:translateY(-2px); }
        .content { padding:30px; }
        .card { background:white; border-radius:15px; padding:25px;
                box-shadow:0 5px 20px rgba(0,0,0,.08); margin-bottom:24px; }
        .card-title { font-size:1.2rem; font-weight:700; color:#2c3e50;
                      margin-bottom:18px; padding-bottom:12px; border-bottom:2px solid #f5f7fa; }
        .btn { padding:10px 20px; border:none; border-radius:8px; cursor:pointer;
               font-weight:600; text-decoration:none; display:inline-block; transition:all .3s;
               font-size:.9rem; }
        .btn-back    { background:#FDC500; color:white; }
        .btn-primary { background:linear-gradient(135deg,#00296B,#00509D); color:white; }
        .btn-success { background:#27ae60; color:white; }
        .btn-danger  { background:#e74c3c; color:white; }
        .btn-warning { background:#f39c12; color:white; }
        .btn-small   { padding:6px 12px; font-size:.8rem; }
        .stats-row { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:8px; }
        .stat-card { flex:1; min-width:130px; background:#f8f9fa; border-radius:10px;
                     padding:16px; text-align:center; border-left:4px solid; }
        .stat-num  { font-size:2rem; font-weight:700; color:#2c3e50; }
        .stat-label { font-size:.82rem; color:#7f8c8d; margin-top:3px; }
        .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .meta-item label { font-weight:600; color:#2c3e50; font-size:.85rem; display:block; margin-bottom:3px; }
        .meta-item span  { color:#555; font-size:.9rem; }
        .badge { display:inline-block; padding:4px 12px; border-radius:15px; font-size:.8rem; font-weight:600; }
        .badge-borrador   { background:#fff3cd; color:#856404; }
        .badge-enviando   { background:#cfe2ff; color:#084298; }
        .badge-completado { background:#d1e7dd; color:#0a3622; }
        .badge-error      { background:#f8d7da; color:#842029; }
        .badge-pendiente  { background:#e2e3e5; color:#383d41; }
        .badge-enviado    { background:#d1e7dd; color:#0a3622; }
        .badge-fallido    { background:#f8d7da; color:#842029; }
        .filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; align-items:center; }
        .filtro-btn { padding:7px 16px; border:2px solid #e0e0e0; border-radius:20px;
                      text-decoration:none; color:#555; font-size:.85rem; font-weight:600;
                      transition:all .2s; background:white; }
        .filtro-btn.active { border-color:#00509D; color:#00509D; background:#e8f4fd; }
        .search-box { padding:9px 14px; border:2px solid #e0e0e0; border-radius:8px;
                      font-size:.9rem; width:230px; }
        .search-box:focus { outline:none; border-color:#00509D; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fa; padding:12px 14px; text-align:left; font-weight:600;
             color:#2c3e50; font-size:.82rem; text-transform:uppercase; }
        td { padding:12px 14px; border-bottom:1px solid #f0f0f0; color:#555; font-size:.88rem; }
        tr:hover td { background:#fafafa; }
        .empty-state { text-align:center; padding:40px; color:#7f8c8d; }
        .pagination { display:flex; justify-content:center; align-items:center; gap:12px; margin-top:24px; }
        .pagination-btn { padding:9px 16px; border:2px solid #00509D; border-radius:8px;
                          text-decoration:none; color:#00509D; font-weight:600; transition:all .2s; background:white; }
        .pagination-btn:hover:not(.disabled) { background:#00509D; color:white; }
        .pagination-btn.disabled { opacity:.3; cursor:not-allowed; pointer-events:none; }
        .pagination-info { padding:7px 14px; background:#f8f9fa; border-radius:8px;
                           color:#2c3e50; font-weight:600; font-size:.88rem; }
        .adj-item { display:flex; align-items:center; gap:10px; padding:10px 14px;
                    background:#f8f9fa; border-radius:8px; margin-bottom:8px; }
        .adj-icon { font-size:1.4rem; }
        .adj-nombre { font-weight:600; color:#2c3e50; font-size:.9rem; }
        .adj-size   { color:#7f8c8d; font-size:.8rem; }
        .progress-bar-wrap { background:#e0e0e0; border-radius:20px; height:14px;
                             overflow:hidden; margin:10px 0; }
        .progress-bar { height:100%; background:linear-gradient(90deg,#00296B,#00509D);
                        border-radius:20px; transition:width .4s; }
        @media(max-width:768px){
            .main-content { margin-left:0; }
            .meta-grid { grid-template-columns:1fr; }
            .stats-row { flex-direction:column; }
        }
    </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php require_once '../../includes/header.php'; ?>
        </header>

        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;flex-wrap:wrap;gap:10px;">
                <div>
                    <a href="index.php" class="btn btn-back">← Volver</a>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php if ($campana['estado'] === 'COMPLETADO' || $campana['estado'] === 'ERROR'): ?>
                    <?php if ($campana['fallidos'] > 0): ?>
                    <button onclick="reenviarFallidos()" class="btn btn-warning">🔁 Reenviar fallidos (<?php echo $campana['fallidos']; ?>)</button>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($campana['estado'] === 'BORRADOR'): ?>
                    <a href="crear.php?id=<?php echo $id; ?>" class="btn btn-primary">✏️ Editar borrador</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resumen de la campaña -->
            <div class="card">
                <div class="card-title">📧 <?php echo htmlspecialchars($campana['nombre_campana']); ?></div>
                <div class="meta-grid" style="margin-bottom:20px;">
                    <div class="meta-item">
                        <label>Asunto</label>
                        <span><?php echo htmlspecialchars($campana['asunto']); ?></span>
                    </div>
                    <div class="meta-item">
                        <label>Estado</label>
                        <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                    </div>
                    <div class="meta-item">
                        <label>Creado por</label>
                        <span><?php echo htmlspecialchars($campana['creador'] ?? '—'); ?></span>
                    </div>
                    <div class="meta-item">
                        <label>Fecha de creación</label>
                        <span><?php echo date('d/m/Y H:i', strtotime($campana['creado_en'])); ?></span>
                    </div>
                </div>

                <?php if ($campana['total_destinatarios'] > 0): ?>
                <?php
                $pct_env = round(($campana['enviados'] / $campana['total_destinatarios']) * 100);
                ?>
                <div class="progress-bar-wrap">
                    <div class="progress-bar" style="width:<?php echo $pct_env; ?>%"></div>
                </div>
                <p style="font-size:.82rem;color:#7f8c8d;text-align:right;"><?php echo $pct_env; ?>% completado</p>
                <?php endif; ?>

                <div class="stats-row" style="margin-top:16px;">
                    <div class="stat-card" style="border-color:#00509D;">
                        <div class="stat-num"><?php echo $campana['total_destinatarios']; ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                    <div class="stat-card" style="border-color:#27ae60;">
                        <div class="stat-num"><?php echo $campana['enviados']; ?></div>
                        <div class="stat-label">✅ Enviados</div>
                    </div>
                    <div class="stat-card" style="border-color:#e74c3c;">
                        <div class="stat-num"><?php echo $campana['fallidos']; ?></div>
                        <div class="stat-label">❌ Fallidos</div>
                    </div>
                    <div class="stat-card" style="border-color:#95a5a6;">
                        <div class="stat-num"><?php echo max(0,$campana['total_destinatarios']-$campana['enviados']-$campana['fallidos']); ?></div>
                        <div class="stat-label">⏳ Pendientes</div>
                    </div>
                </div>
            </div>

            <!-- Adjuntos -->
            <?php if (!empty($adjuntos)): ?>
            <div class="card">
                <div class="card-title">📎 Archivos Adjuntos (<?php echo count($adjuntos); ?>)</div>
                <?php
                $iconos_ext = ['pdf'=>'📄','doc'=>'📝','docx'=>'📝','xls'=>'📊','xlsx'=>'📊',
                               'jpg'=>'🖼','jpeg'=>'🖼','png'=>'🖼','gif'=>'🖼'];
                foreach ($adjuntos as $adj):
                    $ext = strtolower(pathinfo($adj['nombre_archivo'], PATHINFO_EXTENSION));
                    $icono = $iconos_ext[$ext] ?? '📎';
                ?>
                <div class="adj-item">
                    <span class="adj-icon"><?php echo $icono; ?></span>
                    <div>
                        <div class="adj-nombre"><?php echo htmlspecialchars($adj['nombre_archivo']); ?></div>
                        <div class="adj-size"><?php echo round($adj['tamanio']/1024, 1); ?> KB — <?php echo $adj['tipo_mime']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Tabla de destinatarios -->
            <div class="card">
                <div class="card-title">👥 Destinatarios</div>

                <div class="filters">
                    <?php
                    $filtros = ['TODOS'=>'Todos','ENVIADO'=>'✅ Enviados','PENDIENTE'=>'⏳ Pendientes','FALLIDO'=>'❌ Fallidos'];
                    foreach ($filtros as $val => $label):
                        $url = "ver.php?id={$id}&filtro={$val}" . (!empty($buscar_email)?"&buscar=".urlencode($buscar_email):'');
                    ?>
                    <a href="<?php echo $url; ?>" class="filtro-btn <?php echo $filtro_estado===$val?'active':''; ?>">
                        <?php echo $label; ?>
                    </a>
                    <?php endforeach; ?>

                    <form method="GET" style="display:flex;gap:8px;margin-left:auto;">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <input type="hidden" name="filtro" value="<?php echo $filtro_estado; ?>">
                        <input type="text" name="buscar" class="search-box"
                            placeholder="Buscar email..." value="<?php echo htmlspecialchars($buscar_email); ?>">
                        <button type="submit" class="btn btn-primary btn-small">Buscar</button>
                        <?php if (!empty($buscar_email)): ?>
                        <a href="ver.php?id=<?php echo $id; ?>&filtro=<?php echo $filtro_estado; ?>"
                           class="btn btn-small" style="background:#95a5a6;color:white;">✕</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (empty($dests)): ?>
                <div class="empty-state">Sin destinatarios para este filtro.</div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Email</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Enviado en</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dests as $i => $d): ?>
                        <?php
                        $db = ['PENDIENTE'=>'badge-pendiente','ENVIADO'=>'badge-enviado','FALLIDO'=>'badge-fallido'][$d['estado']] ?? 'badge-pendiente';
                        ?>
                        <tr>
                            <td><?php echo $paginacion['offset'] + $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($d['email']); ?></td>
                            <td><?php echo htmlspecialchars($d['nombre'] ?: '—'); ?></td>
                            <td><span class="badge <?php echo $db; ?>"><?php echo $d['estado']; ?></span></td>
                            <td><?php echo $d['enviado_en'] ? date('d/m/Y H:i:s', strtotime($d['enviado_en'])) : '—'; ?></td>
                            <td style="color:#e74c3c;font-size:.8rem;"><?php echo $d['error_mensaje'] ? htmlspecialchars(substr($d['error_mensaje'],0,80)) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($paginacion['total_paginas'] > 1): ?>
                <div class="pagination">
                    <a href="?id=<?php echo $id; ?>&filtro=<?php echo $filtro_estado; ?>&buscar=<?php echo urlencode($buscar_email); ?>&pagina=<?php echo max(1,$pagina-1); ?>"
                       class="pagination-btn <?php echo $pagina<=1?'disabled':''; ?>">← Anterior</a>
                    <span class="pagination-info">Página <strong><?php echo $pagina; ?></strong> de <strong><?php echo $paginacion['total_paginas']; ?></strong></span>
                    <a href="?id=<?php echo $id; ?>&filtro=<?php echo $filtro_estado; ?>&buscar=<?php echo urlencode($buscar_email); ?>&pagina=<?php echo min($paginacion['total_paginas'],$pagina+1); ?>"
                       class="pagination-btn <?php echo $pagina>=$paginacion['total_paginas']?'disabled':''; ?>">Siguiente →</a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function reenviarFallidos() {
        if (!confirm('¿Reintentar el envío solo a los destinatarios fallidos?')) return;
        fetch('enviar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'accion=reenviar_fallidos&campana_id=<?php echo $id; ?>'
        })
        .then(r => r.json())
        .then(d => {
            alert(d.mensaje || (d.success ? 'Reenvío iniciado' : 'Error'));
            if (d.success) location.reload();
        });
    }
    </script>
</body>
</html>

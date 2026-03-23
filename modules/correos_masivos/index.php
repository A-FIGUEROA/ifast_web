<?php
// modules/correos_masivos/index.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();

$tipo_usuario   = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

// Paginación
$pagina             = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;
$buscar             = isset($_GET['buscar']) ? limpiarDatos($_GET['buscar']) : '';

$where = '';
$params = [];
if (!empty($buscar)) {
    $where = " WHERE nombre_campana LIKE :buscar OR asunto LIKE :buscar2";
    $params[':buscar']  = "%{$buscar}%";
    $params[':buscar2'] = "%{$buscar}%";
}

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM campanas_correo" . $where);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$total = $stmt->fetch()['total'];

$paginacion = paginar($total, $registros_por_pagina, $pagina);

$stmt = $conn->prepare("SELECT cc.*, CONCAT(u.nombre,' ',u.apellido) AS creador
    FROM campanas_correo cc
    LEFT JOIN usuarios u ON cc.creado_por = u.id
    {$where}
    ORDER BY cc.creado_en DESC
    LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $paginacion['registros_por_pagina'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $paginacion['offset'],               PDO::PARAM_INT);
$stmt->execute();
$campanas = $stmt->fetchAll();

$mensaje = '';
$tipo_alerta = '';
if (isset($_GET['eliminado']) && $_GET['eliminado'] == 1) {
    $mensaje     = 'Campaña eliminada correctamente.';
    $tipo_alerta = 'success';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correos Masivos - iFast</title>
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
        .user-name  { font-weight:600; color:#2c3e50; font-size:.95rem; }
        .user-role  { font-size:.8rem; color:#7f8c8d; display:inline-block;
                      padding:2px 8px; background:#ecf0f1; border-radius:10px; margin-top:2px; }
        .btn-logout { padding:10px 20px; background:#e74c3c; color:white; border:none;
                      border-radius:8px; cursor:pointer; font-size:.9rem; font-weight:600;
                      transition:all .3s; }
        .btn-logout:hover { background:#c0392b; transform:translateY(-2px); }
        .content { padding:30px; }
        .card { background:white; border-radius:15px; padding:25px;
                box-shadow:0 5px 20px rgba(0,0,0,.08); }
        .card-header { display:flex; justify-content:space-between; align-items:center;
                       margin-bottom:25px; padding-bottom:15px;
                       border-bottom:2px solid #f5f7fa; flex-wrap:wrap; gap:15px; }
        .card-header h2 { font-size:1.5rem; color:#2c3e50; }
        .search-container { display:flex; gap:10px; }
        .search-box { padding:10px 15px; border:2px solid #e0e0e0; border-radius:8px;
                      width:280px; font-size:.95rem; }
        .search-box:focus { outline:none; border-color:#00509D; }
        .btn { padding:12px 24px; border:none; border-radius:8px; cursor:pointer;
               font-weight:600; text-decoration:none; display:inline-block; transition:all .3s;
               font-size:.9rem; }
        .btn-primary { background:linear-gradient(135deg,#00296B 0%,#00509D 100%); color:white; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 5px 15px rgba(0,41,107,.3); }
        .btn-search { background:#3498db; color:white; padding:10px 20px; }
        .btn-danger  { background:#e74c3c; color:white; }
        .btn-info    { background:#17a2b8; color:white; }
        .btn-warning { background:#FDC500; color:white; }
        .btn-small   { padding:7px 12px; font-size:.8rem; margin-right:4px; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fa; padding:14px 15px; text-align:left; font-weight:600;
             color:#2c3e50; font-size:.82rem; text-transform:uppercase; }
        td { padding:14px 15px; border-bottom:1px solid #f0f0f0; color:#555; font-size:.9rem; }
        tr:hover td { background:#f8f9fa; }
        .badge { display:inline-block; padding:4px 10px; border-radius:15px;
                 font-size:.75rem; font-weight:600; }
        .badge-borrador   { background:#fff3cd; color:#856404; }
        .badge-enviando   { background:#cfe2ff; color:#084298; }
        .badge-completado { background:#d1e7dd; color:#0a3622; }
        .badge-error      { background:#f8d7da; color:#842029; }
        .pagination { display:flex; justify-content:center; align-items:center;
                      gap:15px; margin-top:30px; }
        .pagination-btn { padding:10px 18px; border:2px solid #00509D; border-radius:8px;
                          text-decoration:none; color:#00509D; font-weight:600; transition:all .3s; background:white; }
        .pagination-btn:hover:not(.disabled) { background:#00509D; color:white; }
        .pagination-btn.disabled { opacity:.3; cursor:not-allowed; border-color:#ccc; color:#ccc; pointer-events:none; }
        .pagination-info { padding:8px 18px; background:#f8f9fa; border-radius:8px;
                           color:#2c3e50; font-weight:600; font-size:.9rem; }
        .pagination-info strong { color:#00509D; }
        .alert { padding:14px; border-radius:10px; margin-bottom:20px; }
        .alert-success { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
        .alert-error   { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
        .empty-state { text-align:center; padding:60px 20px; color:#7f8c8d; }
        .empty-state .icon { font-size:4rem; margin-bottom:15px; opacity:.5; }
        .stats-row { display:flex; gap:20px; margin-bottom:25px; flex-wrap:wrap; }
        .stat-card { flex:1; min-width:140px; background:white; border-radius:12px; padding:18px 20px;
                     box-shadow:0 3px 10px rgba(0,0,0,.07); border-left:4px solid; }
        .stat-card.azul  { border-color:#00509D; }
        .stat-card.verde { border-color:#27ae60; }
        .stat-card.rojo  { border-color:#e74c3c; }
        .stat-card.gris  { border-color:#95a5a6; }
        .stat-num  { font-size:1.9rem; font-weight:700; color:#2c3e50; }
        .stat-label { font-size:.82rem; color:#7f8c8d; margin-top:2px; }
        @media(max-width:768px){
            .main-content { margin-left:0; }
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
            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_alerta; ?>"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <?php
            // Estadísticas rápidas
            $stmtStats = $conn->query("SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN estado='COMPLETADO' THEN 1 ELSE 0 END) AS completados,
                SUM(CASE WHEN estado='ENVIANDO'   THEN 1 ELSE 0 END) AS enviando,
                SUM(CASE WHEN estado='BORRADOR'   THEN 1 ELSE 0 END) AS borradores
                FROM campanas_correo");
            $stats = $stmtStats->fetch();
            ?>
            <div class="stats-row">
                <div class="stat-card azul">
                    <div class="stat-num"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total campañas</div>
                </div>
                <div class="stat-card verde">
                    <div class="stat-num"><?php echo $stats['completados']; ?></div>
                    <div class="stat-label">Completadas</div>
                </div>
                <div class="stat-card azul" style="border-color:#3498db">
                    <div class="stat-num"><?php echo $stats['enviando']; ?></div>
                    <div class="stat-label">En progreso</div>
                </div>
                <div class="stat-card gris">
                    <div class="stat-num"><?php echo $stats['borradores']; ?></div>
                    <div class="stat-label">Borradores</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>📧 Campañas de Correo Masivo</h2>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <form method="GET" class="search-container">
                            <input type="text" name="buscar" class="search-box"
                                placeholder="Buscar campaña o asunto..."
                                value="<?php echo htmlspecialchars($buscar); ?>">
                            <button type="submit" class="btn btn-search">Buscar</button>
                            <?php if (!empty($buscar)): ?>
                            <a href="index.php" class="btn" style="background:#95a5a6;color:white;">Limpiar</a>
                            <?php endif; ?>
                        </form>
                        <a href="crear.php" class="btn btn-primary">+ Nueva Campaña</a>
                    </div>
                </div>

                <?php if (empty($campanas)): ?>
                <div class="empty-state">
                    <div class="icon">📭</div>
                    <h3>No hay campañas registradas</h3>
                    <p style="margin-top:8px;">Crea tu primera campaña de correo masivo.</p>
                    <br>
                    <a href="crear.php" class="btn btn-primary">+ Nueva Campaña</a>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Campaña</th>
                            <th>Asunto</th>
                            <th>Destinatarios</th>
                            <th>Enviados</th>
                            <th>Fallidos</th>
                            <th>Estado</th>
                            <th>Creado por</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($campanas as $c): ?>
                        <?php
                        $pct = $c['total_destinatarios'] > 0
                            ? round(($c['enviados'] / $c['total_destinatarios']) * 100)
                            : 0;
                        $badge = [
                            'BORRADOR'   => 'badge-borrador',
                            'ENVIANDO'   => 'badge-enviando',
                            'COMPLETADO' => 'badge-completado',
                            'ERROR'      => 'badge-error',
                        ][$c['estado']] ?? 'badge-borrador';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($c['nombre_campana']); ?></strong></td>
                            <td><?php echo htmlspecialchars($c['asunto']); ?></td>
                            <td style="text-align:center"><?php echo $c['total_destinatarios']; ?></td>
                            <td style="text-align:center;color:#27ae60;font-weight:600"><?php echo $c['enviados']; ?></td>
                            <td style="text-align:center;color:<?php echo $c['fallidos']>0?'#e74c3c':'#27ae60'; ?>;font-weight:600"><?php echo $c['fallidos']; ?></td>
                            <td>
                                <span class="badge <?php echo $badge; ?>"><?php echo $c['estado']; ?></span>
                                <?php if ($c['estado'] === 'ENVIANDO'): ?>
                                <br><small style="color:#3498db"><?php echo $pct; ?>%</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($c['creador'] ?? '—'); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($c['creado_en'])); ?></td>
                            <td>
                                <a href="ver.php?id=<?php echo $c['id']; ?>" class="btn btn-small btn-info" title="Ver detalle">👁</a>
                                <?php if ($c['estado'] === 'BORRADOR'): ?>
                                <a href="crear.php?id=<?php echo $c['id']; ?>" class="btn btn-small btn-warning" title="Editar">✏️</a>
                                <?php endif; ?>
                                <button onclick="eliminarCampana(<?php echo $c['id']; ?>,'<?php echo htmlspecialchars(addslashes($c['nombre_campana'])); ?>')"
                                    class="btn btn-small btn-danger" title="Eliminar">🗑</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginación -->
                <?php if ($paginacion['total_paginas'] > 1): ?>
                <div class="pagination">
                    <a href="?pagina=<?php echo max(1,$pagina-1); ?>&buscar=<?php echo urlencode($buscar); ?>"
                       class="pagination-btn <?php echo $pagina<=1?'disabled':''; ?>">← Anterior</a>
                    <span class="pagination-info">
                        Página <strong><?php echo $pagina; ?></strong> de <strong><?php echo $paginacion['total_paginas']; ?></strong>
                    </span>
                    <a href="?pagina=<?php echo min($paginacion['total_paginas'],$pagina+1); ?>&buscar=<?php echo urlencode($buscar); ?>"
                       class="pagination-btn <?php echo $pagina>=$paginacion['total_paginas']?'disabled':''; ?>">Siguiente →</a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal confirmar eliminación -->
    <div id="modalEliminar" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
         background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:15px;padding:35px;max-width:420px;width:90%;text-align:center;">
            <div style="font-size:3rem;margin-bottom:15px;">⚠️</div>
            <h3 style="color:#2c3e50;margin-bottom:10px;">¿Eliminar campaña?</h3>
            <p id="txtCampana" style="color:#7f8c8d;margin-bottom:25px;"></p>
            <p style="color:#e74c3c;font-size:.85rem;margin-bottom:25px;">
                Se eliminarán todos los datos asociados (destinatarios, adjuntos, historial).
            </p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button onclick="cerrarModal()" class="btn" style="background:#95a5a6;color:white;">Cancelar</button>
                <a id="btnConfirmarEliminar" href="#" class="btn btn-danger">Sí, eliminar</a>
            </div>
        </div>
    </div>

    <script>
    function eliminarCampana(id, nombre) {
        document.getElementById('txtCampana').textContent = '"' + nombre + '"';
        document.getElementById('btnConfirmarEliminar').href = 'eliminar.php?id=' + id;
        const modal = document.getElementById('modalEliminar');
        modal.style.display = 'flex';
    }
    function cerrarModal() {
        document.getElementById('modalEliminar').style.display = 'none';
    }
    </script>
</body>
</html>

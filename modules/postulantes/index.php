<?php
// modules/postulantes/index.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN']);

$database = new Database();
$conn = $database->getConnection();

// Filtros
$buscar = isset($_GET['buscar']) ? limpiarDatos($_GET['buscar']) : '';
$filtro_estado = isset($_GET['estado']) ? limpiarDatos($_GET['estado']) : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 15;

// Construir query
$where = 'WHERE 1=1';
$params = [];

if (!empty($buscar)) {
    $where .= ' AND (nombres LIKE :buscar OR apellidos LIKE :buscar2 OR dni LIKE :buscar3 OR correo LIKE :buscar4)';
    $param_buscar = "%{$buscar}%";
    $params[':buscar']  = $param_buscar;
    $params[':buscar2'] = $param_buscar;
    $params[':buscar3'] = $param_buscar;
    $params[':buscar4'] = $param_buscar;
}

if (!empty($filtro_estado)) {
    $where .= ' AND estado = :estado';
    $params[':estado'] = $filtro_estado;
}

// Total
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM postulantes $where");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$total = $stmt->fetch()['total'];

$paginacion = paginar($total, $registros_por_pagina, $pagina);

// Listado
$stmt = $conn->prepare("SELECT * FROM postulantes $where ORDER BY fecha_postulacion DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $paginacion['registros_por_pagina'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $paginacion['offset'],               PDO::PARAM_INT);
$stmt->execute();
$postulantes = $stmt->fetchAll();

// Conteos por estado
$stmt_conteos = $conn->query("SELECT estado, COUNT(*) as c FROM postulantes GROUP BY estado");
$conteos = [];
foreach ($stmt_conteos->fetchAll() as $row) {
    $conteos[$row['estado']] = $row['c'];
}

$estados_colores = [
    'NUEVO'       => ['bg' => '#e3f2fd', 'color' => '#1565c0', 'label' => 'Nuevo'],
    'REVISADO'    => ['bg' => '#fff3e0', 'color' => '#e65100', 'label' => 'Revisado'],
    'CONTACTADO'  => ['bg' => '#e8f5e9', 'color' => '#2e7d32', 'label' => 'Contactado'],
    'DESCARTADO'  => ['bg' => '#fce4ec', 'color' => '#c62828', 'label' => 'Descartado'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postulantes - IFAST</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:#f5f7fa; }
        .container { display:flex; min-height:100vh; }
        .main-content { flex:1; margin-left:260px; }
        .header { background:white; padding:20px 30px; box-shadow:0 2px 10px rgba(0,0,0,.05); display:flex; justify-content:space-between; align-items:center; }
        .header h1 { font-size:1.8rem; color:#2c3e50; }
        .content { padding:30px; }
        .btn { padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all .3s; font-size:.9rem; }
        .btn-back { background:#FDC500; color:#fff; }
        .btn-back:hover { background:#e6b000; }

        /* Tarjetas resumen */
        .summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; margin-bottom:25px; }
        .summary-card { background:white; border-radius:12px; padding:18px 20px; box-shadow:0 3px 12px rgba(0,0,0,.07); display:flex; align-items:center; gap:15px; cursor:pointer; transition:all .3s; border:2px solid transparent; text-decoration:none; }
        .summary-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.12); }
        .summary-card.active-filter { border-color:#00509D; }
        .summary-icon { width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
        .summary-info strong { font-size:1.5rem; color:#2c3e50; display:block; line-height:1; }
        .summary-info span { font-size:.8rem; color:#888; }

        /* Filtros */
        .filter-bar { display:flex; gap:10px; margin-bottom:20px; align-items:center; flex-wrap:wrap; }
        .search-box { padding:10px 16px; border:2px solid #e0e0e0; border-radius:8px; width:280px; font-size:.95rem; }
        .search-box:focus { outline:none; border-color:#00509D; }
        .btn-search { background:linear-gradient(135deg,#00296b,#00509d); color:white; }
        .btn-clear { background:#95a5a6; color:white; }

        /* Tabla */
        .card { background:white; border-radius:15px; padding:25px; box-shadow:0 5px 20px rgba(0,0,0,.08); }
        .card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:15px; border-bottom:2px solid #f5f7fa; }
        .card-header h2 { font-size:1.3rem; color:#2c3e50; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fa; padding:12px 15px; text-align:left; font-weight:600; color:#2c3e50; font-size:.85rem; text-transform:uppercase; }
        td { padding:13px 15px; border-bottom:1px solid #f0f0f0; color:#555; font-size:.9rem; }
        tr:hover td { background:#fafbfc; }
        .badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:.78rem; font-weight:600; }
        .btn-ver { padding:7px 14px; background:linear-gradient(135deg,#00296b,#00509d); color:white; border-radius:7px; text-decoration:none; font-size:.82rem; font-weight:600; display:inline-flex; align-items:center; gap:5px; transition:all .2s; border:none; cursor:pointer; }
        .btn-ver:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,80,157,.3); }

        /* Paginación */
        .pagination { display:flex; justify-content:center; align-items:center; gap:12px; margin-top:25px; }
        .pagination-btn { padding:10px 18px; border:2px solid #00509D; border-radius:8px; text-decoration:none; color:#00509D; font-weight:600; transition:all .3s; background:white; }
        .pagination-btn:hover:not(.disabled) { background:#00509D; color:white; }
        .pagination-btn.disabled { opacity:.3; pointer-events:none; border-color:#ccc; color:#ccc; }
        .pagination-info { padding:8px 16px; background:#f8f9fa; border-radius:8px; color:#2c3e50; font-weight:600; font-size:.9rem; }
        .pagination-info strong { color:#00509D; }

        .empty-state { text-align:center; padding:50px; color:#aaa; }
        .empty-state p { margin-top:10px; font-size:1.05rem; }

        @media(max-width:768px) { .main-content{margin-left:0;} .summary-grid{grid-template-columns:repeat(2,1fr);} }
    </style>
</head>
<body>
<?php require_once '../../includes/sidebar.php'; ?>

<main class="main-content">
    <header class="header">
        <h1>Postulantes</h1>
        <a href="../../dashboard.php" class="btn btn-back">← Volver</a>
    </header>

    <div class="content">

        <!-- Resumen por estado -->
        <div class="summary-grid">
            <?php
            $iconos = ['NUEVO' => '🆕', 'REVISADO' => '👁️', 'CONTACTADO' => '📞', 'DESCARTADO' => '❌'];
            foreach ($estados_colores as $key => $cfg):
                $count = $conteos[$key] ?? 0;
                $is_active = ($filtro_estado === $key);
                $url_estado = '?estado=' . $key . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
            ?>
            <a href="<?php echo $is_active ? '?' . (!empty($buscar) ? 'buscar=' . urlencode($buscar) : '') : $url_estado; ?>"
               class="summary-card <?php echo $is_active ? 'active-filter' : ''; ?>">
                <div class="summary-icon" style="background:<?php echo $cfg['bg']; ?>">
                    <?php echo $iconos[$key]; ?>
                </div>
                <div class="summary-info">
                    <strong><?php echo $count; ?></strong>
                    <span><?php echo $cfg['label']; ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Barra de búsqueda -->
        <form method="GET" class="filter-bar">
            <?php if (!empty($filtro_estado)): ?>
                <input type="hidden" name="estado" value="<?php echo htmlspecialchars($filtro_estado); ?>">
            <?php endif; ?>
            <input type="text" name="buscar" class="search-box"
                   placeholder="🔍 Buscar por nombre, DNI o correo..."
                   value="<?php echo htmlspecialchars($buscar); ?>">
            <button type="submit" class="btn btn-search">Buscar</button>
            <?php if (!empty($buscar) || !empty($filtro_estado)): ?>
                <a href="index.php" class="btn btn-clear">Limpiar</a>
            <?php endif; ?>
        </form>

        <div class="card">
            <div class="card-header">
                <h2>
                    <?php echo $total; ?> postulante<?php echo $total !== 1 ? 's' : ''; ?>
                    <?php if (!empty($filtro_estado)): ?>
                        — <?php echo $estados_colores[$filtro_estado]['label'] ?? $filtro_estado; ?>
                    <?php endif; ?>
                </h2>
            </div>

            <?php if (count($postulantes) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre Completo</th>
                        <th>DNI</th>
                        <th>Correo</th>
                        <th>Celular</th>
                        <th>CV</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($postulantes as $p):
                        $cfg = $estados_colores[$p['estado']] ?? ['bg' => '#eee', 'color' => '#333', 'label' => $p['estado']];
                    ?>
                    <tr>
                        <td><strong>#<?php echo $p['id']; ?></strong></td>
                        <td><strong><?php echo htmlspecialchars($p['nombres'] . ' ' . $p['apellidos']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['dni']); ?></td>
                        <td><?php echo htmlspecialchars($p['correo']); ?></td>
                        <td><?php echo htmlspecialchars($p['celular']); ?></td>
                        <td>
                            <span style="font-size:.8rem;color:#666;">
                                <?php echo strtoupper($p['cv_tipo']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background:<?php echo $cfg['bg']; ?>;color:<?php echo $cfg['color']; ?>">
                                <?php echo $cfg['label']; ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php echo date('d/m/Y', strtotime($p['fecha_postulacion'])); ?>
                        </td>
                        <td>
                            <a href="ver.php?id=<?php echo $p['id']; ?>" class="btn-ver">
                                👁 Ver
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Paginación -->
            <?php if ($paginacion['total_paginas'] > 1): ?>
            <div class="pagination">
                <?php
                $qs_base = '';
                if (!empty($buscar))       $qs_base .= '&buscar=' . urlencode($buscar);
                if (!empty($filtro_estado)) $qs_base .= '&estado=' . urlencode($filtro_estado);
                ?>
                <?php if ($pagina > 1): ?>
                    <a href="?pagina=<?php echo $pagina - 1 . $qs_base; ?>" class="pagination-btn">◀ Anterior</a>
                <?php else: ?>
                    <span class="pagination-btn disabled">◀ Anterior</span>
                <?php endif; ?>

                <div class="pagination-info">
                    Página <strong><?php echo $pagina; ?></strong> de <strong><?php echo $paginacion['total_paginas']; ?></strong>
                </div>

                <?php if ($pagina < $paginacion['total_paginas']): ?>
                    <a href="?pagina=<?php echo $pagina + 1 . $qs_base; ?>" class="pagination-btn">Siguiente ▶</a>
                <?php else: ?>
                    <span class="pagination-btn disabled">Siguiente ▶</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <div style="font-size:3rem;">📋</div>
                <p>No se encontraron postulantes<?php echo !empty($buscar) ? ' para "' . htmlspecialchars($buscar) . '"' : ''; ?>.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</body>
</html>

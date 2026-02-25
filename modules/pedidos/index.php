<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();
$tipo_usuario = obtenerTipoUsuario();

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;

// Búsqueda
$buscar = isset($_GET['buscar']) ? limpiarDatos($_GET['buscar']) : '';

// Filtro de estado (TODOS por defecto)
$filtro_estado = isset($_GET['estado']) ? limpiarDatos($_GET['estado']) : 'TODOS';

// Query base - ahora con contador de trackings
$query_count = "SELECT COUNT(DISTINCT rp.id) as total FROM recibos_pedidos rp INNER JOIN clientes c ON rp.cliente_id = c.id";
$query_select = "
    SELECT
        rp.*,
        c.nombre_razon_social,
        c.apellido,
        c.documento,
        (SELECT COUNT(*) FROM pedidos_trackings pt WHERE pt.recibo_pedido_id = rp.id) as total_trackings,
        (SELECT COUNT(*) FROM pedidos_trackings pt WHERE pt.recibo_pedido_id = rp.id AND pt.estado_embarque = 'EMBARCADO') as trackings_embarcados,
        (SELECT COUNT(*) FROM pedidos_trackings pt WHERE pt.recibo_pedido_id = rp.id AND pt.estado_embarque = 'PENDIENTE') as trackings_pendientes
    FROM recibos_pedidos rp
    INNER JOIN clientes c ON rp.cliente_id = c.id
";

// Construir condiciones WHERE
$conditions = [];
$params = [];

// Filtro por estado
if ($filtro_estado === 'EMBARCADO') {
    // Solo pedidos donde TODOS los trackings están embarcados
    $query_count .= " INNER JOIN pedidos_trackings pt ON rp.id = pt.recibo_pedido_id";
    $conditions[] = "NOT EXISTS (SELECT 1 FROM pedidos_trackings pt2 WHERE pt2.recibo_pedido_id = rp.id AND pt2.estado_embarque = 'PENDIENTE')";
    $conditions[] = "EXISTS (SELECT 1 FROM pedidos_trackings pt3 WHERE pt3.recibo_pedido_id = rp.id AND pt3.estado_embarque = 'EMBARCADO')";
} elseif ($filtro_estado === 'PENDIENTE') {
    // Pedidos que tienen al menos un tracking pendiente
    $conditions[] = "EXISTS (SELECT 1 FROM pedidos_trackings pt WHERE pt.recibo_pedido_id = rp.id AND pt.estado_embarque = 'PENDIENTE')";
}

// Búsqueda
if (!empty($buscar)) {
    $conditions[] = "(c.nombre_razon_social LIKE :buscar1 OR c.documento LIKE :buscar2 OR rp.id IN (SELECT recibo_pedido_id FROM pedidos_trackings WHERE tracking_code LIKE :buscar3))";
    $params[':buscar1'] = "%{$buscar}%";
    $params[':buscar2'] = "%{$buscar}%";
    $params[':buscar3'] = "%{$buscar}%";
}

// Agregar WHERE si hay condiciones
if (count($conditions) > 0) {
    $where_clause = " WHERE " . implode(" AND ", $conditions);
    $query_count .= $where_clause;
    $query_select .= $where_clause;
}

$query_select .= " ORDER BY rp.subido_en DESC LIMIT :limit OFFSET :offset";

// Total de pedidos
$stmt = $conn->prepare($query_count);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_pedidos = $stmt->fetch()['total'];

// Calcular paginación
$paginacion = paginar($total_pedidos, $registros_por_pagina, $pagina);

// Obtener pedidos
$stmt = $conn->prepare($query_select);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $paginacion['registros_por_pagina'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $paginacion['offset'], PDO::PARAM_INT);
$stmt->execute();
$pedidos = $stmt->fetchAll();

// Obtener lista de clientes para el select del modal
$stmt = $conn->query("SELECT id, nombre_razon_social, apellido, documento FROM clientes ORDER BY nombre_razon_social ASC");
$clientes_lista = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }


        .main-content {
            flex: 1;
            margin-left: 260px;
        }

        .header {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
            color: #2c3e50;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00296b 0%, #00509d 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-back {
            background: linear-gradient(135deg, #fdc500 0%, #ffd500 100%);
            color: white;
            margin-left: 10px;
        }

        .content {
            padding: 30px;
        }

        /* FILTER TABS */
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }

        .filter-tab {
            padding: 10px 20px;
            background: #ecf0f1;
            border: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            color: #7f8c8d;
            text-decoration: none;
            transition: all 0.3s;
        }

        .filter-tab:hover {
            background: #d5dbdb;
            color: #2c3e50;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #00296b 0%, #00509d 100%);
            color: white;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f5f7fa;
            flex-wrap: wrap;
            gap: 15px;
        }

        .card-header h2 {
            font-size: 1.5rem;
            color: #2c3e50;
        }

        .search-container {
            display: flex;
            gap: 10px;
        }

        .search-box {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            width: 300px;
            font-size: 0.95rem;
        }

        .search-box:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-search {
            background: #3498db;
            color: white;
            padding: 10px 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #555;
            font-size: 0.9rem;
        }

        tr:hover td {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-embarcado {
            background: #d4edda;
            color: #155724;
            font-weight: bold;
        }

        .badge-pendiente {
            background: #fff3cd;
            color: #856404;
            font-weight: bold;
        }

        .badge-mixto {
            background: #cfe2ff;
            color: #084298;
            font-weight: bold;
        }

        .badge-warning {
            background: #f8d7da;
            color: #721c24;
            font-weight: bold;
        }

        .badge-info {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-warning {
            background: #FFF3CD;
            color: #856404;
        }

        .badge-embarcado {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-pendiente {
            background: #fff3e0;
            color: #f57c00;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 0.8rem;
            margin-right: 5px;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-download {
            background: #27ae60;
            color: white;
        }

        .btn-view {
            background: #9b59b6;
            color: white;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 30px;
        }

        .pagination-btn {
            padding: 12px 20px;
            border: 2px solid #00509D;
            border-radius: 8px;
            text-decoration: none;
            color: #00509D;
            font-weight: 600;
            transition: all 0.3s;
            background: white;
            cursor: pointer;
            font-size: 1.1rem;
        }

        .pagination-btn:hover:not(.disabled) {
            background: #00509D;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 80, 157, 0.3);
        }

        .pagination-btn.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            border-color: #ccc;
            color: #ccc;
        }

        .pagination-info {
            padding: 10px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .pagination-info strong {
            color: #00509D;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
        }

        /* ESTILOS MODALS */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 0;
            max-width: 700px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        .modal-header {
            background: linear-gradient(135deg, #00296B 0%, #00509D 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .close-modal {
            background: transparent;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }

        .close-modal:hover {
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
        }

        /* Estilos para modal de vista */
        .view-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .view-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .view-section-title {
            font-size: 1.1rem;
            color: #00296B;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .view-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .view-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .view-item.full-width {
            grid-column: 1 / -1;
        }

        .view-item label {
            font-weight: 600;
            color: #718096;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .view-item span {
            color: #2c3e50;
            font-size: 1rem;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #00509D;
        }

        /* Lista de trackings en el modal */
        .trackings-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .tracking-card {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid #00509D;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tracking-code {
            font-weight: 600;
            color: #2c3e50;
        }

        .modal-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #e9ecef;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            font-weight: 600;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>
        <!-- MAIN -->
        <main class="main-content">
            <header class="header">
                <h1>📦 Gestión de Pedidos</h1>
                <div>
                    <a href="../../dashboard.php" class="btn btn-back">← Volver</a>
                </div>
            </header>

            <div class="content">
                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✓ <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    ✗ <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
                <?php endif; ?>

                <!-- FILTROS POR ESTADO -->
                <div class="filter-tabs">
                    <a href="?estado=TODOS<?php echo !empty($buscar) ? '&buscar=' . urlencode($buscar) : ''; ?>"
                       class="filter-tab <?php echo $filtro_estado === 'TODOS' ? 'active' : ''; ?>">
                        📋 Todos
                    </a>
                    <a href="?estado=PENDIENTE<?php echo !empty($buscar) ? '&buscar=' . urlencode($buscar) : ''; ?>"
                       class="filter-tab <?php echo $filtro_estado === 'PENDIENTE' ? 'active' : ''; ?>">
                        ⏳ Pendientes
                    </a>
                    <a href="?estado=EMBARCADO<?php echo !empty($buscar) ? '&buscar=' . urlencode($buscar) : ''; ?>"
                       class="filter-tab <?php echo $filtro_estado === 'EMBARCADO' ? 'active' : ''; ?>">
                        ✅ Embarcados
                    </a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Lista de Recibos (<?php echo $total_pedidos; ?>)</h2>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <form method="GET" class="search-container">
                                <input type="hidden" name="estado" value="<?php echo htmlspecialchars($filtro_estado); ?>">
                                <input type="text" name="buscar" class="search-box"
                                       placeholder="🔍 Buscar por tracking, cliente..."
                                       value="<?php echo htmlspecialchars($buscar); ?>">
                                <button type="submit" class="btn btn-search">Buscar</button>
                                <?php if (!empty($buscar)): ?>
                                <a href="?estado=<?php echo urlencode($filtro_estado); ?>" class="btn btn-back">Limpiar</a>
                                <?php endif; ?>
                            </form>
                            <?php if (in_array($tipo_usuario, ['ADMIN', 'SUPERVISOR', 'VENTAS'])): ?>
                            <a href="crear.php" class="btn btn-primary">+ Nuevo Pedido</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (count($pedidos) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Trackings</th>
                                    <th>Cliente</th>
                                    <th>Documento</th>
                                    <th>Archivo</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td><strong>#<?php echo $pedido['id']; ?></strong></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo $pedido['total_trackings']; ?> tracking(s)
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        echo $pedido['nombre_razon_social'];
                                        if ($pedido['apellido']) {
                                            echo ' ' . $pedido['apellido'];
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $pedido['documento']; ?></td>
                                    <td><?php echo isset($pedido['nombre_original']) && !empty($pedido['nombre_original']) ? $pedido['nombre_original'] : $pedido['nombre_archivo']; ?></td>
                                    <td><?php echo formatearFecha($pedido['subido_en']); ?></td>
                                    <td>
                                        <?php
                                        // Determinar el estado según los trackings
                                        if ($pedido['total_trackings'] == 0) {
                                            echo '<span class="badge badge-warning">SIN TRACKINGS</span>';
                                        } elseif ($pedido['trackings_embarcados'] == $pedido['total_trackings']) {
                                            // Todos embarcados
                                            echo '<span class="badge badge-embarcado">EMBARCADO</span>';
                                        } elseif ($pedido['trackings_pendientes'] == $pedido['total_trackings']) {
                                            // Todos pendientes
                                            echo '<span class="badge badge-pendiente">PENDIENTE</span>';
                                        } else {
                                            // Mixto (algunos embarcados, algunos pendientes)
                                            echo '<span class="badge badge-mixto">PARCIAL (' . $pedido['trackings_embarcados'] . '/' . $pedido['total_trackings'] . ')</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button onclick="abrirModalVer(<?php echo $pedido['id']; ?>)"
                                           class="btn btn-small btn-view" title="Ver detalles">
                                           👁️
                                        </button>
                                        <a href="descargar.php?id=<?php echo $pedido['id']; ?>"
                                           class="btn btn-small btn-download" title="Descargar">
                                           ⬇️
                                        </a>
                                        <?php if (in_array($tipo_usuario, ['ADMIN', 'SUPERVISOR', 'VENTAS'])): ?>
                                        <a href="editar.php?id=<?php echo $pedido['id']; ?>"
                                           class="btn btn-small btn-edit" title="Editar">
                                           ✏️
                                        </a>

                                        <?php // BOTÓN ELIMINAR - SOLO ADMIN ?>
                                        <?php if ($tipo_usuario === 'ADMIN'): ?>
                                        <a href="eliminar.php?id=<?php echo $pedido['id']; ?>"
                                           class="btn btn-small btn-delete" title="Eliminar"
                                           onclick="return confirm('¿Eliminar este recibo y todos sus trackings?')">
                                           🗑️
                                        </a>
                                        <?php endif; ?>
                                        <?php // FIN BOTÓN ELIMINAR ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="pagination">
                        <?php
                        // URL para navegación anterior
                        $url_anterior = "?pagina=" . ($pagina - 1);
                        if (!empty($buscar)) {
                            $url_anterior .= "&buscar=" . urlencode($buscar);
                        }
                        if ($filtro_estado !== 'TODOS') {
                            $url_anterior .= "&estado=" . urlencode($filtro_estado);
                        }

                        // URL para navegación siguiente
                        $url_siguiente = "?pagina=" . ($pagina + 1);
                        if (!empty($buscar)) {
                            $url_siguiente .= "&buscar=" . urlencode($buscar);
                        }
                        if ($filtro_estado !== 'TODOS') {
                            $url_siguiente .= "&estado=" . urlencode($filtro_estado);
                        }

                        // Calcular registros mostrados
                        $registro_inicio = ($paginacion['offset'] + 1);
                        $registro_fin = min($paginacion['offset'] + $paginacion['registros_por_pagina'], $total_pedidos);
                        ?>

                        <!-- Botón Anterior -->
                        <?php if ($pagina > 1): ?>
                            <a href="<?php echo $url_anterior; ?>" class="pagination-btn">
                                &#9664; Anterior
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                &#9664; Anterior
                            </span>
                        <?php endif; ?>

                        <!-- Contador de registros -->
                        <div class="pagination-info">
                            Mostrando <strong><?php echo $registro_inicio; ?>-<?php echo $registro_fin; ?></strong>
                            de <strong><?php echo $total_pedidos; ?></strong> registros
                        </div>

                        <!-- Botón Siguiente -->
                        <?php if ($pagina < $paginacion['total_paginas']): ?>
                            <a href="<?php echo $url_siguiente; ?>" class="pagination-btn">
                                Siguiente &#9654;
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                Siguiente &#9654;
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php else: ?>
                    <div class="empty-state">
                        <i>📦</i>
                        <h3>No hay pedidos registrados</h3>
                        <p>
                            <?php if (!empty($buscar)): ?>
                                No se encontraron resultados para "<?php echo htmlspecialchars($buscar); ?>"
                            <?php else: ?>
                                Comienza registrando tu primer pedido
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL VER PEDIDO -->
    <div id="modalVer" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>👁️ Detalles del Recibo</h2>
                <button class="close-modal" onclick="cerrarModalVer()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="view-section">
                    <h3 class="view-section-title">📋 Información del Recibo</h3>
                    <div class="view-grid">
                        <div class="view-item">
                            <label>ID:</label>
                            <span id="ver_id">-</span>
                        </div>
                        <div class="view-item">
                            <label>Fecha de Registro:</label>
                            <span id="ver_fecha">-</span>
                        </div>
                    </div>
                </div>

                <div class="view-section">
                    <h3 class="view-section-title">📦 Trackings (Códigos de Envío)</h3>
                    <div id="ver_trackings_list" class="trackings-list">
                        <p style="text-align: center; color: #718096;">Cargando...</p>
                    </div>
                </div>

                <div class="view-section">
                    <h3 class="view-section-title">👤 Información del Cliente</h3>
                    <div class="view-grid">
                        <div class="view-item">
                            <label>Cliente:</label>
                            <span id="ver_cliente">-</span>
                        </div>
                        <div class="view-item">
                            <label>Tipo Documento:</label>
                            <span id="ver_tipo_doc">-</span>
                        </div>
                        <div class="view-item">
                            <label>Documento:</label>
                            <span id="ver_documento">-</span>
                        </div>
                    </div>
                </div>

                <div class="view-section">
                    <h3 class="view-section-title">📄 Archivo Adjunto</h3>
                    <div class="view-grid">
                        <div class="view-item full-width">
                            <label>Nombre del Archivo:</label>
                            <span id="ver_archivo">-</span>
                        </div>
                    </div>
                </div>

                <div class="view-section">
                    <h3 class="view-section-title">💰 Información de Pago</h3>
                    <div class="view-grid">
                        <div class="view-item">
                            <label>Pendiente de Pago:</label>
                            <span id="ver_pendiente_pago">-</span>
                        </div>
                        <div class="view-item">
                            <label>Monto Pendiente:</label>
                            <span id="ver_monto_pendiente">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="cerrarModalVer()">Cerrar</button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
    <script>
        // ========================================
        // FUNCIONES MODAL VER
        // ========================================
        function abrirModalVer(id) {
            const modal = document.getElementById('modalVer');
            modal.classList.add('active');

            fetch('editar.php?id=' + id + '&ajax=1')
                .then(response => response.text())
                .then(text => {
                    const data = JSON.parse(text);

                    if (data.success) {
                        const pedido = data.pedido;
                        const cliente = data.cliente;

                        // Información del Pedido
                        document.getElementById('ver_id').textContent = pedido.id;
                        document.getElementById('ver_fecha').textContent = formatearFecha(pedido.subido_en);

                        // Información del Cliente
                        let nombreCompleto = cliente.nombre_razon_social;
                        if (cliente.apellido) {
                            nombreCompleto += ' ' + cliente.apellido;
                        }
                        document.getElementById('ver_cliente').textContent = nombreCompleto;
                        document.getElementById('ver_tipo_doc').textContent = cliente.tipo_documento;
                        document.getElementById('ver_documento').textContent = cliente.documento;

                        // Archivo
                        const nombreArchivo = pedido.nombre_original || pedido.nombre_archivo;
                        document.getElementById('ver_archivo').textContent = nombreArchivo;

                        // Trackings
                        const trackingsList = document.getElementById('ver_trackings_list');
                        if (data.trackings && data.trackings.length > 0) {
                            let trackingsHTML = '';
                            data.trackings.forEach(tracking => {
                                const estadoBadge = tracking.estado_embarque === 'EMBARCADO'
                                    ? '<span class="badge badge-embarcado">EMBARCADO</span>'
                                    : '<span class="badge badge-pendiente">PENDIENTE</span>';

                                trackingsHTML += `
                                    <div class="tracking-card">
                                        <span class="tracking-code">${tracking.tracking_code}</span>
                                        ${estadoBadge}
                                    </div>
                                `;
                            });
                            trackingsList.innerHTML = trackingsHTML;
                        } else {
                            trackingsList.innerHTML = '<p style="text-align: center; color: #718096;">No hay trackings registrados</p>';
                        }

                        // Información de Pago
                        const pendientePago = document.getElementById('ver_pendiente_pago');
                        const montoPendiente = document.getElementById('ver_monto_pendiente');

                        if (pedido.pendiente_pago === 'SI') {
                            pendientePago.innerHTML = '<span class="badge" style="background: #FFF3CD; color: #856404; font-weight: bold;">SI</span>';
                            montoPendiente.textContent = '$. ' + parseFloat(pedido.monto_pendiente).toFixed(2);
                            montoPendiente.style.fontWeight = 'bold';
                            montoPendiente.style.color = '#856404';
                        } else {
                            pendientePago.innerHTML = '<span class="badge badge-success">NO</span>';
                            montoPendiente.textContent = '$. 0.00';
                            montoPendiente.style.fontWeight = 'normal';
                            montoPendiente.style.color = '#2c3e50';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los detalles del pedido');
                });
        }

        function cerrarModalVer() {
            document.getElementById('modalVer').classList.remove('active');
        }

        // Función auxiliar para formatear fechas
        function formatearFecha(fecha) {
            const date = new Date(fecha);
            const dia = String(date.getDate()).padStart(2, '0');
            const mes = String(date.getMonth() + 1).padStart(2, '0');
            const año = date.getFullYear();
            const horas = String(date.getHours()).padStart(2, '0');
            const minutos = String(date.getMinutes()).padStart(2, '0');
            return `${dia}/${mes}/${año} ${horas}:${minutos}`;
        }

        // ========================================
        // CERRAR MODAL AL CLICK FUERA
        // ========================================
        window.onclick = function(event) {
            const modalVer = document.getElementById('modalVer');

            if (event.target === modalVer) {
                cerrarModalVer();
            }
        }
    </script>
</body>
</html>

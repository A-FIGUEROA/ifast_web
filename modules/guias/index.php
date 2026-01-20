<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();
$tipo_usuario = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;

// Búsqueda
$buscar = isset($_GET['buscar']) ? limpiarDatos($_GET['buscar']) : '';

// Filtro de estado
$filtro_estado = isset($_GET['estado']) ? limpiarDatos($_GET['estado']) : '';

// Filtro de estado de facturación (TODOS por defecto)
$filtro_facturacion = isset($_GET['estado_facturacion']) ? limpiarDatos($_GET['estado_facturacion']) : 'TODOS';

// Filtro de asesor
$filtro_asesor = isset($_GET['asesor']) ? limpiarDatos($_GET['asesor']) : '';

// Filtro de fechas
$fecha_desde = isset($_GET['fecha_desde']) ? limpiarDatos($_GET['fecha_desde']) : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? limpiarDatos($_GET['fecha_hasta']) : '';

// Obtener lista de asesores únicos para el filtro
$stmt_asesores = $conn->query("
    SELECT DISTINCT asesor
    FROM guias_masivas
    WHERE asesor IS NOT NULL AND asesor != ''
    ORDER BY asesor ASC
");
$asesores = $stmt_asesores->fetchAll(PDO::FETCH_COLUMN);

// Query base con alias
$query_count = "
    SELECT COUNT(*) as total
    FROM guias_masivas gm
";

$query_select = "
    SELECT gm.*, CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario
    FROM guias_masivas gm
    LEFT JOIN usuarios u ON gm.creado_por = u.id
";

$conditions = [];
$params = [];

// Si hay búsqueda
if (!empty($buscar)) {
    $conditions[] = "(gm.nro_guia LIKE :buscar1 OR gm.consignatario LIKE :buscar2 OR gm.cliente LIKE :buscar3 OR gm.asesor LIKE :buscar4)";
    $params[':buscar1'] = "%{$buscar}%";
    $params[':buscar2'] = "%{$buscar}%";
    $params[':buscar3'] = "%{$buscar}%";
    $params[':buscar4'] = "%{$buscar}%";
}

// Si hay filtro de estado
if (!empty($filtro_estado)) {
    $conditions[] = "gm.estado = :estado";
    $params[':estado'] = $filtro_estado;
}

// Si hay filtro de estado de facturación
if ($filtro_facturacion !== 'TODOS') {
    if ($filtro_facturacion === 'LIQUIDADO') {
        $conditions[] = "gm.estado_facturacion = 'LIQUIDADO'";
    } else {
        // PENDIENTE: NULL o vacío
        $conditions[] = "(gm.estado_facturacion IS NULL OR gm.estado_facturacion = '' OR gm.estado_facturacion = 'PENDIENTE')";
    }
}

// Si hay filtro de asesor
if (!empty($filtro_asesor)) {
    $conditions[] = "gm.asesor = :asesor";
    $params[':asesor'] = $filtro_asesor;
}

// Si hay filtro de fecha desde
if (!empty($fecha_desde)) {
    $conditions[] = "gm.fecha_embarque >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

// Si hay filtro de fecha hasta
if (!empty($fecha_hasta)) {
    $conditions[] = "gm.fecha_embarque <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

// Agregar condiciones si existen
if (count($conditions) > 0) {
    $where = " WHERE " . implode(" AND ", $conditions);
    $query_count .= $where;
    $query_select .= $where;
}

$query_select .= " ORDER BY gm.nro_guia ASC LIMIT :limit OFFSET :offset";

// Total de guías
$stmt = $conn->prepare($query_count);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_guias = $stmt->fetch()['total'];

// Calcular paginación
$paginacion = paginar($total_guias, $registros_por_pagina, $pagina);

// Obtener guías
$stmt = $conn->prepare($query_select);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $paginacion['registros_por_pagina'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $paginacion['offset'], PDO::PARAM_INT);
$stmt->execute();
$guias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Guías</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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

        /* HEADER */
        .header {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FDC500 0%, #FFD500 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .user-details {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: #7f8c8d;
            display: inline-block;
            padding: 2px 8px;
            background: #ecf0f1;
            border-radius: 10px;
            margin-top: 2px;
        }

        .btn-logout {
            padding: 10px 20px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-logout:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
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
            font-weight: 600;
            font-size: 0.95rem;
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
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .card-title {
            font-size: 1.3rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00296B 0%, #00509D 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 41, 107, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ffc107;
            color: #000;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .search-filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #00509D;
            box-shadow: 0 0 0 4px rgba(0, 80, 157, 0.1);
        }

        .search-box button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, #00296B 0%, #00509D 100%);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
        }

        .filter-select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.9rem;
            min-width: 140px;
            transition: all 0.3s;
        }

        .filter-select:focus {
            outline: none;
            border-color: #00509D;
            box-shadow: 0 0 0 3px rgba(0, 80, 157, 0.1);
        }

        input[type="date"].filter-select {
            min-width: 160px;
        }

        .table-container {
            overflow-x: auto;
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
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #555;
        }

        tr:hover td {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        /* Estilos para select de estados */
        .estado-select {
            padding: 6px 10px;
            border-radius: 20px;
            border: 2px solid;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            appearance: none;
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 12px;
            padding-right: 28px;
        }

        .estado-select:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .estado-entregado {
            background-color: #d4edda;
            color: #155724;
            border-color: #28a745;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23155724' d='M10.293 3.293L4 9.586 1.707 7.293l1.414-1.414L4 6.758l5.879-5.879z'/%3E%3C/svg%3E");
        }

        .estado-pendiente {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffc107;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Ccircle fill='%23856404' cx='6' cy='6' r='5' stroke='%23856404' stroke-width='1' fill='none'/%3E%3Cpath fill='%23856404' d='M6 3v4l2 2'/%3E%3C/svg%3E");
        }

        .estado-observado {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23721c24' d='M6 1L1 11h10L6 1zm0 3l.5 3h-1L6 4zm0 5a.75.75 0 100 1.5.75.75 0 000-1.5z'/%3E%3C/svg%3E");
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #00509D;
            text-decoration: none;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: #00509D;
            color: white;
        }

        .pagination .active {
            background: #00509D;
            color: white;
            border-color: #00509D;
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

        .empty-state h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: #555;
        }

        .empty-state p {
            font-size: 0.95rem;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .header {
                padding: 15px;
            }

            .content {
                padding: 15px;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-group {
                width: 100%;
            }

            .btn {
                flex: 1;
                justify-content: center;
            }

            .search-filter-bar {
                flex-direction: column;
            }

            .search-box,
            .filter-select,
            input[type="date"].filter-select {
                width: 100%;
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="header">
            <div class="header-left">
                <h1>Gestión de Guías</h1>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo substr($nombre_usuario, 0, 1); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo $nombre_usuario; ?></div>
                        <span class="user-role"><?php echo $tipo_usuario; ?></span>
                    </div>
                </div>
                <a href="../../logout.php" class="btn-logout">
                    <box-icon name='log-out' color='white' size='20px'></box-icon>
                </a>
            </div>
        </header>

        <div class="content">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class='bx bx-check-circle' style="font-size: 1.5rem;"></i>
                    <span>
                        <?php
                        if ($_GET['success'] == 'creado') echo 'Guía creada exitosamente';
                        elseif ($_GET['success'] == 'editado') echo 'Guía actualizada exitosamente';
                        elseif ($_GET['success'] == 'eliminado') echo 'Guía eliminada exitosamente';
                        elseif ($_GET['success'] == 'importado') echo 'Guías importadas exitosamente';
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- FILTER TABS -->
            <div class="filter-tabs">
                <?php
                // Construir URL base manteniendo búsqueda y otros filtros
                $base_params = [];
                if (!empty($buscar)) $base_params[] = "buscar=" . urlencode($buscar);
                if (!empty($filtro_estado)) $base_params[] = "estado=" . urlencode($filtro_estado);
                if (!empty($filtro_asesor)) $base_params[] = "asesor=" . urlencode($filtro_asesor);
                if (!empty($fecha_desde)) $base_params[] = "fecha_desde=" . urlencode($fecha_desde);
                if (!empty($fecha_hasta)) $base_params[] = "fecha_hasta=" . urlencode($fecha_hasta);
                $base_url = !empty($base_params) ? '&' . implode('&', $base_params) : '';
                ?>
                <a href="?estado_facturacion=TODOS<?php echo $base_url; ?>"
                   class="filter-tab <?php echo $filtro_facturacion === 'TODOS' ? 'active' : ''; ?>">
                    📊 Todos
                </a>
                <a href="?estado_facturacion=PENDIENTE<?php echo $base_url; ?>"
                   class="filter-tab <?php echo $filtro_facturacion === 'PENDIENTE' ? 'active' : ''; ?>">
                    ⏳ Pendientes Facturación
                </a>
                <a href="?estado_facturacion=LIQUIDADO<?php echo $base_url; ?>"
                   class="filter-tab <?php echo $filtro_facturacion === 'LIQUIDADO' ? 'active' : ''; ?>">
                    💰 Liquidados
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class='bx bx-package'></i> Gestión de Guías
                    </h2>
                    <?php if (in_array($tipo_usuario, ['ADMIN', 'SUPERVISOR'])): ?>
                    <div class="btn-group">
                        <a href="crear.php" class="btn btn-primary">
                            <i class='bx bx-plus'></i> Agregar Manual
                        </a>
                        <a href="cargar_excel.php" class="btn btn-success">
                            <i class='bx bx-upload'></i> Cargar Excel
                        </a>
                        <a href="descargar_plantilla.php" class="btn btn-secondary">
                            <i class='bx bx-download'></i> Plantilla
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <form method="GET" class="search-filter-bar">
                    <input type="hidden" name="estado_facturacion" value="<?php echo htmlspecialchars($filtro_facturacion); ?>">
                    <div class="search-box">
                        <input
                            type="text"
                            name="buscar"
                            placeholder="Buscar por N° Guía, Cliente, Consignatario o Asesor..."
                            value="<?php echo htmlspecialchars($buscar); ?>"
                        >
                        <button type="submit">
                            <i class='bx bx-search'></i>
                        </button>
                    </div>
                    <select name="estado" class="filter-select">
                        <option value="">Todos los estados</option>
                        <option value="ENTREGADO" <?php echo $filtro_estado === 'ENTREGADO' ? 'selected' : ''; ?>>Entregado</option>
                        <option value="PENDIENTE" <?php echo $filtro_estado === 'PENDIENTE' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="OBSERVADO" <?php echo $filtro_estado === 'OBSERVADO' ? 'selected' : ''; ?>>Observado</option>
                    </select>
                    <select name="asesor" class="filter-select">
                        <option value="">Todos los asesores</option>
                        <?php foreach ($asesores as $asesor): ?>
                        <option value="<?php echo htmlspecialchars($asesor); ?>" <?php echo $filtro_asesor === $asesor ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($asesor); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input
                        type="date"
                        name="fecha_desde"
                        class="filter-select"
                        placeholder="Desde"
                        value="<?php echo htmlspecialchars($fecha_desde); ?>"
                        title="Fecha desde"
                    >
                    <input
                        type="date"
                        name="fecha_hasta"
                        class="filter-select"
                        placeholder="Hasta"
                        value="<?php echo htmlspecialchars($fecha_hasta); ?>"
                        title="Fecha hasta"
                    >
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <i class='bx bx-filter'></i> Filtrar
                    </button>
                    <?php if (!empty($buscar) || !empty($filtro_estado) || !empty($filtro_asesor) || !empty($fecha_desde) || !empty($fecha_hasta)): ?>
                    <a href="index.php<?php
                        $preserve_params = [];
                        if ($filtro_facturacion !== 'TODOS') $preserve_params[] = 'estado_facturacion=' . urlencode($filtro_facturacion);
                        echo !empty($preserve_params) ? '?' . implode('&', $preserve_params) : '';
                    ?>" class="btn btn-secondary btn-sm" title="Limpiar filtros">
                        <i class='bx bx-x'></i> Limpiar
                    </a>
                    <?php endif; ?>
                </form>

                <div class="table-container">
                    <?php if (count($guias) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>N° Guía</th>
                                <th>Consignatario</th>
                                <th>Cliente</th>
                                <th>Descripción</th>
                                <th>PCS</th>
                                <th>Peso (kg)</th>
                                <th>Valor FOB</th>
                                <th>Fecha Embarque</th>
                                <th>Asesor</th>
                                <th>Estado</th>
                                <th>Facturación</th>
                                <th>Método</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($guias as $guia): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($guia['nro_guia']); ?></strong></td>
                                <td><?php echo htmlspecialchars($guia['consignatario']); ?></td>
                                <td><?php echo htmlspecialchars($guia['cliente'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars(substr($guia['descripcion'], 0, 50)); ?><?php echo strlen($guia['descripcion']) > 50 ? '...' : ''; ?></td>
                                <td><?php echo $guia['pcs']; ?></td>
                                <td><?php echo number_format($guia['peso_kg'], 2); ?></td>
                                <td>$<?php echo number_format($guia['valor_fob_usd'], 2); ?></td>
                                <td><?php echo $guia['fecha_embarque'] ? formatearFecha($guia['fecha_embarque'], 'd/m/Y') : '-'; ?></td>
                                <td><?php echo htmlspecialchars($guia['asesor'] ?? '-'); ?></td>
                                <td>
                                    <select
                                        class="estado-select <?php
                                            if ($guia['estado'] === 'ENTREGADO') echo 'estado-entregado';
                                            elseif ($guia['estado'] === 'PENDIENTE') echo 'estado-pendiente';
                                            elseif ($guia['estado'] === 'OBSERVADO') echo 'estado-observado';
                                        ?>"
                                        data-id="<?php echo $guia['id']; ?>"
                                        onchange="cambiarEstado(this)"
                                    >
                                        <option value="ENTREGADO" <?php echo $guia['estado'] === 'ENTREGADO' ? 'selected' : ''; ?>>Entregado</option>
                                        <option value="PENDIENTE" <?php echo $guia['estado'] === 'PENDIENTE' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="OBSERVADO" <?php echo $guia['estado'] === 'OBSERVADO' ? 'selected' : ''; ?>>Observado</option>
                                    </select>
                                </td>
                                <td>
                                    <?php if ($guia['estado_facturacion'] === 'LIQUIDADO'): ?>
                                        <span class="badge" style="background: #e8daef; color: #5b2c6f; border: 2px solid #8e44ad;">
                                            💰 LIQUIDADO
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning" style="opacity: 0.6;">
                                            PENDIENTE
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $guia['metodo_ingreso'] === 'EXCEL' ? 'badge-info' : 'badge-warning'; ?>">
                                        <?php echo $guia['metodo_ingreso']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="visualizar.php?id=<?php echo $guia['id']; ?>" class="btn btn-info btn-sm" title="Ver">
                                            <i class='bx bx-show'></i>
                                        </a>
                                        <?php if (in_array($tipo_usuario, ['ADMIN', 'SUPERVISOR'])): ?>
                                        <a href="editar.php?id=<?php echo $guia['id']; ?>" class="btn btn-warning btn-sm" title="Editar">
                                            <i class='bx bx-edit'></i>
                                        </a>
                                        <a href="eliminar.php?id=<?php echo $guia['id']; ?>" class="btn btn-danger btn-sm" title="Eliminar" onclick="return confirm('¿Está seguro de eliminar esta guía?')">
                                            <i class='bx bx-trash'></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($paginacion['total_paginas'] > 1): ?>
                    <div class="pagination">
                        <?php
                        $url_params = [];
                        if (!empty($buscar)) $url_params[] = "buscar=" . urlencode($buscar);
                        if (!empty($filtro_estado)) $url_params[] = "estado=" . urlencode($filtro_estado);
                        if ($filtro_facturacion !== 'TODOS') $url_params[] = "estado_facturacion=" . urlencode($filtro_facturacion);
                        if (!empty($filtro_asesor)) $url_params[] = "asesor=" . urlencode($filtro_asesor);
                        if (!empty($fecha_desde)) $url_params[] = "fecha_desde=" . urlencode($fecha_desde);
                        if (!empty($fecha_hasta)) $url_params[] = "fecha_hasta=" . urlencode($fecha_hasta);
                        $url_query = count($url_params) > 0 ? '&' . implode('&', $url_params) : '';

                        if ($pagina > 1): ?>
                            <a href="?pagina=<?php echo $pagina - 1; ?><?php echo $url_query; ?>">« Anterior</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>
                            <?php if ($i == $pagina): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?pagina=<?php echo $i; ?><?php echo $url_query; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($pagina < $paginacion['total_paginas']): ?>
                            <a href="?pagina=<?php echo $pagina + 1; ?><?php echo $url_query; ?>">Siguiente »</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php else: ?>
                    <div class="empty-state">
                        <i class='bx bx-package'></i>
                        <h3>No hay guías registradas</h3>
                        <p>Comienza agregando guías manualmente o cargando un archivo Excel</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
    <script>
        function cambiarEstado(selectElement) {
            const id = selectElement.getAttribute('data-id');
            const nuevoEstado = selectElement.value;
            const estadoAnterior = selectElement.getAttribute('data-estado-anterior') || selectElement.value;

            // Guardar estado anterior para poder revertir si falla
            selectElement.setAttribute('data-estado-anterior', estadoAnterior);

            // Deshabilitar el select mientras se procesa
            selectElement.disabled = true;

            // Realizar petición AJAX
            fetch('cambiar_estado.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}&estado=${nuevoEstado}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualizar las clases del select según el nuevo estado
                    selectElement.className = 'estado-select';
                    if (nuevoEstado === 'ENTREGADO') {
                        selectElement.classList.add('estado-entregado');
                    } else if (nuevoEstado === 'PENDIENTE') {
                        selectElement.classList.add('estado-pendiente');
                    } else if (nuevoEstado === 'OBSERVADO') {
                        selectElement.classList.add('estado-observado');
                    }

                    // Actualizar el estado anterior
                    selectElement.setAttribute('data-estado-anterior', nuevoEstado);

                    // Mostrar mensaje de éxito (opcional)
                    mostrarNotificacion('Estado actualizado correctamente', 'success');
                } else {
                    // Revertir al estado anterior si falla
                    selectElement.value = estadoAnterior;
                    alert('Error: ' + data.mensaje);
                }
            })
            .catch(error => {
                // Revertir al estado anterior si hay error
                selectElement.value = estadoAnterior;
                alert('Error al actualizar el estado. Por favor intenta nuevamente.');
                console.error('Error:', error);
            })
            .finally(() => {
                // Rehabilitar el select
                selectElement.disabled = false;
            });
        }

        function mostrarNotificacion(mensaje, tipo) {
            // Crear elemento de notificación
            const notif = document.createElement('div');
            notif.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${tipo === 'success' ? '#d4edda' : '#f8d7da'};
                color: ${tipo === 'success' ? '#155724' : '#721c24'};
                border: 2px solid ${tipo === 'success' ? '#28a745' : '#dc3545'};
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                z-index: 9999;
                font-weight: 600;
                animation: slideIn 0.3s ease;
            `;
            notif.textContent = mensaje;
            document.body.appendChild(notif);

            // Remover después de 3 segundos
            setTimeout(() => {
                notif.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notif.remove(), 300);
            }, 3000);
        }

        // Agregar animaciones CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>

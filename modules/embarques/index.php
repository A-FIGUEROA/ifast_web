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

// Filtro de estado de envío (PENDIENTE por defecto)
$filtro_envio = isset($_GET['estado_envio']) ? limpiarDatos($_GET['estado_envio']) : 'PENDIENTE';

// Filtro de fechas
$fecha_desde = isset($_GET['fecha_desde']) ? limpiarDatos($_GET['fecha_desde']) : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? limpiarDatos($_GET['fecha_hasta']) : '';

// Query base
$query_count = "SELECT COUNT(*) as total FROM guias_embarque ge";
$query_select = "
    SELECT ge.*,
           CONCAT(u.nombre, ' ', u.apellido) as usuario_registro
    FROM guias_embarque ge
    LEFT JOIN usuarios u ON ge.creado_por = u.id
";

$conditions = [];
$params = [];

// Búsqueda
if (!empty($buscar)) {
    $conditions[] = "(ge.nro_guia LIKE :buscar1 OR ge.nombre_completo LIKE :buscar2 OR ge.documento LIKE :buscar3)";
    $params[':buscar1'] = "%{$buscar}%";
    $params[':buscar2'] = "%{$buscar}%";
    $params[':buscar3'] = "%{$buscar}%";
}

// Filtro de estado de envío
if ($filtro_envio !== 'TODOS') {
    $conditions[] = "ge.estado_envio = :estado_envio";
    $params[':estado_envio'] = $filtro_envio;
}

// Filtro de fechas
if (!empty($fecha_desde)) {
    $conditions[] = "ge.fecha_creacion >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $conditions[] = "ge.fecha_creacion <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

// Agregar condiciones
if (count($conditions) > 0) {
    $where = " WHERE " . implode(" AND ", $conditions);
    $query_count .= $where;
    $query_select .= $where;
}

$query_select .= " ORDER BY ge.fecha_creacion DESC LIMIT :limit OFFSET :offset";

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
    <title>Gestión de Embarques</title>
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

        .content {
            padding: 30px;
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
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00296b 0%, #00509d 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 80, 157, 0.3);
        }

        .btn-back {
            background: linear-gradient(135deg, #fdc500 0%, #ffd500 100%);
            color: white;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(253, 197, 0, 0.4);
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.85rem;
        }

        .btn-email {
            background: #27ae60;
            color: white;
        }

        .btn-email:hover {
            background: #229954;
        }

        .btn-excel {
            background: #27ae60;
            color: white;
        }

        .btn-excel:hover {
            background: #229954;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-edit:hover {
            background: #2980b9;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        /* SEARCH & FILTERS */
        .filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .date-filter {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .date-filter input {
            padding: 10px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        /* TABLE */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #00296b 0%, #00509d 100%);
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 0.9rem;
        }

        tbody tr {
            transition: all 0.3s;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-pendiente {
            background: #fff3cd;
            color: #856404;
        }

        .badge-enviado {
            background: #d4edda;
            color: #155724;
        }

        .badge-activo {
            background: #d4edda;
            color: #155724;
        }

        .badge-inactivo {
            background: #f8d7da;
            color: #721c24;
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }

        .modal-header h2 {
            color: #2c3e50;
            font-size: 1.5rem;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #00509d;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .archivo-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .archivo-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .archivo-info {
            flex: 1;
        }

        .archivo-nombre {
            font-weight: 600;
            color: #2c3e50;
        }

        .archivo-descripcion {
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .archivo-tamano {
            font-size: 0.8rem;
            color: #95a5a6;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: #2c3e50;
            background: #ecf0f1;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: #00509d;
            color: white;
        }

        .pagination .active {
            background: linear-gradient(135deg, #00296b 0%, #00509d 100%);
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>

    <!-- MAIN -->
    <main class="main-content">
        <header class="header">
            <h1>📦 Gestión de Embarques</h1>
            <div>
                <a href="../../dashboard.php" class="btn btn-back">← Volver</a>
            </div>
        </header>

        <div class="content">
                <!-- FILTER TABS -->
                <div class="filter-tabs">
                    <a href="?estado_envio=PENDIENTE" class="filter-tab <?php echo $filtro_envio === 'PENDIENTE' ? 'active' : ''; ?>">
                        🔴 Pendientes
                    </a>
                    <a href="?estado_envio=ENVIADO" class="filter-tab <?php echo $filtro_envio === 'ENVIADO' ? 'active' : ''; ?>">
                        🟢 Enviados
                    </a>
                    <a href="?estado_envio=TODOS" class="filter-tab <?php echo $filtro_envio === 'TODOS' ? 'active' : ''; ?>">
                        📊 Todos
                    </a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <?php
                            if ($filtro_envio === 'PENDIENTE') {
                                echo '🔴 Embarques Pendientes de Envío';
                            } elseif ($filtro_envio === 'ENVIADO') {
                                echo '🟢 Embarques Enviados';
                            } else {
                                echo '📊 Todos los Embarques';
                            }
                            ?>
                            (<?php echo $total_guias; ?>)
                        </div>
                        <div class="btn-group">
                            <button onclick="abrirModalCrear()" class="btn btn-primary">
                                <i class='bx bx-plus'></i> Nueva Guía
                            </button>
                        </div>
                    </div>

                    <!-- SEARCH & FILTERS -->
                    <form method="GET" class="filters">
                        <input type="hidden" name="estado_envio" value="<?php echo htmlspecialchars($filtro_envio); ?>">
                        <div class="search-box">
                            <input type="text"
                                   name="buscar"
                                   placeholder="🔍 Buscar por N° Guía, Cliente o Documento..."
                                   value="<?php echo htmlspecialchars($buscar); ?>">
                        </div>
                        <div class="date-filter">
                            <input type="date"
                                   name="fecha_desde"
                                   value="<?php echo htmlspecialchars($fecha_desde); ?>"
                                   placeholder="Desde">
                            <input type="date"
                                   name="fecha_hasta"
                                   value="<?php echo htmlspecialchars($fecha_hasta); ?>"
                                   placeholder="Hasta">
                            <button type="submit" class="btn btn-primary btn-small">Filtrar</button>
                            <?php if (!empty($buscar) || !empty($fecha_desde) || !empty($fecha_hasta)): ?>
                                <a href="?estado_envio=<?php echo htmlspecialchars($filtro_envio); ?>" class="btn btn-small" style="background: #95a5a6; color: white;">
                                    Limpiar
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- TABLE -->
                    <div class="table-responsive">
                        <?php if (count($guias) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>N° Guía</th>
                                    <th>Cliente</th>
                                    <th>Documento</th>
                                    <th>Valor USD</th>
                                    <th>Consignatario</th>
                                    <th>Usuario Registro</th>
                                    <th>Estado Envío</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($guias as $guia): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($guia['nro_guia']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($guia['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($guia['documento']); ?></td>
                                    <td><strong>$<?php echo number_format($guia['valor_usd'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($guia['consignatario']); ?></td>
                                    <td>
                                        <span style="color: #00509d; font-weight: 600;">
                                            👤 <?php echo htmlspecialchars($guia['usuario_registro'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($guia['estado_envio'] === 'ENVIADO'): ?>
                                            <span class="badge badge-enviado">
                                                ✅ ENVIADO
                                            </span>
                                            <?php if (!empty($guia['fecha_envio'])): ?>
                                                <div style="font-size: 0.75rem; color: #7f8c8d; margin-top: 3px;">
                                                    <?php echo date('d/m/Y H:i', strtotime($guia['fecha_envio'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-pendiente">
                                                ⏳ PENDIENTE
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($guia['fecha_creacion'])); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <button onclick="abrirModalVer(<?php echo $guia['id_guia']; ?>)"
                                               class="btn btn-small"
                                               style="background: #3498db; color: white;"
                                               title="Ver detalles">
                                                👁️
                                            </button>
                                            <a href="exportar_excel.php?id=<?php echo $guia['id_guia']; ?>"
                                               class="btn btn-small btn-excel"
                                               title="Descargar Excel">
                                                📊
                                            </a>
                                            <?php if ($guia['estado_envio'] === 'PENDIENTE'): ?>
                                            <button onclick="abrirModalCorreo(<?php echo $guia['id_guia']; ?>)"
                                                    class="btn btn-small btn-email"
                                                    title="Enviar por correo">
                                                📧
                                            </button>
                                            <?php endif; ?>
                                            <button onclick="abrirModalEditar(<?php echo $guia['id_guia']; ?>)"
                                               class="btn btn-small btn-edit"
                                               title="Editar">
                                                ✏️
                                            </button>
                                            <button onclick="eliminarEmbarque(<?php echo $guia['id_guia']; ?>)"
                                               class="btn btn-small btn-delete"
                                               title="Eliminar">
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- PAGINATION -->
                        <?php if ($paginacion['total_paginas'] > 1): ?>
                        <div class="pagination">
                            <?php
                            $query_params = $_GET;
                            for ($i = 1; $i <= $paginacion['total_paginas']; $i++):
                                $query_params['pagina'] = $i;
                                $query_string = http_build_query($query_params);
                            ?>
                                <a href="?<?php echo $query_string; ?>"
                                   class="<?php echo $i === $pagina ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-package'></i>
                            <p>No se encontraron embarques <?php
                                if ($filtro_envio === 'PENDIENTE') echo 'pendientes de envío';
                                elseif ($filtro_envio === 'ENVIADO') echo 'enviados';
                                else echo '';
                            ?>.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- MODAL DE ENVÍO DE CORREO -->
    <div id="modalCorreo" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📧 Enviar Embarque por Correo</h2>
                <span class="close" onclick="cerrarModalCorreo()">&times;</span>
            </div>

            <form id="formEnviarCorreo" enctype="multipart/form-data">
                <input type="hidden" id="correo_id_guia" name="id_guia">

                <div class="form-group">
                    <label>Correo(s) Destino *</label>
                    <input type="text"
                           name="correos_destino"
                           placeholder="ejemplo@correo.com, otro@correo.com"
                           required>
                    <small style="color: #7f8c8d;">Separa múltiples correos con comas</small>
                </div>

                <div class="form-group">
                    <label>Archivos a Adjuntar</label>
                    <div id="archivos_automaticos" class="loading">
                        Cargando archivos disponibles...
                    </div>
                </div>

                <div class="form-group">
                    <label>Archivos Adicionales (Opcional)</label>
                    <input type="file" name="archivos_manuales[]" multiple>
                    <small style="color: #7f8c8d;">Puedes seleccionar múltiples archivos</small>
                </div>

                <div class="form-group">
                    <label>Mensaje Personalizado (Opcional)</label>
                    <textarea name="mensaje"
                              placeholder="Escribe un mensaje adicional para el correo..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button"
                            onclick="cerrarModalCorreo()"
                            class="btn"
                            style="background: #95a5a6; color: white;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-email">
                        📧 Enviar Correo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
    <script>
        function abrirModalCorreo(idGuia) {
            document.getElementById('correo_id_guia').value = idGuia;
            document.getElementById('modalCorreo').style.display = 'block';
            cargarArchivosDisponibles(idGuia);
        }

        function cerrarModalCorreo() {
            document.getElementById('modalCorreo').style.display = 'none';
            document.getElementById('formEnviarCorreo').reset();
        }

        function cargarArchivosDisponibles(idGuia) {
            const container = document.getElementById('archivos_automaticos');
            container.innerHTML = '<div class="loading">Cargando archivos disponibles...</div>';

            fetch(`obtener_archivos.php?id_guia=${idGuia}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.archivos.length > 0) {
                        let html = '';
                        data.archivos.forEach(archivo => {
                            html += `
                                <div class="archivo-item">
                                    <input type="checkbox"
                                           name="archivos[]"
                                           value="${archivo.ruta}"
                                           ${archivo.auto_seleccionar ? 'checked' : ''}>
                                    <div class="archivo-info">
                                        <div class="archivo-nombre">${archivo.nombre}</div>
                                        <div class="archivo-descripcion">${archivo.descripcion}</div>
                                        <div class="archivo-tamano">${archivo.tamano}</div>
                                    </div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<div class="empty-state" style="padding: 20px;">No hay archivos disponibles para adjuntar.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<div class="empty-state" style="padding: 20px; color: #e74c3c;">Error al cargar archivos: ' + error.message + '</div>';
                });
        }

        document.getElementById('formEnviarCorreo').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const checkboxes = document.querySelectorAll('input[name="archivos[]"]:checked');
            const archivos = Array.from(checkboxes).map(cb => cb.value);

            // Limpiar los checkboxes del FormData y agregar como array
            formData.delete('archivos[]');
            archivos.forEach(archivo => {
                formData.append('archivos[]', archivo);
            });

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Enviando...';

            fetch('enviar_correo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.mensaje);
                    cerrarModalCorreo();
                    location.reload(); // Recargar para actualizar la lista
                } else {
                    alert('❌ ' + data.mensaje);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error al enviar correo: ' + error.message);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        // Cerrar modal al hacer click fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalCorreo');
            if (event.target == modal) {
                cerrarModalCorreo();
            }
        }
    </script>

    <!-- MODALES ADICIONALES -->
    <?php include 'modales.html'; ?>
</body>
</html>

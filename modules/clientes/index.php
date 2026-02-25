<?php
// modules/clientes/index.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Todos los usuarios pueden acceder
requiereLogin();

$database = new Database();
$conn = $database->getConnection();

// Obtener tipo de usuario
$tipo_usuario = obtenerTipoUsuario();

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;

// Búsqueda
$buscar = isset($_GET['buscar']) ? limpiarDatos($_GET['buscar']) : '';

// Query base con JOIN a usuarios para obtener el nombre del que registró
$query_count = "SELECT COUNT(*) as total FROM clientes c";
$query_select = "SELECT c.*, CONCAT(u.nombre, ' ', u.apellido) AS nombre_usuario
                 FROM clientes c
                 LEFT JOIN usuarios u ON c.creado_por = u.id";

// Si hay búsqueda
if (!empty($buscar)) {
    $where = " WHERE c.nombre_razon_social LIKE :buscar1 OR c.documento LIKE :buscar2 OR c.email LIKE :buscar3";
    $query_count .= $where;
    $query_select .= $where;
}

$query_select .= " ORDER BY creado_en DESC LIMIT :limit OFFSET :offset";

// Total de clientes
$stmt = $conn->prepare($query_count);
if (!empty($buscar)) {
    $buscar_param = "%{$buscar}%";
    $stmt->bindValue(':buscar1', $buscar_param);
    $stmt->bindValue(':buscar2', $buscar_param);
    $stmt->bindValue(':buscar3', $buscar_param);
}
$stmt->execute();
$total_clientes = $stmt->fetch()['total'];

// Calcular paginación
$paginacion = paginar($total_clientes, $registros_por_pagina, $pagina);

// Obtener clientes
$stmt = $conn->prepare($query_select);
if (!empty($buscar)) {
    $buscar_param = "%{$buscar}%";
    $stmt->bindValue(':buscar1', $buscar_param);
    $stmt->bindValue(':buscar2', $buscar_param);
    $stmt->bindValue(':buscar3', $buscar_param);
}
$stmt->bindValue(':limit', $paginacion['registros_por_pagina'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $paginacion['offset'], PDO::PARAM_INT);
$stmt->execute();
$clientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes</title>
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
            background: linear-gradient(135deg, #00296B 0%, #00509D 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-back {
            background: #FDC500;
            color: white;
            margin-left: 10px;
        }

        .content {
            padding: 30px;
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

        .badge-dni {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-ruc {
            background: #f3e5f5;
            color: #7b1fa2;
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

        .btn-files {
            background: #f39c12;
            color: white;
        }

        .btn-excel {
            background: #27ae60;
            color: white;
        }

        .btn-excel:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
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
            table {
                font-size: 0.85rem;
            }
            th, td {
                padding: 10px;
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
            max-width: 800px;
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

        /* Estilos de formulario mejorados */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #2c3e50;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-control:hover {
            border-color: #cbd5e0;
        }

        .form-control:focus {
            outline: none;
            border-color: #00509D;
            box-shadow: 0 0 0 3px rgba(0, 80, 157, 0.1);
            background: #ffffff;
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
        }

        .form-control::placeholder {
            color: #a0aec0;
        }

        .form-control:disabled {
            background: #f7fafc;
            color: #a0aec0;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .required {
            color: #e74c3c;
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

        .modal-footer .btn-primary {
            padding: 12px 24px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .alert-modal {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInDown 0.3s ease;
        }

        .alert-modal.alert-danger {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
            border-left: 4px solid #e74c3c;
        }

        .alert-modal.alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-left: 4px solid #28a745;
        }

        .section-title {
            font-size: 1.1rem;
            color: #2c3e50;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .info-text {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 6px;
            font-style: italic;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>
        <!-- MAIN -->
        <main class="main-content">
            <header class="header">
                <h1>👤 Gestión de Clientes</h1>
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

                <div class="card">
                    <div class="card-header">
                        <h2>Lista de Clientes (<?php echo $total_clientes; ?>)</h2>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <form method="GET" class="search-container">
                                <input type="text" name="buscar" class="search-box" 
                                       placeholder="🔍 Buscar por nombre, documento o email..."
                                       value="<?php echo htmlspecialchars($buscar); ?>">
                                <button type="submit" class="btn btn-search">Buscar</button>
                                <?php if (!empty($buscar)): ?>
                                <a href="index.php" class="btn btn-back">Limpiar</a>
                                <?php endif; ?>
                            </form>
                            <?php if (in_array($tipo_usuario, ['ADMIN', 'SUPERVISOR', 'VENTAS'])): ?>
                            <a href="cargar_excel.php" class="btn btn-excel">📊 Importar Excel</a>
                            <button onclick="abrirModalCrear()" class="btn btn-primary">+ Nuevo Cliente</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (count($clientes) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo Doc</th>
                                    <th>Documento</th>
                                    <th>Nombre / Razón Social</th>
                                    <th>Email</th>
                                    <th>Celular</th>
                                    <th>Distrito</th>
                                    <th>Registrado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td><strong>#<?php echo $cliente['id']; ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($cliente['tipo_documento']); ?>">
                                            <?php echo $cliente['tipo_documento']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $cliente['documento']; ?></td>
                                    <td>
                                        <strong><?php echo $cliente['nombre_razon_social']; ?></strong>
                                        <?php if ($cliente['apellido']): ?>
                                            <br><small style="color: #999;"><?php echo $cliente['apellido']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $cliente['email']; ?></td>
                                    <td><?php echo $cliente['celular']; ?></td>
                                    <td><?php echo $cliente['distrito']; ?></td>
                                    <td>
                                        <?php if (!empty($cliente['nombre_usuario'])): ?>
                                            <span class="badge badge-dni" style="background: #e8f5e9; color: #2e7d32;">
                                                👤 <?php echo htmlspecialchars($cliente['nombre_usuario']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999; font-style: italic;">Sin registro</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="archivos.php?cliente_id=<?php echo $cliente['id']; ?>" 
                                           class="btn btn-small btn-files" title="Archivos">
                                           📄
                                        </a>
                                        <?php if (in_array($tipo_usuario, ['ADMIN', 'SUPERVISOR', 'VENTAS'])): ?>
                                        <button onclick="abrirModalEditar(<?php echo $cliente['id']; ?>)"
                                           class="btn btn-small btn-edit" title="Editar">
                                           ✏️
                                        </button>

                                        <?php // BOTÓN ELIMINAR - SOLO ADMIN ?>
                                        <?php if ($tipo_usuario === 'ADMIN'): ?>
                                        <a href="eliminar.php?id=<?php echo $cliente['id']; ?>"
                                           class="btn btn-small btn-delete" title="Eliminar"
                                           onclick="return confirm('¿Eliminar este cliente y todos sus archivos?')">
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

                        // URL para navegación siguiente
                        $url_siguiente = "?pagina=" . ($pagina + 1);
                        if (!empty($buscar)) {
                            $url_siguiente .= "&buscar=" . urlencode($buscar);
                        }

                        // Calcular registros mostrados
                        $registro_inicio = ($paginacion['offset'] + 1);
                        $registro_fin = min($paginacion['offset'] + $paginacion['registros_por_pagina'], $total_clientes);
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
                            de <strong><?php echo $total_clientes; ?></strong> registros
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
                        <i>👤</i>
                        <h3>No hay clientes registrados</h3>
                        <p>
                            <?php if (!empty($buscar)): ?>
                                No se encontraron resultados para "<?php echo htmlspecialchars($buscar); ?>"
                            <?php else: ?>
                                Comienza agregando tu primer cliente
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL CREAR CLIENTE -->
    <div id="modalCrear" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>+ Crear Nuevo Cliente</h2>
                <button class="close-modal" onclick="cerrarModalCrear()">&times;</button>
            </div>
            <form id="formCrear">
                <div class="modal-body">
                    <div id="alertCrear"></div>

                    <div class="section-title">📋 Información del Documento</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Documento <span class="required">*</span></label>
                            <select class="form-control" name="tipo_documento" id="crear_tipo_documento" required>
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Número de Documento <span class="required">*</span></label>
                            <input type="text" class="form-control" name="documento" id="crear_documento" maxlength="11" required>
                            <small class="info-text">DNI: 8 dígitos | RUC: 11 dígitos</small>
                        </div>
                    </div>

                    <div class="section-title">👤 Datos Personales / Empresa</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre / Razón Social <span class="required">*</span></label>
                            <input type="text" class="form-control" name="nombre_razon_social" required>
                        </div>
                        <div class="form-group">
                            <label>Apellido (opcional)</label>
                            <input type="text" class="form-control" name="apellido">
                        </div>
                    </div>

                    <div class="section-title">📧 Información de Contacto</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Teléfono Fijo (opcional)</label>
                            <input type="text" class="form-control" name="telif">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Celular <span class="required">*</span></label>
                        <input type="text" class="form-control" name="celular" required>
                    </div>

                    <div class="section-title">📍 Dirección</div>
                    <div class="form-group">
                        <label>Dirección Completa <span class="required">*</span></label>
                        <input type="text" class="form-control" name="direccion" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Distrito <span class="required">*</span></label>
                            <input type="text" class="form-control" name="distrito" required>
                        </div>
                        <div class="form-group">
                            <label>Provincia <span class="required">*</span></label>
                            <input type="text" class="form-control" name="provincia" required>
                        </div>
                        <div class="form-group">
                            <label>Departamento <span class="required">*</span></label>
                            <input type="text" class="form-control" name="departamento" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="cerrarModalCrear()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCrear">💾 Guardar Cliente</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDITAR CLIENTE -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Editar Cliente</h2>
                <button class="close-modal" onclick="cerrarModalEditar()">&times;</button>
            </div>
            <form id="formEditar">
                <div class="modal-body">
                    <div id="alertEditar"></div>

                    <input type="hidden" id="editar_id" name="id">

                    <div class="section-title">📋 Información del Documento</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Documento <span class="required">*</span></label>
                            <select class="form-control" name="tipo_documento" id="editar_tipo_documento" required>
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Número de Documento <span class="required">*</span></label>
                            <input type="text" class="form-control" name="documento" id="editar_documento" maxlength="11" required>
                            <small class="info-text">DNI: 8 dígitos | RUC: 11 dígitos</small>
                        </div>
                    </div>

                    <div class="section-title">👤 Datos Personales / Empresa</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre / Razón Social <span class="required">*</span></label>
                            <input type="text" class="form-control" name="nombre_razon_social" id="editar_nombre_razon_social" required>
                        </div>
                        <div class="form-group">
                            <label>Apellido (opcional)</label>
                            <input type="text" class="form-control" name="apellido" id="editar_apellido">
                        </div>
                    </div>

                    <div class="section-title">📧 Información de Contacto</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" class="form-control" name="email" id="editar_email" required>
                        </div>
                        <div class="form-group">
                            <label>Teléfono Fijo (opcional)</label>
                            <input type="text" class="form-control" name="telif" id="editar_telif">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Celular <span class="required">*</span></label>
                        <input type="text" class="form-control" name="celular" id="editar_celular" required>
                    </div>

                    <div class="section-title">📍 Dirección</div>
                    <div class="form-group">
                        <label>Dirección Completa <span class="required">*</span></label>
                        <input type="text" class="form-control" name="direccion" id="editar_direccion" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Distrito <span class="required">*</span></label>
                            <input type="text" class="form-control" name="distrito" id="editar_distrito" required>
                        </div>
                        <div class="form-group">
                            <label>Provincia <span class="required">*</span></label>
                            <input type="text" class="form-control" name="provincia" id="editar_provincia" required>
                        </div>
                        <div class="form-group">
                            <label>Departamento <span class="required">*</span></label>
                            <input type="text" class="form-control" name="departamento" id="editar_departamento" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="cerrarModalEditar()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarEditar">💾 Actualizar Cliente</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>

    <script>
        // ========================================
        // FUNCIONES MODAL CREAR
        // ========================================
        function abrirModalCrear() {
            document.getElementById('modalCrear').classList.add('active');
            document.getElementById('formCrear').reset();
            document.getElementById('alertCrear').innerHTML = '';
        }

        function cerrarModalCrear() {
            document.getElementById('modalCrear').classList.remove('active');
            document.getElementById('formCrear').reset();
            document.getElementById('alertCrear').innerHTML = '';
        }

        // ========================================
        // FUNCIONES MODAL EDITAR
        // ========================================
        function abrirModalEditar(id) {
            const modal = document.getElementById('modalEditar');
            modal.classList.add('active');
            document.getElementById('alertEditar').innerHTML = '';

            // Mostrar carga
            mostrarAlerta('alertEditar', 'Cargando datos...', 'success');

            // Cargar datos
            fetch('editar.php?id=' + id + '&ajax=1')
                .then(response => response.text())
                .then(text => {
                    console.log('Response:', text);
                    const data = JSON.parse(text);

                    if (data.success) {
                        document.getElementById('editar_id').value = data.cliente.id;
                        document.getElementById('editar_tipo_documento').value = data.cliente.tipo_documento;
                        document.getElementById('editar_documento').value = data.cliente.documento;
                        document.getElementById('editar_nombre_razon_social').value = data.cliente.nombre_razon_social;
                        document.getElementById('editar_apellido').value = data.cliente.apellido || '';
                        document.getElementById('editar_email').value = data.cliente.email;
                        document.getElementById('editar_telif').value = data.cliente.telif || '';
                        document.getElementById('editar_celular').value = data.cliente.celular;
                        document.getElementById('editar_direccion').value = data.cliente.direccion;
                        document.getElementById('editar_distrito').value = data.cliente.distrito;
                        document.getElementById('editar_provincia').value = data.cliente.provincia;
                        document.getElementById('editar_departamento').value = data.cliente.departamento;
                        document.getElementById('alertEditar').innerHTML = '';
                    } else {
                        mostrarAlerta('alertEditar', data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarAlerta('alertEditar', 'Error al cargar datos', 'danger');
                });
        }

        function cerrarModalEditar() {
            document.getElementById('modalEditar').classList.remove('active');
            document.getElementById('formEditar').reset();
            document.getElementById('alertEditar').innerHTML = '';
        }

        // ========================================
        // CERRAR MODAL AL CLICK FUERA
        // ========================================
        window.onclick = function(event) {
            const modalCrear = document.getElementById('modalCrear');
            const modalEditar = document.getElementById('modalEditar');

            if (event.target === modalCrear) {
                cerrarModalCrear();
            }
            if (event.target === modalEditar) {
                cerrarModalEditar();
            }
        }

        // ========================================
        // FUNCIÓN MOSTRAR ALERTAS
        // ========================================
        function mostrarAlerta(elementId, mensaje, tipo) {
            const alertDiv = document.getElementById(elementId);
            alertDiv.innerHTML = `
                <div class="alert-modal alert-${tipo}">
                    ${tipo === 'success' ? '✓' : '✗'} ${mensaje}
                </div>
            `;

            if (tipo === 'success') {
                setTimeout(() => {
                    alertDiv.innerHTML = '';
                }, 5000);
            }
        }

        // ========================================
        // MANEJO FORMULARIO CREAR
        // ========================================
        document.getElementById('formCrear').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('ajax', '1');
            const btnGuardar = document.getElementById('btnGuardarCrear');
            const textoOriginal = btnGuardar.innerHTML;

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner"></span> Guardando...';

            fetch('crear.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta('alertCrear', data.message, 'success');

                    setTimeout(() => {
                        cerrarModalCrear();
                        window.location.reload();
                    }, 1000);
                } else {
                    mostrarAlerta('alertCrear', data.message, 'danger');
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = textoOriginal;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('alertCrear', 'Error al crear el cliente', 'danger');
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
            });
        });

        // ========================================
        // MANEJO FORMULARIO EDITAR
        // ========================================
        document.getElementById('formEditar').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('ajax', '1');
            const btnGuardar = document.getElementById('btnGuardarEditar');
            const textoOriginal = btnGuardar.innerHTML;

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner"></span> Actualizando...';

            fetch('editar.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta('alertEditar', data.message, 'success');

                    setTimeout(() => {
                        cerrarModalEditar();
                        window.location.reload();
                    }, 1000);
                } else {
                    mostrarAlerta('alertEditar', data.message, 'danger');
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = textoOriginal;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('alertEditar', 'Error al actualizar el cliente', 'danger');
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
            });
        });

        // Validar documento según tipo (para crear)
        document.getElementById('crear_tipo_documento').addEventListener('change', function() {
            const docInput = document.getElementById('crear_documento');
            docInput.maxLength = this.value === 'DNI' ? 8 : 11;
        });

        // Validar documento según tipo (para editar)
        document.getElementById('editar_tipo_documento').addEventListener('change', function() {
            const docInput = document.getElementById('editar_documento');
            docInput.maxLength = this.value === 'DNI' ? 8 : 11;
        });

        // Solo números en documento (crear)
        document.getElementById('crear_documento').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Solo números en documento (editar)
        document.getElementById('editar_documento').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>
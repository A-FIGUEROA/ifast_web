<?php
// modules/usuarios/index.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Solo ADMIN puede acceder
requierePermiso(['ADMIN']);

$database = new Database();
$conn = $database->getConnection();

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;

// Búsqueda
$buscar = isset($_GET['buscar']) ? limpiarDatos($_GET['buscar']) : '';

// Query base
$query_count = "SELECT COUNT(*) as total FROM usuarios";
$query_select = "SELECT id, nombre, apellido, dni, email, tipo, creado_en FROM usuarios";

// Si hay búsqueda
if (!empty($buscar)) {
    $where = " WHERE nombre LIKE :buscar1 OR apellido LIKE :buscar2 OR dni LIKE :buscar3 OR email LIKE :buscar4";
    $query_count .= $where;
    $query_select .= $where;
}

$query_select .= " ORDER BY creado_en DESC LIMIT :limit OFFSET :offset";

// Total de usuarios
$stmt = $conn->prepare($query_count);
if (!empty($buscar)) {
    $buscar_param = "%{$buscar}%";
    $stmt->bindValue(':buscar1', $buscar_param);
    $stmt->bindValue(':buscar2', $buscar_param);
    $stmt->bindValue(':buscar3', $buscar_param);
    $stmt->bindValue(':buscar4', $buscar_param);
}
$stmt->execute();
$total_usuarios = $stmt->fetch()['total'];

// Calcular paginación
$paginacion = paginar($total_usuarios, $registros_por_pagina, $pagina);

// Obtener usuarios
$stmt = $conn->prepare($query_select);
if (!empty($buscar)) {
    $buscar_param = "%{$buscar}%";
    $stmt->bindValue(':buscar1', $buscar_param);
    $stmt->bindValue(':buscar2', $buscar_param);
    $stmt->bindValue(':buscar3', $buscar_param);
    $stmt->bindValue(':buscar4', $buscar_param);
}
$stmt->bindValue(':limit', $paginacion['registros_por_pagina'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $paginacion['offset'], PDO::PARAM_INT);
$stmt->execute();
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gesti贸n de Usuarios</title>
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


        /* MAIN */
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
        }

        .btn-back:hover {
            background: #7f8c8d;
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
        }

        .card-header h2 {
            font-size: 1.5rem;
            color: #2c3e50;
        }

        .search-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-box {
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            width: 300px;
            font-size: 1rem;
        }

        .search-box:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-search {
            background: linear-gradient(135deg, #00296b 0%, #00509d 100%);
            color: white;
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

        .badge-admin {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-supervisor {
            background: #e3f2fd;
            color: #1565c0;
        }

        .badge-ventas {
            background: #fff3e0;
            color: #e65100;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 0.85rem;
            margin-right: 5px;
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

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
        }

        /* ESTILOS PARA MODALS */
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
            max-width: 600px;
            width: 90%;
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

        /* Spinner de carga */
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
                <h1> Gestión de Usuarios</h1>
                <div>
                    <a href="../../dashboard.php" class="btn btn-back">← Volver</a>
                </div>
            </header>

            <div class="content">
                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    鉁?<?php echo htmlspecialchars($_GET['success']); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    鉁?<?php echo htmlspecialchars($_GET['error']); ?>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h2>Lista de Usuarios (<?php echo $total_usuarios; ?>)</h2>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <form method="GET" class="search-container">
                                <input type="text" name="buscar" class="search-box"
                                       placeholder="🔍 Buscar por nombre, DNI o email..."
                                       value="<?php echo htmlspecialchars($buscar); ?>">
                                <button type="submit" class="btn btn-search">Buscar</button>
                                <?php if (!empty($buscar)): ?>
                                <a href="index.php" class="btn btn-back">Limpiar</a>
                                <?php endif; ?>
                            </form>
                            <button onclick="abrirModalCrear()" class="btn btn-primary">+ Nuevo Usuario</button>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre Completo</th>
                                <th>DNI</th>
                                <th>Email</th>
                                <th>Tipo</th>
                                <th>Registrado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><strong>#<?php echo $usuario['id']; ?></strong></td>
                                <td><?php echo $usuario['nombre'] . ' ' . $usuario['apellido']; ?></td>
                                <td><?php echo $usuario['dni']; ?></td>
                                <td><?php echo $usuario['email']; ?></td>
                                <td>
                                    <?php 
                                    $badge_class = 'badge-' . strtolower($usuario['tipo']);
                                    echo '<span class="badge ' . $badge_class . '">' . $usuario['tipo'] . '</span>';
                                    ?>
                                </td>
                                <td><?php echo formatearFecha($usuario['creado_en']); ?></td>
                                <td>
                                    <button onclick="abrirModalEditar(<?php echo $usuario['id']; ?>)"
                                       class="btn btn-small btn-edit"><box-icon name='edit' type='solid' color= 'white'></box-icon></button>
                                    <a href="eliminar.php?id=<?php echo $usuario['id']; ?>"
                                       class="btn btn-small btn-delete"
                                       onclick="return confirm('¿Estás seguro de eliminar este usuario?')">
                                       <box-icon name='trash' color='white' ></box-icon>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

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
                        $registro_fin = min($paginacion['offset'] + $paginacion['registros_por_pagina'], $total_usuarios);
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
                            de <strong><?php echo $total_usuarios; ?></strong> registros
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
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL CREAR USUARIO -->
    <div id="modalCrear" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>+ Crear Nuevo Usuario</h2>
                <button class="close-modal" onclick="cerrarModalCrear()">&times;</button>
            </div>
            <form id="formCrear">
                <div class="modal-body">
                    <div id="alertCrear"></div>

                    <div class="form-group">
                        <label for="crear_nombre">Nombre *</label>
                        <input type="text" class="form-control" id="crear_nombre" name="nombre" required>
                    </div>

                    <div class="form-group">
                        <label for="crear_apellido">Apellido *</label>
                        <input type="text" class="form-control" id="crear_apellido" name="apellido" required>
                    </div>

                    <div class="form-group">
                        <label for="crear_dni">DNI *</label>
                        <input type="text" class="form-control" id="crear_dni" name="dni" maxlength="8" required>
                    </div>

                    <div class="form-group">
                        <label for="crear_email">Email *</label>
                        <input type="email" class="form-control" id="crear_email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="crear_password">Contraseña *</label>
                        <input type="password" class="form-control" id="crear_password" name="password" required>
                        <small class="info-text">Mínimo 6 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label for="crear_tipo">Tipo de Usuario *</label>
                        <select class="form-control" id="crear_tipo" name="tipo" required>
                            <option value="">Seleccionar...</option>
                            <option value="ADMIN">Administrador</option>
                            <option value="SUPERVISOR">Supervisor</option>
                            <option value="VENTAS">Ventas</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="cerrarModalCrear()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCrear">
                        💾 Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDITAR USUARIO -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Editar Usuario</h2>
                <button class="close-modal" onclick="cerrarModalEditar()">&times;</button>
            </div>
            <form id="formEditar">
                <div class="modal-body">
                    <div id="alertEditar"></div>

                    <input type="hidden" id="editar_id" name="id">

                    <div class="form-group">
                        <label for="editar_nombre">Nombre *</label>
                        <input type="text" class="form-control" id="editar_nombre" name="nombre" required>
                    </div>

                    <div class="form-group">
                        <label for="editar_apellido">Apellido *</label>
                        <input type="text" class="form-control" id="editar_apellido" name="apellido" required>
                    </div>

                    <div class="form-group">
                        <label for="editar_dni">DNI *</label>
                        <input type="text" class="form-control" id="editar_dni" name="dni" maxlength="8" required>
                    </div>

                    <div class="form-group">
                        <label for="editar_email">Email *</label>
                        <input type="email" class="form-control" id="editar_email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="editar_password">Nueva Contraseña (opcional)</label>
                        <input type="password" class="form-control" id="editar_password" name="password">
                        <small class="info-text">Dejar vacío para mantener la actual</small>
                    </div>

                    <div class="form-group">
                        <label for="editar_tipo">Tipo de Usuario *</label>
                        <select class="form-control" id="editar_tipo" name="tipo" required>
                            <option value="ADMIN">Administrador</option>
                            <option value="SUPERVISOR">Supervisor</option>
                            <option value="VENTAS">Ventas</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="cerrarModalEditar()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarEditar">
                        💾 Actualizar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>

    <script>
        // ========================================
        // FUNCIONES PARA MODAL CREAR
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
        // FUNCIONES PARA MODAL EDITAR
        // ========================================
        function abrirModalEditar(id) {
            const modal = document.getElementById('modalEditar');
            modal.classList.add('active');
            document.getElementById('alertEditar').innerHTML = '';

            // Mostrar mensaje de carga
            mostrarAlerta('alertEditar', 'Cargando datos...', 'success');

            // Cargar datos del usuario
            fetch('editar.php?id=' + id + '&ajax=1')
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error('Error HTTP: ' + response.status);
                    }
                    return response.text(); // Primero obtener como texto para ver qué viene
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed data:', data);

                        if (data.success) {
                            document.getElementById('editar_id').value = data.usuario.id;
                            document.getElementById('editar_nombre').value = data.usuario.nombre;
                            document.getElementById('editar_apellido').value = data.usuario.apellido;
                            document.getElementById('editar_dni').value = data.usuario.dni;
                            document.getElementById('editar_email').value = data.usuario.email;
                            document.getElementById('editar_tipo').value = data.usuario.tipo;
                            document.getElementById('editar_password').value = '';
                            // Limpiar alerta de carga
                            document.getElementById('alertEditar').innerHTML = '';
                        } else {
                            mostrarAlerta('alertEditar', data.message || 'Error desconocido', 'danger');
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        console.error('Texto recibido:', text);
                        mostrarAlerta('alertEditar', 'Error: La respuesta no es JSON válido. Revisa la consola.', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    mostrarAlerta('alertEditar', 'Error al cargar: ' + error.message, 'danger');
                });
        }

        function cerrarModalEditar() {
            document.getElementById('modalEditar').classList.remove('active');
            document.getElementById('formEditar').reset();
            document.getElementById('alertEditar').innerHTML = '';
        }

        // ========================================
        // CERRAR MODAL AL HACER CLICK FUERA
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
        // FUNCIÓN PARA MOSTRAR ALERTAS
        // ========================================
        function mostrarAlerta(elementId, mensaje, tipo) {
            const alertDiv = document.getElementById(elementId);
            alertDiv.innerHTML = `
                <div class="alert-modal alert-${tipo}">
                    ${tipo === 'success' ? '✓' : '✗'} ${mensaje}
                </div>
            `;

            // Auto-ocultar después de 5 segundos
            if (tipo === 'success') {
                setTimeout(() => {
                    alertDiv.innerHTML = '';
                }, 5000);
            }
        }

        // ========================================
        // MANEJO DE FORMULARIO CREAR
        // ========================================
        document.getElementById('formCrear').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('ajax', '1'); // Indicador de petición AJAX
            const btnGuardar = document.getElementById('btnGuardarCrear');
            const textoOriginal = btnGuardar.innerHTML;

            // Deshabilitar botón y mostrar spinner
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

                    // Cerrar modal y recargar página después de 1 segundo
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
                mostrarAlerta('alertCrear', 'Error al crear el usuario', 'danger');
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
            });
        });

        // ========================================
        // MANEJO DE FORMULARIO EDITAR
        // ========================================
        document.getElementById('formEditar').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('ajax', '1'); // Indicador de petición AJAX
            const btnGuardar = document.getElementById('btnGuardarEditar');
            const textoOriginal = btnGuardar.innerHTML;

            // Deshabilitar botón y mostrar spinner
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

                    // Cerrar modal y recargar página después de 1 segundo
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
                mostrarAlerta('alertEditar', 'Error al actualizar el usuario', 'danger');
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
            });
        });
    </script>
</body>
</html>

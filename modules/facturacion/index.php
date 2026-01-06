<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();

// Paginación
$registros_por_pagina = 15;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $registros_por_pagina;

// Búsqueda y filtros
$buscar = isset($_GET['buscar']) ? limpiarDatos($_GET['buscar']) : '';
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Query base
$query_count = "SELECT COUNT(*) as total
                FROM documentos_facturacion df
                INNER JOIN clientes c ON df.cliente_id = c.id
                WHERE 1=1";

$query_select = "SELECT df.*, df.imagen_adjunta,
                        c.nombre_razon_social, c.apellido, c.documento,
                        u.nombre as usuario_nombre, u.apellido as usuario_apellido
                 FROM documentos_facturacion df
                 INNER JOIN clientes c ON df.cliente_id = c.id
                 LEFT JOIN usuarios u ON df.creado_por = u.id
                 WHERE 1=1";

$params = [];

// Aplicar búsqueda
if (!empty($buscar)) {
    $query_count .= " AND (df.numero_documento LIKE :buscar
                          OR c.nombre_razon_social LIKE :buscar
                          OR c.documento LIKE :buscar)";
    $query_select .= " AND (df.numero_documento LIKE :buscar
                           OR c.nombre_razon_social LIKE :buscar
                           OR c.documento LIKE :buscar)";
    $params[':buscar'] = "%$buscar%";
}

// Aplicar filtro por tipo
if (!empty($tipo_filtro)) {
    $query_count .= " AND df.tipo_documento = :tipo";
    $query_select .= " AND df.tipo_documento = :tipo";
    $params[':tipo'] = $tipo_filtro;
}

// Contar total de registros
$stmt = $conn->prepare($query_count);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_registros = $stmt->fetch()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener registros
$query_select .= " ORDER BY df.creado_en DESC LIMIT :offset, :limit";
$stmt = $conn->prepare($query_select);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->execute();
$documentos = $stmt->fetchAll();

$tipo_usuario = obtenerTipoUsuario();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturación</title>
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

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            margin-right: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00296b 0%, #00509d 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 80, 157, 0.4);
        }

        .btn-back {
            background: #95a5a6;
            color: white;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.9rem;
        }

        .btn-view { background: #3498db; color: white; }
        .btn-edit { background: #f39c12; color: white; }
        .btn-delete { background: #e74c3c; color: white; }
        .btn-download { background: #27ae60; color: white; }
        .btn-email { background: #9b59b6; color: white; }

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
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f5f7fa;
        }

        .card-header h2 {
            font-size: 1.3rem;
            color: #2c3e50;
        }

        .search-container {
            display: flex;
            gap: 10px;
            align-items: center;
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
            border-color: #00509d;
        }

        .btn-search {
            padding: 10px 20px;
            background: #00509d;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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

        .badge-factura { background: #e3f2fd; color: #1976d2; }
        .badge-boleta { background: #f3e5f5; color: #7b1fa2; }
        .badge-recibo { background: #e8f5e9; color: #388e3c; }

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

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #00509d;
            color: white;
        }

        .pagination .active {
            background: #00296b;
            color: white;
            border-color: #00296b;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .filter-btn:hover {
            border-color: #00509d;
        }

        .filter-btn.active {
            background: #00296b;
            color: white;
            border-color: #00296b;
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1>📄 Gestión de Facturación</h1>
            <div>
                <a href="crear.php" class="btn btn-primary">+ Nuevo Documento</a>
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
                    <h2>Lista de Documentos (<?php echo $total_registros; ?>)</h2>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <form method="GET" class="search-container">
                            <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo_filtro); ?>">
                            <input type="text" name="buscar" class="search-box"
                                   placeholder="🔍 Buscar por número, cliente..."
                                   value="<?php echo htmlspecialchars($buscar); ?>">
                            <button type="submit" class="btn-search">Buscar</button>
                            <?php if (!empty($buscar) || !empty($tipo_filtro)): ?>
                            <a href="index.php" class="btn btn-back btn-small">Limpiar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Filtros por tipo -->
                <div class="filter-buttons">
                    <a href="index.php" class="filter-btn <?php echo empty($tipo_filtro) ? 'active' : ''; ?>">
                        📋 Todos
                    </a>
                    <a href="?tipo=FACTURA" class="filter-btn <?php echo $tipo_filtro === 'FACTURA' ? 'active' : ''; ?>">
                        📄 Facturas
                    </a>
                    <a href="?tipo=BOLETA" class="filter-btn <?php echo $tipo_filtro === 'BOLETA' ? 'active' : ''; ?>">
                        🧾 Boletas
                    </a>
                    <a href="?tipo=RECIBO" class="filter-btn <?php echo $tipo_filtro === 'RECIBO' ? 'active' : ''; ?>">
                        📃 Recibos
                    </a>
                </div>

                <?php if (count($documentos) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>N° Documento</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Archivo PDF</th>
                                <th>Fecha</th>
                                <th>Usuario Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documentos as $doc): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($doc['tipo_documento']); ?>">
                                        <?php echo $doc['tipo_documento']; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $doc['numero_documento']; ?></strong></td>
                                <td>
                                    <?php
                                    echo $doc['nombre_razon_social'];
                                    if ($doc['apellido']) echo ' ' . $doc['apellido'];
                                    ?>
                                    <br><small style="color: #999;"><?php echo $doc['documento']; ?></small>
                                </td>
                                <td><strong>$<?php echo number_format($doc['total'], 2); ?></strong></td>
                                <td>
                                    <small><?php echo $doc['nombre_archivo']; ?></small>
                                    <?php if (!empty($doc['imagen_adjunta'])): ?>
                                    <br><span style="color: #27ae60; font-size: 0.85rem;">📷 Imagen adjunta</span>
                                    <?php endif; ?>
                                    <br>
                                    <?php if ($doc['estado_envio'] === 'ENVIADO'): ?>
                                    <span style="color: #27ae60; font-size: 0.85rem;">✅ Enviado</span>
                                    <?php else: ?>
                                    <span style="color: #95a5a6; font-size: 0.85rem;">⏳ Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatearFecha($doc['creado_en'], 'd/m/Y'); ?></td>
                                <td>
                                    <span style="color: #00509d; font-weight: 600;">
                                        👤 <?php
                                        if ($doc['usuario_nombre']) {
                                            echo htmlspecialchars($doc['usuario_nombre'] . ' ' . $doc['usuario_apellido']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="visualizar.php?id=<?php echo $doc['id']; ?>"
                                       class="btn btn-small btn-view" title="Ver documento">
                                       👁️
                                    </a>
                                    <a href="descargar.php?id=<?php echo $doc['id']; ?>"
                                       class="btn btn-small btn-download" title="Descargar PDF">
                                       ⬇️
                                    </a>
                                    <button onclick="abrirModalEnviarCorreo(<?php echo $doc['id']; ?>, '<?php echo addslashes($doc['numero_documento']); ?>', '<?php echo addslashes($doc['email'] ?? ''); ?>', '<?php echo !empty($doc['imagen_adjunta']) ? '1' : '0'; ?>')"
                                       class="btn btn-small btn-email" title="Enviar por correo">
                                       📧
                                    </button>
                                    <a href="editar.php?id=<?php echo $doc['id']; ?>"
                                       class="btn btn-small btn-edit" title="Editar">
                                       ✏️
                                    </a>
                                    <?php if ($tipo_usuario !== 'VENTAS'): ?>
                                    <a href="eliminar.php?id=<?php echo $doc['id']; ?>"
                                       class="btn btn-small btn-delete" title="Eliminar"
                                       onclick="return confirm('¿Eliminar el documento <?php echo $doc['numero_documento']; ?>?')">
                                       🗑️
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php
                    $url_base = "?pagina=";
                    if (!empty($buscar)) $url_base .= "&buscar=" . urlencode($buscar);
                    if (!empty($tipo_filtro)) $url_base .= "&tipo=" . urlencode($tipo_filtro);
                    ?>

                    <?php if ($pagina > 1): ?>
                    <a href="<?php echo $url_base . ($pagina - 1); ?>">← Anterior</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <?php if ($i == $pagina): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $url_base . $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($pagina < $total_paginas): ?>
                    <a href="<?php echo $url_base . ($pagina + 1); ?>">Siguiente →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="empty-state">
                    <p style="font-size: 3rem;">📄</p>
                    <p>No hay documentos registrados</p>
                    <p><a href="crear.php" class="btn btn-primary" style="margin-top: 15px;">+ Crear primer documento</a></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- MODAL ENVIAR CORREO -->
    <div id="modalEnviarCorreo" class="modal-correo">
        <div class="modal-content-correo">
            <div class="modal-header-correo">
                <h2>📧 Enviar Documento por Correo</h2>
                <span class="close-correo" onclick="cerrarModalCorreo()">&times;</span>
            </div>
            <form id="formEnviarCorreo">
                <input type="hidden" id="correo_id_documento" name="id_documento">

                <div class="form-group-correo">
                    <label>Correos Destino *</label>
                    <input type="text"
                           id="correo_destino"
                           name="correos_destino"
                           placeholder="ejemplo@correo.com, otro@correo.com"
                           required>
                    <small style="color: #7f8c8d;">Separa múltiples correos con comas</small>
                </div>

                <div class="form-group-correo" id="checkbox-imagen-container" style="display: none;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="incluir_imagen" id="incluir_imagen" value="1">
                        <span>Incluir imagen adjunta en el correo</span>
                    </label>
                </div>

                <div class="form-group-correo">
                    <label>Mensaje Personalizado (opcional)</label>
                    <textarea name="mensaje"
                              id="correo_mensaje"
                              rows="5"
                              placeholder="Escribe un mensaje adicional para el correo..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="cerrarModalCorreo()" class="btn btn-back">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        📧 Enviar Correo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .modal-correo {
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

        .modal-content-correo {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .modal-header-correo {
            padding: 20px 30px;
            border-bottom: 2px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header-correo h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.5rem;
        }

        .close-correo {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-correo:hover {
            color: #000;
        }

        .modal-content-correo form {
            padding: 30px;
        }

        .form-group-correo {
            margin-bottom: 20px;
        }

        .form-group-correo label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group-correo input[type="text"],
        .form-group-correo textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-group-correo input[type="text"]:focus,
        .form-group-correo textarea:focus {
            outline: none;
            border-color: #00509d;
        }

        .form-group-correo input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
    </style>

    <script>
        function abrirModalEnviarCorreo(idDocumento, numeroDocumento, emailCliente, tieneImagen) {
            document.getElementById('modalEnviarCorreo').style.display = 'block';
            document.getElementById('correo_id_documento').value = idDocumento;
            document.getElementById('correo_destino').value = emailCliente;
            document.getElementById('correo_mensaje').value = '';

            // Mostrar checkbox de imagen si el documento tiene imagen adjunta
            if (tieneImagen === '1') {
                document.getElementById('checkbox-imagen-container').style.display = 'block';
                document.getElementById('incluir_imagen').checked = true;
            } else {
                document.getElementById('checkbox-imagen-container').style.display = 'none';
                document.getElementById('incluir_imagen').checked = false;
            }
        }

        function cerrarModalCorreo() {
            document.getElementById('modalEnviarCorreo').style.display = 'none';
            document.getElementById('formEnviarCorreo').reset();
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalEnviarCorreo');
            if (event.target == modal) {
                cerrarModalCorreo();
            }
        }

        // Enviar correo
        document.getElementById('formEnviarCorreo').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando...';

            fetch('enviar_correo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.mensaje);
                    cerrarModalCorreo();
                    location.reload();
                } else {
                    alert('❌ ' + data.mensaje);
                }
            })
            .catch(error => {
                alert('❌ Error al enviar correo: ' + error.message);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '📧 Enviar Correo';
            });
        });
    </script>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</body>
</html>

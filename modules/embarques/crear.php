<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();
$tipo_usuario = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

// Obtener lista de clientes
$stmt_clientes = $conn->query("
    SELECT id, tipo_documento, documento,
           CONCAT(nombre_razon_social, ' ', COALESCE(apellido, '')) as nombre_completo
    FROM clientes
    ORDER BY nombre_razon_social ASC
");
$clientes = $stmt_clientes->fetchAll();

// Generar número de guía automático
$year = date('Y');
$stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM guias_embarque WHERE YEAR(fecha_creacion) = :year");
$stmt_count->bindParam(':year', $year);
$stmt_count->execute();
$count = $stmt_count->fetch()['total'];
$nro_guia_sugerido = 'EMB-' . $year . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos del formulario
    $nro_guia = limpiarDatos($_POST['nro_guia']);
    $cliente_id = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
    $proveedor = limpiarDatos($_POST['proveedor'] ?? '');
    $valor_usd = (float)($_POST['valor_usd'] ?? 0);
    $contenido = limpiarDatos($_POST['contenido'] ?? '');
    $indicaciones = limpiarDatos($_POST['indicaciones'] ?? '');
    $trackings_seleccionados = isset($_POST['trackings']) ? $_POST['trackings'] : [];

    // Validaciones
    if (empty($nro_guia)) {
        $errores[] = "El número de guía es obligatorio";
    }

    if ($cliente_id === null) {
        $errores[] = "Debe seleccionar un cliente";
    }

    if ($valor_usd < 0) {
        $errores[] = "El valor USD no puede ser negativo";
    }

    if (empty($trackings_seleccionados)) {
        $errores[] = "Debe seleccionar al menos un tracking";
    }

    // Si no hay errores, insertar
    if (count($errores) === 0) {
        try {
            // Obtener datos del cliente
            $stmt_cliente = $conn->prepare("SELECT tipo_documento, documento, CONCAT(nombre_razon_social, ' ', COALESCE(apellido, '')) as nombre_completo FROM clientes WHERE id = :id");
            $stmt_cliente->bindParam(':id', $cliente_id);
            $stmt_cliente->execute();
            $cliente = $stmt_cliente->fetch();

            $conn->beginTransaction();

            // Insertar guía
            $stmt = $conn->prepare("
                INSERT INTO guias_embarque
                (nro_guia, cliente_id, documento, nombre_completo, tipo_documento, consignatario,
                 proveedor, contenido, valor_usd, indicaciones, estado)
                VALUES
                (:nro_guia, :cliente_id, :documento, :nombre_completo, :tipo_documento, :consignatario,
                 :proveedor, :contenido, :valor_usd, :indicaciones, 'ACTIVO')
            ");

            $stmt->bindParam(':nro_guia', $nro_guia);
            $stmt->bindParam(':cliente_id', $cliente_id);
            $stmt->bindParam(':documento', $cliente['documento']);
            $stmt->bindParam(':nombre_completo', $cliente['nombre_completo']);
            $stmt->bindParam(':tipo_documento', $cliente['tipo_documento']);
            $stmt->bindParam(':consignatario', $cliente['nombre_completo']); // Auto-llenar
            $stmt->bindParam(':proveedor', $proveedor);
            $stmt->bindParam(':contenido', $contenido);
            $stmt->bindParam(':valor_usd', $valor_usd);
            $stmt->bindParam(':indicaciones', $indicaciones);

            $stmt->execute();
            $id_guia = $conn->lastInsertId();

            // Asociar trackings
            $stmt_tracking = $conn->prepare("INSERT INTO guia_pedidos (id_guia, tracking_id) VALUES (:id_guia, :tracking_id)");
            foreach ($trackings_seleccionados as $tracking_id) {
                $stmt_tracking->bindParam(':id_guia', $id_guia);
                $stmt_tracking->bindParam(':tracking_id', $tracking_id);
                $stmt_tracking->execute();
            }

            $conn->commit();
            header("Location: ver.php?id=" . $id_guia);
            exit();

        } catch (PDOException $e) {
            $conn->rollBack();
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Embarque</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 260px; }
        .header { background: white; padding: 20px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .header-left h1 { font-size: 1.8rem; color: #2c3e50; font-weight: 600; }
        .content { padding: 30px; }
        .card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); max-width: 900px; margin: 0 auto; }
        .card-title { font-size: 1.5rem; color: #00296b; font-weight: 600; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .section-title { font-size: 1.1rem; color: #2c3e50; font-weight: 600; margin: 25px 0 15px 0; display: flex; align-items: center; gap: 8px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert ul { margin: 10px 0 0 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-row.full { grid-template-columns: 1fr; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; color: #2c3e50; margin-bottom: 8px; font-size: 0.9rem; }
        .form-group label .required { color: #e74c3c; }
        .form-group input, .form-group select, .form-group textarea { padding: 12px; border: 2px solid #ecf0f1; border-radius: 8px; font-size: 0.9rem; transition: border 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #00509d; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group small { font-size: 0.8rem; color: #7f8c8d; margin-top: 5px; }

        .trackings-container { background: #f8f9fa; border-radius: 8px; padding: 20px; min-height: 150px; }
        .trackings-empty { text-align: center; color: #7f8c8d; padding: 40px; }
        .trackings-empty i { font-size: 3rem; opacity: 0.3; margin-bottom: 10px; }
        .tracking-item { display: flex; align-items: flex-start; gap: 12px; padding: 15px; background: white; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #00509d; transition: all 0.3s; }
        .tracking-item:hover { box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        .tracking-item input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; margin-top: 2px; }
        .tracking-info { flex: 1; }
        .tracking-code { font-weight: 600; color: #00296b; font-size: 1rem; margin-bottom: 5px; }
        .tracking-details { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 5px; }
        .tracking-detail { font-size: 0.85rem; color: #7f8c8d; display: flex; align-items: center; gap: 5px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 600; }
        .badge-pendiente { background: #fff3cd; color: #856404; }
        .badge-pagado { background: #d4edda; color: #155724; }

        .btn-group { display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; }
        .btn { padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.95rem; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: linear-gradient(135deg, #00296b 0%, #00509d 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 80, 157, 0.3); }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }

        .loading { text-align: center; padding: 20px; color: #7f8c8d; }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="header">
                <div class="header-left">
                    <h1>✈️ Generar Embarque</h1>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <h2 class="card-title">
                        <i class='bx bx-package'></i> Generar Embarque
                    </h2>

                    <?php if (count($errores) > 0): ?>
                    <div class="alert">
                        <strong>⚠️ Errores:</strong>
                        <ul>
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <!-- INFORMACIÓN DEL CLIENTE -->
                        <div class="section-title">
                            <i class='bx bx-user'></i> Información del Cliente
                        </div>

                        <div class="form-row full">
                            <div class="form-group">
                                <label>Cliente <span class="required">*</span></label>
                                <select id="cliente_id" name="cliente_id" required onchange="cargarTrackingsCliente()">
                                    <option value="">Seleccionar cliente...</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?php echo $cliente['id']; ?>"
                                                <?php echo (isset($_POST['cliente_id']) && $_POST['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cliente['nombre_completo']); ?> - <?php echo htmlspecialchars($cliente['documento']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Selecciona el cliente para cargar sus trackings</small>
                            </div>
                        </div>

                        <!-- TRACKINGS DEL CLIENTE -->
                        <div class="section-title">
                            <i class='bx bx-box'></i> Trackings del Cliente
                        </div>

                        <div class="form-group">
                            <label>Selecciona los trackings para esta guía <span class="required">*</span></label>
                            <div id="trackings-container" class="trackings-container">
                                <div class="trackings-empty">
                                    <i class='bx bx-package'></i>
                                    <p>Selecciona un cliente para ver sus trackings</p>
                                </div>
                            </div>
                        </div>

                        <!-- DETALLES DE LA GUÍA -->
                        <div class="section-title">
                            <i class='bx bx-clipboard'></i> Detalles de la Guía
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>N° de Guía <span class="required">*</span></label>
                                <input type="text"
                                       name="nro_guia"
                                       value="<?php echo isset($_POST['nro_guia']) ? htmlspecialchars($_POST['nro_guia']) : $nro_guia_sugerido; ?>"
                                       required>
                            </div>

                            <div class="form-group">
                                <label>Valor (USD) <span class="required">*</span></label>
                                <input type="number"
                                       name="valor_usd"
                                       step="0.01"
                                       min="0"
                                       placeholder="0.00"
                                       value="<?php echo isset($_POST['valor_usd']) ? htmlspecialchars($_POST['valor_usd']) : ''; ?>"
                                       required>
                            </div>
                        </div>

                        <div class="form-row full">
                            <div class="form-group">
                                <label>Proveedor(es)</label>
                                <input type="text"
                                       name="proveedor"
                                       placeholder="Ej: Amazon, AliExpress"
                                       value="<?php echo isset($_POST['proveedor']) ? htmlspecialchars($_POST['proveedor']) : ''; ?>">
                                <small>Puedes listar varios separados por comas</small>
                            </div>
                        </div>

                        <div class="form-row full">
                            <div class="form-group">
                                <label>Contenido</label>
                                <textarea name="contenido" placeholder="Describe el contenido del embarque..."><?php echo isset($_POST['contenido']) ? htmlspecialchars($_POST['contenido']) : ''; ?></textarea>
                            </div>
                        </div>

                        <div class="form-row full">
                            <div class="form-group">
                                <label>Indicaciones</label>
                                <textarea name="indicaciones" placeholder="Indicaciones especiales..."><?php echo isset($_POST['indicaciones']) ? htmlspecialchars($_POST['indicaciones']) : ''; ?></textarea>
                            </div>
                        </div>

                        <div class="btn-group">
                            <a href="index.php" class="btn btn-secondary">
                                ← Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                💾 Guardar y Generar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function cargarTrackingsCliente() {
            const clienteId = document.getElementById('cliente_id').value;
            const container = document.getElementById('trackings-container');

            if (!clienteId) {
                container.innerHTML = `
                    <div class="trackings-empty">
                        <i class='bx bx-package'></i>
                        <p>Selecciona un cliente para ver sus trackings</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = '<div class="loading">⏳ Cargando trackings...</div>';

            fetch(`obtener_trackings_cliente.php?cliente_id=${clienteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.trackings.length > 0) {
                        let html = '';
                        data.trackings.forEach(tracking => {
                            const badgeClass = tracking.pendiente_pago === 'SI' ? 'badge-pendiente' : 'badge-pagado';
                            const badgeText = tracking.pendiente_pago === 'SI' ? `Pendiente: $${tracking.monto_pendiente}` : 'Pagado';

                            html += `
                                <div class="tracking-item">
                                    <input type="checkbox" name="trackings[]" value="${tracking.id}" id="track_${tracking.id}">
                                    <label for="track_${tracking.id}" class="tracking-info" style="cursor: pointer;">
                                        <div class="tracking-code">${tracking.tracking_code}</div>
                                        <div class="tracking-details">
                                            <span class="tracking-detail">
                                                <i class='bx bx-file'></i> ${tracking.nombre_archivo}
                                            </span>
                                            <span class="tracking-detail">
                                                <i class='bx bx-calendar'></i> ${tracking.fecha}
                                            </span>
                                            <span class="badge ${badgeClass}">${badgeText}</span>
                                        </div>
                                    </label>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = `
                            <div class="trackings-empty">
                                <i class='bx bx-error'></i>
                                <p>Este cliente no tiene trackings disponibles</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = `
                        <div class="trackings-empty" style="color: #e74c3c;">
                            <i class='bx bx-error-circle'></i>
                            <p>Error al cargar trackings: ${error.message}</p>
                        </div>
                    `;
                });
        }
    </script>
</body>
</html>

<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN', 'VENTAS']);

$database = new Database();
$conn = $database->getConnection();

$errores = [];
$esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Obtener lista de clientes para el select
$stmt = $conn->query("SELECT id, nombre_razon_social, apellido, documento FROM clientes ORDER BY nombre_razon_social ASC");
$clientes = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trackings = isset($_POST['trackings']) ? $_POST['trackings'] : [];
    $cliente_id = (int)$_POST['cliente_id'];
    $pendiente_pago = isset($_POST['pendiente_pago']) ? $_POST['pendiente_pago'] : 'NO';
    $monto_pendiente = isset($_POST['monto_pendiente']) ? (float)$_POST['monto_pendiente'] : 0.00;

    // Validaciones
    if (empty($trackings) || !is_array($trackings)) {
        $errores[] = "Debes agregar al menos un código de tracking";
    } else {
        // Limpiar trackings vacíos
        $trackings = array_filter(array_map('trim', $trackings));
        if (empty($trackings)) {
            $errores[] = "Debes agregar al menos un código de tracking válido";
        }
    }

    if ($cliente_id <= 0) {
        $errores[] = "Debes seleccionar un cliente";
    }

    // Validar que el archivo fue subido
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Debes subir un archivo para el pedido";
    }

    // Validar pendiente de pago
    if (!in_array($pendiente_pago, ['SI', 'NO'])) {
        $errores[] = "Valor inválido para pendiente de pago";
    }

    // Si pendiente de pago es SI, validar que el monto sea mayor a 0
    if ($pendiente_pago === 'SI' && $monto_pendiente <= 0) {
        $errores[] = "Debe ingresar un monto pendiente mayor a 0";
    }

    if (empty($errores)) {
        // Verificar que los trackings no existan
        $placeholders = implode(',', array_fill(0, count($trackings), '?'));
        $stmt = $conn->prepare("SELECT tracking_code FROM pedidos_trackings WHERE tracking_code IN ($placeholders)");
        $stmt->execute($trackings);
        $trackings_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($trackings_existentes)) {
            $errores[] = "Los siguientes trackings ya existen: " . implode(', ', $trackings_existentes);
        } else {
            // Verificar que el cliente existe
            $stmt = $conn->prepare("SELECT id FROM clientes WHERE id = :id");
            $stmt->bindParam(':id', $cliente_id);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                $errores[] = "El cliente seleccionado no existe";
            } else {
                // Subir archivo
                $carpeta = "../../uploads/pedidos";
                $resultado = subirArchivo($_FILES['archivo'], $carpeta, ['pdf', 'xlsx', 'xls', 'doc', 'docx']);

                if ($resultado['exito']) {
                    try {
                        // Iniciar transacción
                        $conn->beginTransaction();

                        // Insertar recibo (sin tracking_pedido, se removió)
                        $stmt = $conn->prepare("
                            INSERT INTO recibos_pedidos (cliente_id, nombre_archivo, nombre_original, ruta, pendiente_pago, monto_pendiente)
                            VALUES (:cliente_id, :nombre_archivo, :nombre_original, :ruta, :pendiente_pago, :monto_pendiente)
                        ");

                        $stmt->bindParam(':cliente_id', $cliente_id);
                        $stmt->bindParam(':nombre_archivo', $resultado['nombre_archivo']);
                        $stmt->bindParam(':nombre_original', $resultado['nombre_original']);
                        $stmt->bindParam(':ruta', $resultado['ruta']);
                        $stmt->bindParam(':pendiente_pago', $pendiente_pago);
                        $stmt->bindParam(':monto_pendiente', $monto_pendiente);

                        $stmt->execute();
                        $recibo_id = $conn->lastInsertId();

                        // Insertar trackings individuales
                        $stmt_tracking = $conn->prepare("
                            INSERT INTO pedidos_trackings (recibo_pedido_id, tracking_code, estado_embarque)
                            VALUES (:recibo_pedido_id, :tracking_code, 'PENDIENTE')
                        ");

                        foreach ($trackings as $tracking_code) {
                            $tracking_code = limpiarDatos($tracking_code);
                            $stmt_tracking->bindParam(':recibo_pedido_id', $recibo_id);
                            $stmt_tracking->bindParam(':tracking_code', $tracking_code);
                            $stmt_tracking->execute();
                        }

                        // Confirmar transacción
                        $conn->commit();

                        // Respuesta para AJAX
                        if ($esAjax) {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success' => true,
                                'message' => 'Recibo creado con ' . count($trackings) . ' tracking(s) exitosamente'
                            ]);
                            exit();
                        } else {
                            header("Location: index.php?success=Recibo creado con " . count($trackings) . " tracking(s) exitosamente");
                            exit();
                        }
                    } catch(PDOException $e) {
                        // Revertir transacción
                        $conn->rollBack();
                        $errores[] = "Error al crear el pedido: " . $e->getMessage();
                        // Eliminar archivo si falla la inserción
                        if (file_exists($resultado['ruta'])) {
                            unlink($resultado['ruta']);
                        }
                    }
                } else {
                    $errores[] = $resultado['mensaje'];
                }
            }
        }
    }
}

// Si hay errores y es petición AJAX, responder con JSON
if ($esAjax && !empty($errores)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => implode('<br>', $errores)
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Pedido</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .card-header {
            margin-bottom: 30px;
        }

        .card-header h1 {
            font-size: 1.8rem;
            color: #00296b;
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }

        .trackings-container {
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            padding: 15px;
            background: #f8f9fa;
            margin-bottom: 10px;
        }

        .tracking-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .tracking-item input {
            flex: 1;
        }

        .btn-remove {
            padding: 8px 12px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-remove:hover {
            background: #c0392b;
        }

        .btn-add-tracking {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-add-tracking:hover {
            background: #2980b9;
        }

        .file-input-wrapper {
            position: relative;
            display: block;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-input-label {
            padding: 12px 20px;
            background: #e0e5e9ff;
            color: #ffff;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s;
            cursor: pointer;
        }

        .file-input-label:hover {
            background: #2980b9;
        }

        .file-name {
            display: block;
            margin-top: 10px;
            color: #666;
            font-style: italic;
            font-size: 0.9rem;
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
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-back {
            background: linear-gradient(135deg, #a70b0b 0%, #f00000 100%);
            color: white;
        }

        .btn-back:hover {
            background: #d40000;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert ul {
            margin-left: 20px;
            margin-top: 10px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }

        .info-box ul {
            margin-left: 20px;
            margin-top: 10px;
        }

        .warning-box {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }

        .section-title {
            font-size: 1.2rem;
            color: #2c3e50;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .cliente-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            display: none;
        }

        .cliente-preview.show {
            display: block;
        }

        .cliente-preview p {
            margin: 5px 0;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><box-icon type='solid' name='package' color='#00509d' size='40px'></box-icon> Crear Nuevo Pedido</h1>
            </div>

            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <strong>⚠️ Errores encontrados:</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (count($clientes) === 0): ?>
            <div class="warning-box">
                <strong>⚠️ No hay clientes registrados</strong>
                <p>Debes registrar al menos un cliente antes de crear pedidos.</p>
                <a href="../clientes/crear.php" style="color: #ff9800; font-weight: 600;">→ Ir a registrar cliente</a>
            </div>
            <?php else: ?>

            <div class="info-box">
                <strong>ℹ️ Información importante:</strong>
                <ul>
                    <li>Puedes agregar <strong>múltiples trackings</strong> para un mismo recibo</li>
                    <li>Solo puedes crear pedidos para clientes ya registrados</li>
                    <li>Los códigos de tracking deben ser únicos</li>
                    <li>Formatos de archivo permitidos: PDF, Excel, Word (máx. 5MB)</li>
                </ul>
            </div>

            <form method="POST" enctype="multipart/form-data" id="pedidoForm">
                <div class="section-title">📋 Datos del Pedido</div>

                <div class="form-group">
                    <label>Seleccionar Cliente <span class="required">*</span></label>
                    <select class="form-control" name="cliente_id" id="cliente_id" required>
                        <option value="">-- Seleccionar cliente --</option>
                        <?php foreach ($clientes as $cliente): ?>
                        <option value="<?php echo $cliente['id']; ?>"
                                data-nombre="<?php echo htmlspecialchars($cliente['nombre_razon_social']); ?>"
                                data-apellido="<?php echo htmlspecialchars($cliente['apellido']); ?>"
                                data-documento="<?php echo htmlspecialchars($cliente['documento']); ?>"
                                <?php echo (isset($_POST['cliente_id']) && $_POST['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                            <?php
                            echo $cliente['nombre_razon_social'];
                            if ($cliente['apellido']) {
                                echo ' ' . $cliente['apellido'];
                            }
                            echo ' - ' . $cliente['documento'];
                            ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cliente-preview" id="clientePreview">
                    <strong>📌 Cliente seleccionado:</strong>
                    <p id="clienteNombre"></p>
                    <p id="clienteDocumento"></p>
                </div>

                <div class="section-title">📦 Códigos de Tracking</div>

                <div class="form-group">
                    <label>Trackings <span class="required">*</span></label>
                    <div id="trackingsContainer" class="trackings-container">
                        <div class="tracking-item">
                            <input type="text" class="form-control" name="trackings[]" placeholder="Ej: TRK-2024-001" required>
                            <button type="button" class="btn-remove" onclick="removeTracking(this)" style="display: none;">✖</button>
                        </div>
                    </div>
                    <button type="button" class="btn-add-tracking" onclick="addTracking()">
                        ➕ Agregar otro tracking
                    </button>
                    <small style="color: #666; font-size: 0.85rem; display: block; margin-top: 8px;">
                        Puedes agregar múltiples trackings si el recibo contiene varios envíos
                    </small>
                </div>

                <div class="section-title">📄 Archivo del Pedido</div>

                <div class="form-group">
                    <label>Subir Archivo <span class="required">*</span></label>
                    <div class="file-input-wrapper">
                        <input type="file" name="archivo" id="archivo"
                               accept=".pdf,.xlsx,.xls,.doc,.docx" required>
                        <label for="archivo" class="file-input-label">
                            📎 Seleccionar Archivo
                        </label>
                    </div>
                    <span class="file-name" id="fileName">PDF, Excel o Word (máx. 5MB)</span>
                </div>

                <div class="section-title">💰 Información de Pago</div>

                <div class="form-group">
                    <label>Pendiente de Pago <span class="required">*</span></label>
                    <div style="display: flex; gap: 20px; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 0;">
                            <input type="radio" name="pendiente_pago" value="NO" checked onchange="toggleMontoPago(false)">
                            <span>NO</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 0;">
                            <input type="radio" name="pendiente_pago" value="SI" onchange="toggleMontoPago(true)">
                            <span>SI</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="monto_group" style="display: none;">
                    <label>Monto Pendiente ($) <span class="required">*</span></label>
                    <input type="number" class="form-control" name="monto_pendiente" id="monto_pendiente"
                           step="0.01" min="0" placeholder="0.00">
                    <small style="color: #666; font-size: 0.85rem;">Ingrese el monto pendiente en soles</small>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">💾 Crear Pedido</button>
                    <a href="index.php" class="btn btn-back">← Cancelar</a>
                </div>
            </form>

            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mostrar/ocultar monto pendiente
        function toggleMontoPago(mostrar) {
            const montoGroup = document.getElementById('monto_group');
            const montoInput = document.getElementById('monto_pendiente');

            if (mostrar) {
                montoGroup.style.display = 'block';
                montoInput.required = true;
            } else {
                montoGroup.style.display = 'none';
                montoInput.required = false;
                montoInput.value = '0.00';
            }
        }

        // Agregar nuevo campo de tracking
        function addTracking() {
            const container = document.getElementById('trackingsContainer');
            const trackingItem = document.createElement('div');
            trackingItem.className = 'tracking-item';
            trackingItem.innerHTML = `
                <input type="text" class="form-control" name="trackings[]" placeholder="Ej: TRK-2024-002" required>
                <button type="button" class="btn-remove" onclick="removeTracking(this)">✖</button>
            `;
            container.appendChild(trackingItem);
            updateRemoveButtons();
        }

        // Eliminar campo de tracking
        function removeTracking(button) {
            button.closest('.tracking-item').remove();
            updateRemoveButtons();
        }

        // Actualizar visibilidad de botones eliminar
        function updateRemoveButtons() {
            const items = document.querySelectorAll('.tracking-item');
            items.forEach((item, index) => {
                const removeBtn = item.querySelector('.btn-remove');
                if (items.length > 1) {
                    removeBtn.style.display = 'block';
                } else {
                    removeBtn.style.display = 'none';
                }
            });
        }

        // Mostrar preview del cliente seleccionado
        document.getElementById('cliente_id').addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const preview = document.getElementById('clientePreview');

            if (this.value) {
                const nombre = option.dataset.nombre + (option.dataset.apellido ? ' ' + option.dataset.apellido : '');
                const documento = option.dataset.documento;

                document.getElementById('clienteNombre').textContent = '👤 ' + nombre;
                document.getElementById('clienteDocumento').textContent = '🆔 ' + documento;
                preview.classList.add('show');
            } else {
                preview.classList.remove('show');
            }
        });

        // Mostrar nombre del archivo seleccionado
        document.getElementById('archivo').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'PDF, Excel o Word (máx. 5MB)';
            document.getElementById('fileName').textContent = '📄 ' + fileName;
        });

        // Validar antes de enviar
        document.getElementById('pedidoForm').addEventListener('submit', function(e) {
            const clienteId = document.getElementById('cliente_id').value;
            const archivo = document.getElementById('archivo').files[0];
            const trackings = document.querySelectorAll('input[name="trackings[]"]');

            // Validar que haya al menos un tracking
            let hasValidTracking = false;
            trackings.forEach(input => {
                if (input.value.trim() !== '') {
                    hasValidTracking = true;
                }
            });

            if (!hasValidTracking) {
                e.preventDefault();
                alert('Debes agregar al menos un código de tracking');
                return false;
            }

            if (!clienteId) {
                e.preventDefault();
                alert('Debes seleccionar un cliente');
                return false;
            }

            if (!archivo) {
                e.preventDefault();
                alert('Debes subir un archivo');
                return false;
            }

            if (archivo.size > 5242880) { // 5MB
                e.preventDefault();
                alert('El archivo es demasiado grande. Máximo 5MB');
                return false;
            }
        });
    </script>

     <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</body>
</html>

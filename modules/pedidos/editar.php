<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN', 'SUPERVISOR', 'VENTAS']);

$database = new Database();
$conn = $database->getConnection();

$errores = [];
$esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

// Obtener datos del pedido
$stmt = $conn->prepare("SELECT rp.*, c.nombre_razon_social, c.apellido, c.tipo_documento, c.documento
                        FROM recibos_pedidos rp
                        INNER JOIN clientes c ON rp.cliente_id = c.id
                        WHERE rp.id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$pedido = $stmt->fetch();

if (!$pedido) {
    if ($esAjax || isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Pedido no encontrado'
        ]);
        exit();
    } else {
        header("Location: index.php?error=Pedido no encontrado");
        exit();
    }
}

// Si es petición AJAX GET (cargar datos), devolver JSON
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Obtener trackings asociados al pedido
    $stmt_trackings = $conn->prepare("SELECT id, tracking_code, estado_embarque FROM pedidos_trackings WHERE recibo_pedido_id = :recibo_id ORDER BY fecha_creacion ASC");
    $stmt_trackings->bindParam(':recibo_id', $id);
    $stmt_trackings->execute();
    $trackings = $stmt_trackings->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'pedido' => [
            'id' => $pedido['id'],
            'cliente_id' => $pedido['cliente_id'],
            'nombre_archivo' => $pedido['nombre_archivo'],
            'nombre_original' => isset($pedido['nombre_original']) ? $pedido['nombre_original'] : $pedido['nombre_archivo'],
            'pendiente_pago' => $pedido['pendiente_pago'],
            'monto_pendiente' => $pedido['monto_pendiente'],
            'subido_en' => $pedido['subido_en']
        ],
        'cliente' => [
            'nombre_razon_social' => $pedido['nombre_razon_social'],
            'apellido' => $pedido['apellido'],
            'tipo_documento' => $pedido['tipo_documento'],
            'documento' => $pedido['documento']
        ],
        'trackings' => $trackings
    ]);
    exit();
}

// Obtener trackings actuales del recibo
$stmt = $conn->prepare("SELECT id, tracking_code, estado_embarque FROM pedidos_trackings WHERE recibo_pedido_id = :recibo_id ORDER BY fecha_creacion ASC");
$stmt->bindParam(':recibo_id', $id);
$stmt->execute();
$trackings_actuales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de clientes para el select
$stmt = $conn->query("SELECT id, nombre_razon_social, apellido, documento FROM clientes ORDER BY nombre_razon_social ASC");
$clientes = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = (int)$_POST['cliente_id'];
    $pendiente_pago = isset($_POST['pendiente_pago']) ? $_POST['pendiente_pago'] : 'NO';
    $monto_pendiente = isset($_POST['monto_pendiente']) ? (float)$_POST['monto_pendiente'] : 0.00;
    $cambiar_archivo = isset($_POST['cambiar_archivo']) && $_POST['cambiar_archivo'] === '1';

    // Trackings a eliminar
    $trackings_eliminar = isset($_POST['trackings_eliminar']) ? $_POST['trackings_eliminar'] : [];

    // Nuevos trackings a agregar
    $nuevos_trackings = isset($_POST['nuevos_trackings']) ? $_POST['nuevos_trackings'] : [];

    // Validaciones
    if ($cliente_id <= 0) {
        $errores[] = "Debes seleccionar un cliente";
    }

    // Validar que haya al menos un tracking (entre los que quedan y los nuevos)
    $trackings_restantes = count($trackings_actuales) - count($trackings_eliminar);
    $trackings_nuevos_validos = 0;

    if (!empty($nuevos_trackings)) {
        foreach ($nuevos_trackings as $nuevo_tracking) {
            if (!empty(trim($nuevo_tracking))) {
                $trackings_nuevos_validos++;
            }
        }
    }

    $total_trackings_final = $trackings_restantes + $trackings_nuevos_validos;

    if ($total_trackings_final <= 0) {
        $errores[] = "Debe haber al menos un tracking en el recibo";
    }

    // Validar pendiente de pago
    if (!in_array($pendiente_pago, ['SI', 'NO'])) {
        $errores[] = "Valor inválido para pendiente de pago";
    }

    // Si pendiente de pago es SI, validar que el monto sea mayor a 0
    if ($pendiente_pago === 'SI' && $monto_pendiente <= 0) {
        $errores[] = "Debe ingresar un monto pendiente mayor a 0";
    }

    // Si se quiere cambiar el archivo, validar que se haya subido uno nuevo
    if ($cambiar_archivo) {
        if (!isset($_FILES['nuevo_archivo'])) {
            $errores[] = "Debes subir un nuevo archivo si deseas reemplazarlo";
        } else {
            $error_archivo = $_FILES['nuevo_archivo']['error'];

            // Validar errores específicos de upload
            switch ($error_archivo) {
                case UPLOAD_ERR_OK:
                    // Archivo subido correctamente, continuar
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errores[] = "Debes seleccionar un archivo si deseas reemplazarlo";
                    break;
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errores[] = "El archivo excede el tamaño máximo permitido (5MB)";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errores[] = "El archivo solo se subió parcialmente. Intenta nuevamente";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errores[] = "Error del servidor: Falta directorio temporal";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errores[] = "Error del servidor: No se pudo escribir el archivo";
                    break;
                default:
                    $errores[] = "Error al subir el archivo. Por favor intenta nuevamente";
                    break;
            }
        }
    }

    // Validar que los nuevos trackings no existan
    if (!empty($nuevos_trackings) && $trackings_nuevos_validos > 0) {
        $nuevos_trackings_limpios = array_filter(array_map('trim', $nuevos_trackings));

        if (!empty($nuevos_trackings_limpios)) {
            $placeholders = implode(',', array_fill(0, count($nuevos_trackings_limpios), '?'));
            $stmt = $conn->prepare("SELECT tracking_code FROM pedidos_trackings WHERE tracking_code IN ($placeholders)");
            $stmt->execute($nuevos_trackings_limpios);
            $trackings_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($trackings_existentes)) {
                $errores[] = "Los siguientes trackings ya existen: " . implode(', ', $trackings_existentes);
            }
        }
    }

    if (empty($errores)) {
        // Verificar que el cliente existe
        $stmt = $conn->prepare("SELECT id FROM clientes WHERE id = :id");
        $stmt->bindParam(':id', $cliente_id);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            $errores[] = "El cliente seleccionado no existe";
        } else {
            try {
                // Iniciar transacción
                $conn->beginTransaction();

                // Si se va a cambiar el archivo, procesarlo primero
                $nuevo_archivo_data = null;
                if ($cambiar_archivo) {
                    $carpeta = "../../uploads/pedidos";
                    $resultado = subirArchivo($_FILES['nuevo_archivo'], $carpeta, ['pdf', 'xlsx', 'xls', 'doc', 'docx']);

                    if ($resultado['exito']) {
                        $nuevo_archivo_data = $resultado;

                        // Eliminar archivo antiguo
                        if (file_exists($pedido['ruta'])) {
                            unlink($pedido['ruta']);
                        }
                    } else {
                        $errores[] = $resultado['mensaje'];
                    }
                }

                if (empty($errores)) {
                    // Preparar SQL según si se cambia archivo o no
                    if ($cambiar_archivo && $nuevo_archivo_data) {
                        $stmt = $conn->prepare("
                            UPDATE recibos_pedidos
                            SET cliente_id = :cliente_id,
                                pendiente_pago = :pendiente_pago,
                                monto_pendiente = :monto_pendiente,
                                nombre_archivo = :nombre_archivo,
                                nombre_original = :nombre_original,
                                ruta = :ruta
                            WHERE id = :id
                        ");

                        $stmt->bindParam(':nombre_archivo', $nuevo_archivo_data['nombre_archivo']);
                        $stmt->bindParam(':nombre_original', $nuevo_archivo_data['nombre_original']);
                        $stmt->bindParam(':ruta', $nuevo_archivo_data['ruta']);
                    } else {
                        // Actualizar pedido sin cambiar archivo
                        $stmt = $conn->prepare("
                            UPDATE recibos_pedidos
                            SET cliente_id = :cliente_id,
                                pendiente_pago = :pendiente_pago,
                                monto_pendiente = :monto_pendiente
                            WHERE id = :id
                        ");
                    }

                    $stmt->bindParam(':cliente_id', $cliente_id);
                    $stmt->bindParam(':pendiente_pago', $pendiente_pago);
                    $stmt->bindParam(':monto_pendiente', $monto_pendiente);
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();

                    // Eliminar trackings marcados
                    if (!empty($trackings_eliminar)) {
                        $placeholders_del = implode(',', array_fill(0, count($trackings_eliminar), '?'));
                        $stmt_del = $conn->prepare("DELETE FROM pedidos_trackings WHERE id IN ($placeholders_del) AND recibo_pedido_id = ?");
                        $params_del = array_merge($trackings_eliminar, [$id]);
                        $stmt_del->execute($params_del);
                    }

                    // Agregar nuevos trackings
                    if (!empty($nuevos_trackings)) {
                        $stmt_insert = $conn->prepare("
                            INSERT INTO pedidos_trackings (recibo_pedido_id, tracking_code, estado_embarque)
                            VALUES (:recibo_pedido_id, :tracking_code, 'PENDIENTE')
                        ");

                        foreach ($nuevos_trackings as $nuevo_tracking) {
                            $nuevo_tracking = trim($nuevo_tracking);
                            if (!empty($nuevo_tracking)) {
                                $stmt_insert->bindParam(':recibo_pedido_id', $id);
                                $stmt_insert->bindParam(':tracking_code', $nuevo_tracking);
                                $stmt_insert->execute();
                            }
                        }
                    }

                    // Confirmar transacción
                    $conn->commit();

                    $mensaje = 'Recibo actualizado exitosamente';
                    header("Location: index.php?success=" . urlencode($mensaje));
                    exit();
                } else {
                    // Revertir transacción
                    $conn->rollBack();

                    // Si hubo error en la subida del archivo y ya se subió, eliminarlo
                    if ($nuevo_archivo_data && file_exists($nuevo_archivo_data['ruta'])) {
                        unlink($nuevo_archivo_data['ruta']);
                    }
                }
            } catch(PDOException $e) {
                // Revertir transacción
                $conn->rollBack();
                $errores[] = "Error al actualizar el recibo: " . $e->getMessage();

                // Si se subió archivo nuevo y hay error, eliminarlo
                if (isset($nuevo_archivo_data) && $nuevo_archivo_data && file_exists($nuevo_archivo_data['ruta'])) {
                    unlink($nuevo_archivo_data['ruta']);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Recibo</title>
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
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header h1 {
            font-size: 1.8rem;
            color: #00296b;
            margin-bottom: 10px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.9rem;
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

        .trackings-existentes {
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            padding: 15px;
            background: #f8f9fa;
            margin-bottom: 15px;
        }

        .tracking-item-editable {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 3px solid #00509D;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tracking-item-editable.to-delete {
            opacity: 0.5;
            border-left-color: #e74c3c;
            text-decoration: line-through;
        }

        .tracking-info-edit {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tracking-code {
            font-weight: 600;
            color: #2c3e50;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-pendiente {
            background: #fff3e0;
            color: #f57c00;
        }

        .badge-embarcado {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .btn-delete-tracking {
            padding: 6px 12px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.85rem;
        }

        .btn-delete-tracking:hover {
            background: #c0392b;
        }

        .btn-undo-tracking {
            padding: 6px 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.85rem;
        }

        .btn-undo-tracking:hover {
            background: #2980b9;
        }

        .nuevos-trackings-container {
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            padding: 15px;
            background: #f8f9fa;
            margin-bottom: 10px;
        }

        .nuevo-tracking-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .nuevo-tracking-item input {
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #00509D;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>✏️ Editar Recibo #<?php echo $pedido['id']; ?></h1>
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

            <div class="info-box">
                <strong>ℹ️ Información:</strong> Puedes editar todos los datos del recibo: cliente, trackings, archivo y pago.
                <ul style="margin-top: 8px; margin-left: 20px;">
                    <li>Elimina trackings que no necesites</li>
                    <li>Agrega nuevos trackings al recibo</li>
                    <li>Debe quedar al menos 1 tracking</li>
                </ul>
            </div>

            <form method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <div class="section-title">👤 Cliente</div>

                <div class="form-group">
                    <label>Seleccionar Cliente <span class="required">*</span></label>
                    <select class="form-control" name="cliente_id" id="cliente_id" required>
                        <option value="">-- Seleccionar cliente --</option>
                        <?php foreach ($clientes as $cliente): ?>
                        <option value="<?php echo $cliente['id']; ?>"
                                <?php echo ($cliente['id'] == $pedido['cliente_id']) ? 'selected' : ''; ?>>
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

                <div class="section-title">📦 Trackings del Recibo</div>

                <?php if (!empty($trackings_actuales)): ?>
                <div class="form-group">
                    <label>Trackings Existentes</label>
                    <div class="trackings-existentes" id="trackingsExistentes">
                        <?php foreach ($trackings_actuales as $tracking): ?>
                        <div class="tracking-item-editable" id="tracking_<?php echo $tracking['id']; ?>">
                            <div class="tracking-info-edit">
                                <span class="tracking-code"><?php echo htmlspecialchars($tracking['tracking_code']); ?></span>
                                <?php if ($tracking['estado_embarque'] === 'EMBARCADO'): ?>
                                    <span class="badge badge-embarcado">EMBARCADO</span>
                                <?php else: ?>
                                    <span class="badge badge-pendiente">PENDIENTE</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($tracking['estado_embarque'] === 'PENDIENTE'): ?>
                            <button type="button" class="btn-delete-tracking" onclick="toggleEliminarTracking(<?php echo $tracking['id']; ?>)">
                                ✖ Eliminar
                            </button>
                            <?php else: ?>
                            <span style="font-size: 0.85rem; color: #718096;">No se puede eliminar (embarcado)</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Agregar Nuevos Trackings (Opcional)</label>
                    <div id="nuevosTrackingsContainer" class="nuevos-trackings-container">
                        <!-- Los nuevos trackings se agregarán aquí con JavaScript -->
                    </div>
                    <button type="button" class="btn-add-tracking" onclick="agregarNuevoTracking()">
                        ➕ Agregar Tracking
                    </button>
                    <small style="display: block; margin-top: 8px; color: #666; font-size: 0.85rem;">
                        Puedes agregar más trackings a este recibo sin duplicar el archivo
                    </small>
                </div>

                <div class="section-title">📄 Archivo del Recibo</div>

                <div class="form-group">
                    <p><strong>Archivo Actual:</strong> <?php echo htmlspecialchars($pedido['nombre_original'] ?: $pedido['nombre_archivo']); ?></p>
                </div>

                <div class="checkbox-group" onclick="document.getElementById('cambiar_archivo_check').click();">
                    <input type="checkbox" name="cambiar_archivo" id="cambiar_archivo_check" value="1" onchange="toggleArchivoNuevo(this.checked)">
                    <label for="cambiar_archivo_check" style="cursor: pointer; margin: 0;">¿Deseas cambiar el archivo?</label>
                </div>

                <div class="form-group" id="archivo_nuevo_group" style="display: none;">
                    <label>Subir Nuevo Archivo <span class="required">*</span></label>
                    <div class="file-input-wrapper">
                        <input type="file" name="nuevo_archivo" id="nuevo_archivo"
                               accept=".pdf,.xlsx,.xls,.doc,.docx">
                        <label for="nuevo_archivo" class="file-input-label">
                            📎 Seleccionar Nuevo Archivo
                        </label>
                    </div>
                    <span class="file-name" id="fileName">PDF, Excel o Word (máx. 5MB)</span>
                </div>

                <div class="section-title">💰 Información de Pago</div>

                <div class="form-group">
                    <label>Pendiente de Pago <span class="required">*</span></label>
                    <div style="display: flex; gap: 20px; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 0;">
                            <input type="radio" name="pendiente_pago" value="NO"
                                   <?php echo ($pedido['pendiente_pago'] === 'NO') ? 'checked' : ''; ?>
                                   onchange="toggleMontoPago(false)">
                            <span>NO</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 0;">
                            <input type="radio" name="pendiente_pago" value="SI"
                                   <?php echo ($pedido['pendiente_pago'] === 'SI') ? 'checked' : ''; ?>
                                   onchange="toggleMontoPago(true)">
                            <span>SI</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="monto_group" style="<?php echo ($pedido['pendiente_pago'] === 'SI') ? '' : 'display: none;'; ?>">
                    <label>Monto Pendiente ($) <span class="required">*</span></label>
                    <input type="number" class="form-control" name="monto_pendiente" id="monto_pendiente"
                           step="0.01" min="0" value="<?php echo $pedido['monto_pendiente']; ?>"
                           placeholder="0.00">
                    <small style="color: #666; font-size: 0.85rem;">Ingrese el monto pendiente en soles</small>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">💾 Guardar Cambios</button>
                    <a href="index.php" class="btn btn-back">← Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const trackingsAEliminar = new Set();
        let contadorNuevos = 0;

        // Toggle eliminar tracking
        function toggleEliminarTracking(trackingId) {
            const item = document.getElementById('tracking_' + trackingId);
            const btn = item.querySelector('button');

            if (trackingsAEliminar.has(trackingId)) {
                // Desmarcar para eliminar
                trackingsAEliminar.delete(trackingId);
                item.classList.remove('to-delete');
                btn.innerHTML = '✖ Eliminar';
                btn.classList.remove('btn-undo-tracking');
                btn.classList.add('btn-delete-tracking');
            } else {
                // Marcar para eliminar
                trackingsAEliminar.add(trackingId);
                item.classList.add('to-delete');
                btn.innerHTML = '↶ Deshacer';
                btn.classList.remove('btn-delete-tracking');
                btn.classList.add('btn-undo-tracking');
            }
        }

        // Agregar nuevo tracking
        function agregarNuevoTracking() {
            contadorNuevos++;
            const container = document.getElementById('nuevosTrackingsContainer');
            const div = document.createElement('div');
            div.className = 'nuevo-tracking-item';
            div.id = 'nuevo_' + contadorNuevos;
            div.innerHTML = `
                <input type="text" class="form-control" name="nuevos_trackings[]" placeholder="Ej: TRK-2024-00${contadorNuevos}" required>
                <button type="button" class="btn-remove" onclick="removerNuevoTracking(${contadorNuevos})">✖</button>
            `;
            container.appendChild(div);
        }

        // Remover nuevo tracking
        function removerNuevoTracking(id) {
            const item = document.getElementById('nuevo_' + id);
            item.remove();
        }

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

        // Mostrar/ocultar campo de nuevo archivo
        function toggleArchivoNuevo(mostrar) {
            const archivoGroup = document.getElementById('archivo_nuevo_group');
            const archivoInput = document.getElementById('nuevo_archivo');

            if (mostrar) {
                archivoGroup.style.display = 'block';
                archivoInput.required = true;
            } else {
                archivoGroup.style.display = 'none';
                archivoInput.required = false;
                archivoInput.value = '';
                document.getElementById('fileName').textContent = 'PDF, Excel o Word (máx. 5MB)';
            }
        }

        // Mostrar nombre del archivo seleccionado
        document.getElementById('nuevo_archivo').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'PDF, Excel o Word (máx. 5MB)';
            document.getElementById('fileName').textContent = '📄 ' + fileName;
        });

        // Validar antes de enviar
        document.getElementById('editForm').addEventListener('submit', function(e) {
            // Agregar inputs hidden con los trackings a eliminar
            trackingsAEliminar.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'trackings_eliminar[]';
                input.value = id;
                this.appendChild(input);
            });

            const clienteId = document.getElementById('cliente_id').value;
            const cambiarArchivo = document.getElementById('cambiar_archivo_check').checked;
            const archivoInput = document.getElementById('nuevo_archivo');
            const archivo = archivoInput.files[0];

            if (!clienteId) {
                e.preventDefault();
                alert('⚠️ Debes seleccionar un cliente');
                return false;
            }

            // Si el checkbox de cambiar archivo está marcado, debe haber un archivo
            if (cambiarArchivo && !archivo) {
                e.preventDefault();
                alert('⚠️ Has marcado "Cambiar archivo" pero no has seleccionado ningún archivo.\n\nPor favor:\n- Selecciona un archivo nuevo, o\n- Desmarca la opción "¿Deseas cambiar el archivo?"');
                // Hacer focus en el input de archivo
                archivoInput.focus();
                return false;
            }

            // Validar tamaño del archivo
            if (archivo && archivo.size > 5242880) { // 5MB
                e.preventDefault();
                alert('⚠️ El archivo es demasiado grande.\n\nTamaño máximo permitido: 5MB\nTamaño del archivo: ' + (archivo.size / 1024 / 1024).toFixed(2) + 'MB');
                return false;
            }

            // Validar extensión del archivo
            if (archivo) {
                const nombreArchivo = archivo.name.toLowerCase();
                const extensionesPermitidas = ['.pdf', '.xlsx', '.xls', '.doc', '.docx'];
                const tieneExtensionValida = extensionesPermitidas.some(ext => nombreArchivo.endsWith(ext));

                if (!tieneExtensionValida) {
                    e.preventDefault();
                    alert('⚠️ Formato de archivo no permitido.\n\nFormatos aceptados: PDF, Excel (.xlsx, .xls), Word (.doc, .docx)');
                    return false;
                }
            }
        });
    </script>
</body>
</html>

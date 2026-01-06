<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once 'funciones_pdf.php';

requiereLogin(); // Todos los usuarios pueden editar documentos

$database = new Database();
$conn = $database->getConnection();
$errores = [];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php?error=ID inválido");
    exit();
}

// Obtener datos del documento
$stmt = $conn->prepare("SELECT * FROM documentos_facturacion WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$documento = $stmt->fetch();

if (!$documento) {
    header("Location: index.php?error=Documento no encontrado");
    exit();
}

// Obtener lista de clientes
$stmt = $conn->query("SELECT id, tipo_documento, documento, nombre_razon_social, apellido FROM clientes ORDER BY nombre_razon_social ASC");
$clientes = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_documento = $_POST['tipo_documento'];
    $cliente_id = (int)$_POST['cliente_id'];

    // Datos de cálculo
    $peso_total = (float)$_POST['peso_total'];
    $total_paquetes = (int)$_POST['total_paquetes'];
    $total_guias = (int)$_POST['total_guias'];
    $cantidad_cambio_consignatario = (int)($_POST['cantidad_cambio_consignatario'] ?? 0);
    $cantidad_reempaque = (int)($_POST['cantidad_reempaque'] ?? 0);
    $envio_provincia = $_POST['envio_provincia'] ?? 'NO';
    $gastos_adicionales = (float)($_POST['gastos_adicionales'] ?? 0);
    $detalle_gastos_adicionales = $_POST['detalle_gastos_adicionales'] ?? '';
    $descuento = (float)($_POST['descuento'] ?? 0);
    $detalle_descuento = $_POST['detalle_descuento'] ?? '';
    $canal_aduanas = $_POST['canal_aduanas'] ?? '';

    // Validaciones
    if (empty($tipo_documento) || !in_array($tipo_documento, ['FACTURA', 'BOLETA', 'RECIBO'])) {
        $errores[] = "Tipo de documento inválido";
    }

    if ($cliente_id <= 0) {
        $errores[] = "Debe seleccionar un cliente";
    }

    if ($peso_total <= 0) {
        $errores[] = "El peso total debe ser mayor a 0";
    }

    if (empty($errores)) {
        try {
            // Calcular costos
            $costo_peso = ($peso_total < 1) ? 10.00 : ($peso_total * 10.00);
            $costo_desaduanaje = $total_guias * 5.00;
            $costo_cambio_consignatario = $cantidad_cambio_consignatario * 3.00;
            $costo_reempaque = $cantidad_reempaque * 5.00;
            $costo_envio_provincia = ($envio_provincia === 'SI') ? 3.00 : 0.00;

            // Obtener pendiente de pago del cliente
            $stmt_pendiente = $conn->prepare("SELECT SUM(monto_pendiente) as total_pendiente
                                              FROM recibos_pedidos
                                              WHERE cliente_id = :cliente_id AND pendiente_pago = 'SI'");
            $stmt_pendiente->bindParam(':cliente_id', $cliente_id);
            $stmt_pendiente->execute();
            $pendiente_resultado = $stmt_pendiente->fetch();
            $pendiente_pago = $pendiente_resultado['total_pendiente'] ?? 0.00;

            // Calcular subtotal
            $subtotal = $costo_peso + $costo_desaduanaje + $costo_cambio_consignatario +
                       $costo_reempaque + $costo_envio_provincia + $pendiente_pago + $gastos_adicionales - $descuento;

            // Calcular IGV y total
            if ($tipo_documento === 'FACTURA' || $tipo_documento === 'BOLETA') {
                $igv = $subtotal * 0.18;
                $total = $subtotal + $igv;
            } else {
                $igv = 0.00;
                $total = $subtotal;
                $subtotal = 0.00;
            }

            // Manejar imagen adjunta
            $imagen_adjunta = $documento['imagen_adjunta']; // Mantener la imagen actual por defecto
            $eliminar_imagen = isset($_POST['eliminar_imagen']) && $_POST['eliminar_imagen'] === 'SI';

            // Si se marca para eliminar la imagen
            if ($eliminar_imagen && !empty($documento['imagen_adjunta'])) {
                if (file_exists($documento['imagen_adjunta'])) {
                    unlink($documento['imagen_adjunta']);
                }
                $imagen_adjunta = null;
            }

            // Si se sube una nueva imagen
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
                $max_size = 5 * 1024 * 1024; // 5MB

                $file_type = $_FILES['imagen']['type'];
                $file_size = $_FILES['imagen']['size'];
                $file_tmp = $_FILES['imagen']['tmp_name'];
                $file_ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));

                // Validar tipo de archivo
                if (!in_array($file_type, $allowed_types) && !in_array($file_ext, ['png', 'jpg', 'jpeg'])) {
                    $errores[] = "Formato de imagen no permitido. Solo se aceptan PNG, JPEG, JPG";
                }

                // Validar tamaño
                if ($file_size > $max_size) {
                    $errores[] = "La imagen excede el tamaño máximo de 5MB";
                }

                // Si no hay errores, procesar el upload
                if (empty($errores)) {
                    // Eliminar imagen anterior si existe
                    if (!empty($documento['imagen_adjunta']) && file_exists($documento['imagen_adjunta'])) {
                        unlink($documento['imagen_adjunta']);
                    }

                    $directorio_imagenes = '../../uploads/facturas/imagenes/';
                    if (!file_exists($directorio_imagenes)) {
                        mkdir($directorio_imagenes, 0777, true);
                    }

                    $nombre_imagen = 'IMG_' . $documento['numero_documento'] . '_' . time() . '.' . $file_ext;
                    $ruta_imagen = $directorio_imagenes . $nombre_imagen;

                    if (move_uploaded_file($file_tmp, $ruta_imagen)) {
                        $imagen_adjunta = $ruta_imagen;
                    } else {
                        $errores[] = "Error al guardar la imagen";
                    }
                }
            }

            // Si hay errores de imagen, no continuar
            if (!empty($errores)) {
                // No hacer nada, los errores se mostrarán
            } else {
                // Actualizar en base de datos
                $sql = "UPDATE documentos_facturacion SET
                            tipo_documento = :tipo_documento,
                            cliente_id = :cliente_id,
                            peso_total = :peso_total,
                            costo_peso = :costo_peso,
                            total_paquetes = :total_paquetes,
                            total_guias = :total_guias,
                            costo_desaduanaje = :costo_desaduanaje,
                            cantidad_cambio_consignatario = :cantidad_cambio_consignatario,
                            costo_cambio_consignatario = :costo_cambio_consignatario,
                            cantidad_reempaque = :cantidad_reempaque,
                            costo_reempaque = :costo_reempaque,
                            envio_provincia = :envio_provincia,
                            costo_envio_provincia = :costo_envio_provincia,
                            pendiente_pago = :pendiente_pago,
                            gastos_adicionales = :gastos_adicionales,
                            detalle_gastos_adicionales = :detalle_gastos_adicionales,
                            descuento = :descuento,
                            detalle_descuento = :detalle_descuento,
                            canal_aduanas = :canal_aduanas,
                            subtotal = :subtotal,
                            igv = :igv,
                            total = :total,
                            imagen_adjunta = :imagen_adjunta
                        WHERE id = :id";

                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':tipo_documento', $tipo_documento);
                $stmt->bindParam(':cliente_id', $cliente_id);
                $stmt->bindParam(':peso_total', $peso_total);
                $stmt->bindParam(':costo_peso', $costo_peso);
                $stmt->bindParam(':total_paquetes', $total_paquetes);
                $stmt->bindParam(':total_guias', $total_guias);
                $stmt->bindParam(':costo_desaduanaje', $costo_desaduanaje);
                $stmt->bindParam(':cantidad_cambio_consignatario', $cantidad_cambio_consignatario);
                $stmt->bindParam(':costo_cambio_consignatario', $costo_cambio_consignatario);
                $stmt->bindParam(':cantidad_reempaque', $cantidad_reempaque);
                $stmt->bindParam(':costo_reempaque', $costo_reempaque);
                $stmt->bindParam(':envio_provincia', $envio_provincia);
                $stmt->bindParam(':costo_envio_provincia', $costo_envio_provincia);
                $stmt->bindParam(':pendiente_pago', $pendiente_pago);
                $stmt->bindParam(':gastos_adicionales', $gastos_adicionales);
                $stmt->bindParam(':detalle_gastos_adicionales', $detalle_gastos_adicionales);
                $stmt->bindParam(':descuento', $descuento);
                $stmt->bindParam(':detalle_descuento', $detalle_descuento);
                $stmt->bindParam(':canal_aduanas', $canal_aduanas);
                $stmt->bindParam(':subtotal', $subtotal);
                $stmt->bindParam(':igv', $igv);
                $stmt->bindParam(':total', $total);
                $stmt->bindParam(':imagen_adjunta', $imagen_adjunta);
                $stmt->bindParam(':id', $id);

                $stmt->execute();

                // Regenerar el PDF con los cambios actualizados
                $ruta_pdf_completa = __DIR__ . '/../../uploads/facturas/' . $documento['nombre_archivo'];
                generarYGuardarPDF($conn, $id, $ruta_pdf_completa);

                header("Location: index.php?success=" . urlencode("Documento actualizado exitosamente"));
                exit();
            }

        } catch (PDOException $e) {
            $errores[] = "Error al actualizar el documento: " . $e->getMessage();
        }
    }
}

$tipo_usuario = obtenerTipoUsuario();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Documento - <?php echo $documento['numero_documento']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 260px; }
        .header { background: white; padding: 20px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.8rem; color: #2c3e50; }
        .content { padding: 30px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; font-size: 0.95rem; }
        .form-group .required { color: #e74c3c; }
        .form-control { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem; transition: all 0.3s; }
        .form-control:focus { outline: none; border-color: #00509d; box-shadow: 0 0 0 3px rgba(0, 80, 157, 0.1); }
        select.form-control { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 40px; }
        .section-title { font-size: 1.2rem; color: #00296b; margin: 25px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; font-weight: 600; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.3s; margin-right: 10px; }
        .btn-primary { background: linear-gradient(135deg, #00296b 0%, #00509d 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 80, 157, 0.4); }
        .btn-back { background: #95a5a6; color: white; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .info-text { font-size: 0.85rem; color: #666; margin-top: 5px; }
        .totales-box { background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px solid #00509d; }
        .totales-box .total-row { display: flex; justify-content: space-between; padding: 10px 0; font-size: 1.1rem; }
        .totales-box .total-row.final { border-top: 2px solid #00296b; margin-top: 10px; padding-top: 15px; font-weight: 700; font-size: 1.3rem; color: #00296b; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
        .readonly-field { background: #e9ecef; cursor: not-allowed; }
        .info-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="header">
            <h1>✏️ Editar Documento: <?php echo $documento['numero_documento']; ?></h1>
            <div><a href="index.php" class="btn btn-back">← Volver</a></div>
        </header>
        <div class="content">
            <div class="info-box">
                <strong>ℹ️ Nota:</strong> El número de documento no se puede cambiar. Para modificar el tipo, créelo nuevamente.
            </div>
            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <strong>⚠️ Errores encontrados:</strong>
                <ul><?php foreach ($errores as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>
            <form method="POST" id="formFacturacion" enctype="multipart/form-data">
                <div class="card">
                    <div class="section-title">📋 Tipo de Documento</div>
                    <div class="form-group">
                        <label>Tipo de Documento (No editable)</label>
                        <input type="text" class="form-control readonly-field" value="<?php echo $documento['tipo_documento']; ?>" readonly>
                        <input type="hidden" name="tipo_documento" value="<?php echo $documento['tipo_documento']; ?>">
                    </div>
                    <div class="section-title">👤 Datos del Cliente</div>
                    <div class="form-group">
                        <label>Cliente <span class="required">*</span></label>
                        <select class="form-control" name="cliente_id" id="cliente_id" required>
                            <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>"
                                    <?php echo ($cliente['id'] == $documento['cliente_id']) ? 'selected' : ''; ?>
                                    data-pendiente="<?php
                                        $stmt_p = $conn->prepare("SELECT SUM(monto_pendiente) as total FROM recibos_pedidos WHERE cliente_id = :id AND pendiente_pago = 'SI'");
                                        $stmt_p->bindParam(':id', $cliente['id']);
                                        $stmt_p->execute();
                                        $pend = $stmt_p->fetch();
                                        echo $pend['total'] ?? 0;
                                    ?>">
                                <?php
                                echo $cliente['nombre_razon_social'];
                                if ($cliente['apellido']) echo ' ' . $cliente['apellido'];
                                echo ' - ' . $cliente['tipo_documento'] . ': ' . $cliente['documento'];
                                ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="section-title">💰 Detalles de Costos</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Peso Total (kg) <span class="required">*</span></label>
                            <input type="number" class="form-control" name="peso_total" id="peso_total"
                                   step="0.001" min="0" value="<?php echo $documento['peso_total']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Costo por Peso</label>
                            <input type="text" class="form-control readonly-field" id="costo_peso_display" readonly>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Total de Paquetes</label>
                            <input type="number" class="form-control" name="total_paquetes" id="total_paquetes"
                                   min="0" value="<?php echo $documento['total_paquetes']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Total de Guías <span class="required">*</span></label>
                            <input type="number" class="form-control" name="total_guias" id="total_guias"
                                   min="0" value="<?php echo $documento['total_guias']; ?>" required>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Cantidad Cambio de Consignatario</label>
                            <input type="number" class="form-control" name="cantidad_cambio_consignatario"
                                   id="cantidad_cambio_consignatario" min="0" value="<?php echo $documento['cantidad_cambio_consignatario']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Cantidad de Reempaques</label>
                            <input type="number" class="form-control" name="cantidad_reempaque"
                                   id="cantidad_reempaque" min="0" value="<?php echo $documento['cantidad_reempaque']; ?>">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Envío a Provincia</label>
                            <div class="checkbox-group">
                                <input type="checkbox" name="envio_provincia_check" id="envio_provincia_check" value="SI"
                                       <?php echo ($documento['envio_provincia'] === 'SI') ? 'checked' : ''; ?>>
                                <label for="envio_provincia_check" style="margin: 0;">Aplica envío a provincia ($3)</label>
                            </div>
                            <input type="hidden" name="envio_provincia" id="envio_provincia" value="<?php echo $documento['envio_provincia']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Pendiente de Pago (Automático)</label>
                            <input type="text" class="form-control readonly-field" id="pendiente_pago_display" readonly>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Gastos Adicionales ($)</label>
                            <input type="number" class="form-control" name="gastos_adicionales"
                                   id="gastos_adicionales" step="0.01" min="0" value="<?php echo $documento['gastos_adicionales']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Detalle de Gastos Adicionales</label>
                            <input type="text" class="form-control" name="detalle_gastos_adicionales"
                                   id="detalle_gastos_adicionales" value="<?php echo htmlspecialchars($documento['detalle_gastos_adicionales']); ?>">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Descuento ($)</label>
                            <input type="number" class="form-control" name="descuento"
                                   id="descuento" step="0.01" min="0" value="<?php echo $documento['descuento']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Detalle de Descuento</label>
                            <input type="text" class="form-control" name="detalle_descuento"
                                   id="detalle_descuento" value="<?php echo htmlspecialchars($documento['detalle_descuento']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Canal de Aduanas</label>
                        <select class="form-control" name="canal_aduanas" id="canal_aduanas">
                            <option value="">-- Seleccionar --</option>
                            <option value="VERDE" <?php echo ($documento['canal_aduanas'] === 'VERDE') ? 'selected' : ''; ?>>🟢 CANAL VERDE</option>
                            <option value="NARANJA" <?php echo ($documento['canal_aduanas'] === 'NARANJA') ? 'selected' : ''; ?>>🟠 CANAL NARANJA</option>
                            <option value="ROJO" <?php echo ($documento['canal_aduanas'] === 'ROJO') ? 'selected' : ''; ?>>🔴 CANAL ROJO</option>
                        </select>
                    </div>

                    <div class="section-title">📷 Imagen Adjunta</div>
                    <?php if (!empty($documento['imagen_adjunta']) && file_exists($documento['imagen_adjunta'])): ?>
                    <div class="form-group">
                        <label>Imagen Actual</label>
                        <div style="margin-bottom: 15px;">
                            <img src="<?php echo $documento['imagen_adjunta']; ?>" alt="Imagen adjunta"
                                 style="max-width: 300px; max-height: 300px; border: 2px solid #ddd; border-radius: 8px; display: block;">
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="eliminar_imagen" id="eliminar_imagen" value="SI">
                            <label for="eliminar_imagen" style="margin: 0; color: #e74c3c;">Eliminar imagen actual</label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label><?php echo !empty($documento['imagen_adjunta']) ? 'Cambiar Imagen' : 'Adjuntar Imagen'; ?></label>
                        <input type="file" class="form-control" name="imagen" id="imagen"
                               accept="image/png,image/jpeg,image/jpg">
                        <small class="info-text">Formatos permitidos: PNG, JPEG, JPG (Máximo 5MB)</small>
                    </div>
                </div>
                <div class="card totales-box">
                    <div class="section-title">💵 Resumen de Totales</div>
                    <div id="subtotal_row">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span id="subtotal_display">$0.00</span>
                        </div>
                        <div class="total-row">
                            <span>IGV (18%):</span>
                            <span id="igv_display">$0.00</span>
                        </div>
                    </div>
                    <div class="total-row final">
                        <span>TOTAL:</span>
                        <span id="total_display">$0.00</span>
                    </div>
                </div>
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">💾 Actualizar Documento</button>
                    <a href="index.php" class="btn btn-back">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
    <script>
        let pendientePago = <?php echo $documento['pendiente_pago']; ?>;
        const tipoDoc = '<?php echo $documento['tipo_documento']; ?>';

        function calcularTotales() {
            const peso = parseFloat(document.getElementById('peso_total').value) || 0;
            const costoPeso = peso < 1 ? 10.00 : (peso * 10.00);
            const totalGuias = parseInt(document.getElementById('total_guias').value) || 0;
            const costoDesaduanaje = totalGuias * 5.00;
            const cantidadCambio = parseInt(document.getElementById('cantidad_cambio_consignatario').value) || 0;
            const costoCambio = cantidadCambio * 3.00;
            const cantidadReempaque = parseInt(document.getElementById('cantidad_reempaque').value) || 0;
            const costoReempaque = cantidadReempaque * 5.00;
            const envioCheck = document.getElementById('envio_provincia_check').checked;
            const costoEnvio = envioCheck ? 3.00 : 0.00;
            document.getElementById('envio_provincia').value = envioCheck ? 'SI' : 'NO';
            const gastosAdicionales = parseFloat(document.getElementById('gastos_adicionales').value) || 0;
            const descuento = parseFloat(document.getElementById('descuento').value) || 0;

            let subtotal = costoPeso + costoDesaduanaje + costoCambio + costoReempaque +
                          costoEnvio + pendientePago + gastosAdicionales - descuento;

            let igv = 0, total = 0;
            if (tipoDoc === 'FACTURA' || tipoDoc === 'BOLETA') {
                igv = subtotal * 0.18;
                total = subtotal + igv;
                document.getElementById('subtotal_row').style.display = 'block';
                document.getElementById('subtotal_display').textContent = '$' + subtotal.toFixed(2);
                document.getElementById('igv_display').textContent = '$' + igv.toFixed(2);
            } else {
                total = subtotal;
                document.getElementById('subtotal_row').style.display = 'none';
            }

            document.getElementById('costo_peso_display').value = '$' + costoPeso.toFixed(2);
            document.getElementById('total_display').textContent = '$' + total.toFixed(2);
        }

        document.getElementById('peso_total').addEventListener('input', calcularTotales);
        document.getElementById('total_guias').addEventListener('input', calcularTotales);
        document.getElementById('cantidad_cambio_consignatario').addEventListener('input', calcularTotales);
        document.getElementById('cantidad_reempaque').addEventListener('input', calcularTotales);
        document.getElementById('envio_provincia_check').addEventListener('change', calcularTotales);
        document.getElementById('gastos_adicionales').addEventListener('input', calcularTotales);
        document.getElementById('descuento').addEventListener('input', calcularTotales);

        document.getElementById('cliente_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            pendientePago = parseFloat(selectedOption.dataset.pendiente) || 0;
            document.getElementById('pendiente_pago_display').value = '$' + pendientePago.toFixed(2);
            calcularTotales();
        });

        // Cargar valores iniciales
        window.addEventListener('load', function() {
            const selectCliente = document.getElementById('cliente_id');
            const selectedOption = selectCliente.options[selectCliente.selectedIndex];
            pendientePago = parseFloat(selectedOption.dataset.pendiente) || 0;
            document.getElementById('pendiente_pago_display').value = '$' + pendientePago.toFixed(2);
            calcularTotales();
        });
    </script>
</body>
</html>

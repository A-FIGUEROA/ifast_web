<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once 'funciones_pdf.php';

requiereLogin(); // Todos los usuarios pueden crear facturas

$database = new Database();
$conn = $database->getConnection();
$errores = [];

// Obtener lista de clientes
$stmt = $conn->query("SELECT id, tipo_documento, documento, nombre_razon_social, apellido FROM clientes ORDER BY nombre_razon_social ASC");
$clientes = $stmt->fetchAll();

// Función para obtener el siguiente número de documento
function obtenerSiguienteNumero($conn, $tipo) {
    $stmt = $conn->prepare("SELECT prefijo, ultimo_numero FROM numeracion_documentos WHERE tipo = :tipo FOR UPDATE");
    $stmt->bindParam(':tipo', $tipo);
    $stmt->execute();
    $resultado = $stmt->fetch();

    // Validar que exista el registro
    if (!$resultado) {
        throw new Exception("No existe configuración de numeración para el tipo de documento: $tipo. Por favor contacte al administrador.");
    }

    // Validar que el prefijo no esté vacío
    if (empty($resultado['prefijo'])) {
        throw new Exception("El prefijo para el tipo de documento '$tipo' está vacío. Por favor contacte al administrador.");
    }

    $nuevo_numero = $resultado['ultimo_numero'] + 1;
    $numero_formateado = $resultado['prefijo'] . '-' . str_pad($nuevo_numero, 5, '0', STR_PAD_LEFT);

    // Actualizar el contador
    $stmt_update = $conn->prepare("UPDATE numeracion_documentos SET ultimo_numero = :numero WHERE tipo = :tipo");
    $stmt_update->bindParam(':numero', $nuevo_numero);
    $stmt_update->bindParam(':tipo', $tipo);
    $stmt_update->execute();

    return $numero_formateado;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_documento = $_POST['tipo_documento'];
    $cliente_id = (int)$_POST['cliente_id'];
    $modo_creacion = $_POST['modo_creacion'] ?? 'MANUAL';
    $tarifa_aplicada = $_POST['tarifa_aplicada'] ?? 'TARIFA_1';

    // Guías asociadas (solo si es modo DESDE_GUIA)
    $guias_asociadas = '';
    if ($modo_creacion === 'DESDE_GUIA') {
        $guias_ids = isset($_POST['guias_seleccionadas']) ? $_POST['guias_seleccionadas'] : [];
        if (!empty($guias_ids) && is_array($guias_ids)) {
            $guias_asociadas = implode(',', $guias_ids);
        }
    }

    // Datos de cálculo con validación mejorada
    $peso_total = filter_var($_POST['peso_total'], FILTER_VALIDATE_FLOAT);
    $peso_ajustado_calculado = filter_var($_POST['peso_ajustado_calculado'] ?? 0, FILTER_VALIDATE_FLOAT);
    $total_paquetes = (int)$_POST['total_paquetes'];
    $total_guias = (int)$_POST['total_guias'];
    $cantidad_cambio_consignatario = (int)($_POST['cantidad_cambio_consignatario'] ?? 0);
    $cantidad_reempaque = (int)($_POST['cantidad_reempaque'] ?? 0);
    $envio_provincia = $_POST['envio_provincia'] ?? 'NO';
    $gastos_adicionales = filter_var($_POST['gastos_adicionales'] ?? 0, FILTER_VALIDATE_FLOAT);
    $detalle_gastos_adicionales = $_POST['detalle_gastos_adicionales'] ?? '';
    $descuento = filter_var($_POST['descuento'] ?? 0, FILTER_VALIDATE_FLOAT);
    $detalle_descuento = $_POST['detalle_descuento'] ?? '';
    $canal_aduanas = $_POST['canal_aduanas'] ?? '';

    // Pedidos seleccionados (IDs de pedidos pendientes)
    $pedidos_seleccionados = isset($_POST['pedidos_seleccionados']) ? $_POST['pedidos_seleccionados'] : [];

    // Validaciones
    if (empty($tipo_documento) || !in_array($tipo_documento, ['FACTURA', 'BOLETA', 'RECIBO'])) {
        $errores[] = "Tipo de documento inválido";
    }

    if ($cliente_id <= 0) {
        $errores[] = "Debe seleccionar un cliente";
    }

    if ($peso_total === false || $peso_total <= 0) {
        $errores[] = "El peso total debe ser un número válido mayor a 0";
    }

    if ($gastos_adicionales === false) $gastos_adicionales = 0;
    if ($descuento === false) $descuento = 0;
    if ($peso_ajustado_calculado === false) $peso_ajustado_calculado = 0;

    if (empty($errores)) {
        // Validar si el cliente tiene pedidos pendientes (debe seleccionar al menos uno)
        $stmt_check_pedidos = $conn->prepare("
            SELECT COUNT(*)
            FROM recibos_pedidos
            WHERE cliente_id = :cliente_id AND pendiente_pago = 'SI' AND monto_pendiente > 0
        ");
        $stmt_check_pedidos->bindParam(':cliente_id', $cliente_id);
        $stmt_check_pedidos->execute();
        $tiene_pedidos_pendientes = $stmt_check_pedidos->fetchColumn() > 0;

        // Si tiene pedidos pendientes, debe seleccionar al menos uno
        if ($tiene_pedidos_pendientes && empty($pedidos_seleccionados)) {
            $errores[] = "Este cliente tiene pedidos pendientes de pago. Debe seleccionar al menos uno para incluir en la factura.";
        }
    }

    if (empty($errores)) {
        try {
            $conn->beginTransaction();

            // Generar número de documento
            $numero_documento = obtenerSiguienteNumero($conn, $tipo_documento);

            // Definir tarifas según selección
            $tarifa_peso = 10.00;
            $tarifa_desaduanaje = 5.00;

            switch ($tarifa_aplicada) {
                case 'TARIFA_1':
                    $tarifa_peso = 10.00;
                    $tarifa_desaduanaje = 5.00;
                    break;
                case 'TARIFA_2':
                    $tarifa_peso = 9.50;
                    $tarifa_desaduanaje = 5.00;
                    break;
                case 'TARIFA_3':
                    $tarifa_peso = 9.90;
                    $tarifa_desaduanaje = 0.00;
                    break;
                default:
                    $tarifa_peso = 10.00;
                    $tarifa_desaduanaje = 5.00;
            }

            // Calcular costos
            // 1. Costo por peso
            if ($modo_creacion === 'DESDE_GUIA' && !empty($guias_asociadas)) {
                // RECALCULAR peso ajustado desde las guías para validar seguridad
                $guias_ids_array = explode(',', $guias_asociadas);
                $placeholders_guias = implode(',', array_fill(0, count($guias_ids_array), '?'));

                $stmt_validar = $conn->prepare("SELECT peso_kg FROM guias_masivas WHERE id IN ($placeholders_guias)");
                $stmt_validar->execute($guias_ids_array);
                $guias_pesos = $stmt_validar->fetchAll(PDO::FETCH_COLUMN);

                // Recalcular peso ajustado (aplicar regla < 1kg = 1kg)
                $peso_ajustado_recalculado = 0;
                foreach ($guias_pesos as $peso) {
                    $peso_ajustado_recalculado += ($peso < 1) ? 1.0 : floatval($peso);
                }

                // Usar el valor recalculado en lugar del que viene del form (seguridad)
                $peso_ajustado_calculado = $peso_ajustado_recalculado;
                $costo_peso = round($peso_ajustado_calculado * $tarifa_peso, 2);
            } else {
                // Modo MANUAL: aplicar regla de peso mínimo (< 1kg = tarifa mínima, >= 1kg = peso x tarifa)
                $costo_peso = round(($peso_total < 1) ? $tarifa_peso : ($peso_total * $tarifa_peso), 2);
            }

            // 2. Desaduanaje (tarifa variable según selección)
            $costo_desaduanaje = round($total_guias * $tarifa_desaduanaje, 2);

            // 3. Cambio de consignatario ($3 x cantidad)
            $costo_cambio_consignatario = round($cantidad_cambio_consignatario * 3.00, 2);

            // 4. Reempaque ($5 x cantidad)
            $costo_reempaque = round($cantidad_reempaque * 5.00, 2);

            // 5. Envío a provincia ($3 si aplica)
            $costo_envio_provincia = ($envio_provincia === 'SI') ? 3.00 : 0.00;

            // 6. Calcular pendiente de pago SOLO de los pedidos seleccionados
            $pendiente_pago = 0.00;
            if (!empty($pedidos_seleccionados) && is_array($pedidos_seleccionados)) {
                $placeholders = implode(',', array_fill(0, count($pedidos_seleccionados), '?'));
                $stmt_pendiente = $conn->prepare("SELECT SUM(monto_pendiente) as total_pendiente
                                                  FROM recibos_pedidos
                                                  WHERE id IN ($placeholders) AND pendiente_pago = 'SI'");
                $stmt_pendiente->execute($pedidos_seleccionados);
                $pendiente_resultado = $stmt_pendiente->fetch();
                $pendiente_pago = round($pendiente_resultado['total_pendiente'] ?? 0.00, 2);
            }

            // Calcular subtotal con redondeo
            $subtotal = round($costo_peso + $costo_desaduanaje + $costo_cambio_consignatario +
                       $costo_reempaque + $costo_envio_provincia + $pendiente_pago + $gastos_adicionales - $descuento, 2);

            // Calcular IGV y total con redondeo
            if ($tipo_documento === 'FACTURA' || $tipo_documento === 'BOLETA') {
                $igv = round($subtotal * 0.18, 2);
                $total = round($subtotal + $igv, 2);
            } else {
                $igv = 0.00;
                $total = round($subtotal, 2);
                $subtotal = 0.00; // Para recibo no mostramos subtotal
            }

            // Obtener datos del cliente para el nombre del archivo
            $stmt_cliente = $conn->prepare("SELECT nombre_razon_social, apellido FROM clientes WHERE id = :id");
            $stmt_cliente->bindParam(':id', $cliente_id);
            $stmt_cliente->execute();
            $cliente = $stmt_cliente->fetch();

            $nombre_cliente = $cliente['nombre_razon_social'];
            if (!empty($cliente['apellido'])) {
                $nombre_cliente .= ' ' . $cliente['apellido'];
            }

            // Nombre del archivo: "FV-00001 Juan Perez.pdf"
            $nombre_archivo = $numero_documento . ' ' . $nombre_cliente . '.pdf';
            $ruta_archivo = '../../uploads/facturas/' . $nombre_archivo;

            // Insertar en base de datos
            $usuario_id = $_SESSION['usuario_id'];

            // Manejar upload de imagen
            $imagen_adjunta = null;
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
                    $directorio_imagenes = '../../uploads/facturas/imagenes/';
                    if (!file_exists($directorio_imagenes)) {
                        mkdir($directorio_imagenes, 0777, true);
                    }

                    $nombre_imagen = 'IMG_' . $numero_documento . '_' . time() . '.' . $file_ext;
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
                $conn->rollBack();
            } else {
                $sql = "INSERT INTO documentos_facturacion (
                            tipo_documento, numero_documento, cliente_id,
                            peso_total, peso_ajustado_calculado, costo_peso,
                            total_paquetes, total_guias, costo_desaduanaje,
                            cantidad_cambio_consignatario, costo_cambio_consignatario,
                            cantidad_reempaque, costo_reempaque,
                            envio_provincia, costo_envio_provincia,
                            pendiente_pago,
                            gastos_adicionales, detalle_gastos_adicionales,
                            descuento, detalle_descuento,
                            canal_aduanas,
                            guias_asociadas, modo_creacion, tarifa_aplicada,
                            subtotal, igv, total,
                            nombre_archivo, ruta_archivo,
                            imagen_adjunta,
                            creado_por
                        ) VALUES (
                            :tipo_documento, :numero_documento, :cliente_id,
                            :peso_total, :peso_ajustado_calculado, :costo_peso,
                            :total_paquetes, :total_guias, :costo_desaduanaje,
                            :cantidad_cambio_consignatario, :costo_cambio_consignatario,
                            :cantidad_reempaque, :costo_reempaque,
                            :envio_provincia, :costo_envio_provincia,
                            :pendiente_pago,
                            :gastos_adicionales, :detalle_gastos_adicionales,
                            :descuento, :detalle_descuento,
                            :canal_aduanas,
                            :guias_asociadas, :modo_creacion, :tarifa_aplicada,
                            :subtotal, :igv, :total,
                            :nombre_archivo, :ruta_archivo,
                            :imagen_adjunta,
                            :creado_por
                        )";

                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':tipo_documento', $tipo_documento);
                $stmt->bindParam(':numero_documento', $numero_documento);
                $stmt->bindParam(':cliente_id', $cliente_id);
                $stmt->bindParam(':peso_total', $peso_total);
                $stmt->bindParam(':peso_ajustado_calculado', $peso_ajustado_calculado);
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
                $stmt->bindParam(':guias_asociadas', $guias_asociadas);
                $stmt->bindParam(':modo_creacion', $modo_creacion);
                $stmt->bindParam(':tarifa_aplicada', $tarifa_aplicada);
                $stmt->bindParam(':subtotal', $subtotal);
                $stmt->bindParam(':igv', $igv);
                $stmt->bindParam(':total', $total);
                $stmt->bindParam(':nombre_archivo', $nombre_archivo);
                $stmt->bindParam(':ruta_archivo', $ruta_archivo);
                $stmt->bindParam(':imagen_adjunta', $imagen_adjunta);
                $stmt->bindParam(':creado_por', $usuario_id);

                $stmt->execute();

                // Obtener ID del documento insertado
                $documento_id = $conn->lastInsertId();

                // Si es modo DESDE_GUIA, marcar las guías como facturadas
                if ($modo_creacion === 'DESDE_GUIA' && !empty($guias_asociadas)) {
                    $guias_ids_array = explode(',', $guias_asociadas);
                    $placeholders = implode(',', array_fill(0, count($guias_ids_array), '?'));

                    $stmt_update = $conn->prepare("UPDATE guias_masivas SET facturado = 'SI' WHERE id IN ($placeholders)");
                    $stmt_update->execute($guias_ids_array);
                }

                // Marcar pedidos seleccionados como facturados (pendiente_pago = 'NO')
                if (!empty($pedidos_seleccionados) && is_array($pedidos_seleccionados)) {
                    $placeholders_pedidos = implode(',', array_fill(0, count($pedidos_seleccionados), '?'));
                    $stmt_update_pedidos = $conn->prepare("UPDATE recibos_pedidos SET pendiente_pago = 'NO' WHERE id IN ($placeholders_pedidos)");
                    $stmt_update_pedidos->execute($pedidos_seleccionados);
                }

                // Generar y guardar el PDF físicamente
                $ruta_pdf_completa = __DIR__ . '/../../uploads/facturas/' . $nombre_archivo;
                if (generarYGuardarPDF($conn, $documento_id, $ruta_pdf_completa)) {
                    // PDF generado correctamente
                    $conn->commit();
                    header("Location: index.php?success=" . urlencode("Documento $numero_documento creado exitosamente"));
                    exit();
                } else {
                    $conn->rollBack();
                    $errores[] = "Error al generar el PDF del documento";
                }
            }

        } catch (PDOException $e) {
            $conn->rollBack();
            $errores[] = "Error al crear el documento: " . $e->getMessage();
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
    <title>Crear Documento de Facturación</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group .required {
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
            border-color: #00509d;
            box-shadow: 0 0 0 3px rgba(0, 80, 157, 0.1);
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }

        .section-title {
            font-size: 1.2rem;
            color: #00296b;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            font-weight: 600;
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

        .btn-back:hover {
            background: #7f8c8d;
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

        .info-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }

        .totales-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #00509d;
        }

        .totales-box .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 1.1rem;
        }

        .totales-box .total-row.final {
            border-top: 2px solid #00296b;
            margin-top: 10px;
            padding-top: 15px;
            font-weight: 700;
            font-size: 1.3rem;
            color: #00296b;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .readonly-field {
            background: #e9ecef;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1>📄 Crear Documento de Facturación</h1>
            <div>
                <a href="index.php" class="btn btn-back">← Volver</a>
            </div>
        </header>

        <div class="content">
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

            <form method="POST" id="formFacturacion" enctype="multipart/form-data">
                <div class="card">
                    <div class="section-title">📋 Tipo de Documento</div>
                    <div class="form-group">
                        <label>Tipo de Documento <span class="required">*</span></label>
                        <select class="form-control" name="tipo_documento" id="tipo_documento" required>
                            <option value="">-- Seleccionar --</option>
                            <option value="FACTURA">FACTURA (con IGV 18%)</option>
                            <option value="BOLETA">BOLETA (con IGV 18%)</option>
                            <option value="RECIBO">RECIBO (sin IGV)</option>
                        </select>
                    </div>

                    <div class="section-title">⚙️ Modo de Creación</div>
                    <div class="form-group">
                        <div style="display: flex; gap: 20px; align-items: center;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="radio" name="modo_creacion" value="MANUAL" id="modo_manual" checked style="width: 20px; height: 20px;">
                                <span style="font-weight: 600;">✍️ Entrada Manual</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="radio" name="modo_creacion" value="DESDE_GUIA" id="modo_desde_guia" style="width: 20px; height: 20px;">
                                <span style="font-weight: 600;">📦 Desde Guías</span>
                            </label>
                        </div>
                        <small class="info-text">Selecciona "Desde Guías" para crear factura automáticamente desde guías existentes</small>
                    </div>

                    <div class="section-title">💵 Seleccionar Tarifa a Aplicar</div>
                    <div class="form-group">
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; transition: all 0.3s;">
                                <input type="radio" name="tarifa_aplicada" value="TARIFA_1" id="tarifa_1" checked style="width: 20px; height: 20px;">
                                <div>
                                    <span style="font-weight: 600; color: #00296b;">TARIFA 1:</span>
                                    <span style="color: #555;"> $10.00/kg + $5.00 Desaduanaje por guía</span>
                                </div>
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; transition: all 0.3s;">
                                <input type="radio" name="tarifa_aplicada" value="TARIFA_2" id="tarifa_2" style="width: 20px; height: 20px;">
                                <div>
                                    <span style="font-weight: 600; color: #00296b;">TARIFA 2:</span>
                                    <span style="color: #555;"> $9.50/kg + $5.00 Desaduanaje por guía</span>
                                </div>
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; transition: all 0.3s;">
                                <input type="radio" name="tarifa_aplicada" value="TARIFA_3" id="tarifa_3" style="width: 20px; height: 20px;">
                                <div>
                                    <span style="font-weight: 600; color: #00296b;">TARIFA 3:</span>
                                    <span style="color: #555;"> $9.90/kg (Sin Desaduanaje)</span>
                                </div>
                            </label>
                        </div>
                        <small class="info-text">La tarifa seleccionada afectará el cálculo del costo por peso y desaduanaje</small>
                    </div>

                    <div class="section-title">👤 Datos del Cliente</div>
                    <div class="form-group">
                        <label>Cliente <span class="required">*</span></label>
                        <select class="form-control" name="cliente_id" id="cliente_id" required>
                            <option value="">-- Seleccionar cliente --</option>
                            <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>">
                                <?php
                                echo $cliente['nombre_razon_social'];
                                if ($cliente['apellido']) echo ' ' . $cliente['apellido'];
                                echo ' - ' . $cliente['tipo_documento'] . ': ' . $cliente['documento'];
                                ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- SECCIÓN GUÍAS (solo visible en modo DESDE_GUIA) -->
                    <div id="seccion_guias" style="display: none;">
                        <div class="section-title">📦 Seleccionar Guías</div>
                        <div class="form-group">
                            <div id="guias_loading" style="display: none; padding: 20px; text-align: center; color: #666;">
                                <i class='bx bx-loader-alt bx-spin' style="font-size: 2rem;"></i>
                                <p>Cargando guías...</p>
                            </div>
                            <div id="guias_vacio" style="display: none; padding: 20px; text-align: center; color: #666; background: #f8f9fa; border-radius: 8px;">
                                <i class='bx bx-package' style="font-size: 2rem;"></i>
                                <p>No hay guías sin facturar para este cliente</p>
                            </div>
                            <div id="guias_container" style="max-height: 400px; overflow-y: auto; border: 2px solid #e0e0e0; border-radius: 8px; padding: 15px;"></div>
                        </div>
                    </div>

                    <!-- SECCIÓN PEDIDOS PENDIENTES -->
                    <div id="seccion_pedidos_pendientes" style="display: none;">
                        <div class="section-title">💰 Seleccionar Pedidos Pendientes de Pago</div>
                        <div class="form-group">
                            <div id="pedidos_loading" style="display: none; padding: 20px; text-align: center; color: #666;">
                                <i class='bx bx-loader-alt bx-spin' style="font-size: 2rem;"></i>
                                <p>Cargando pedidos pendientes...</p>
                            </div>
                            <div id="pedidos_vacio" style="display: none; padding: 20px; text-align: center; color: #666; background: #f8f9fa; border-radius: 8px;">
                                <i class='bx bx-money' style="font-size: 2rem;"></i>
                                <p>Este cliente no tiene pedidos pendientes de pago</p>
                            </div>
                            <div id="pedidos_container" style="max-height: 400px; overflow-y: auto; border: 2px solid #e0e0e0; border-radius: 8px; padding: 15px;"></div>
                        </div>
                        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196f3; margin-top: 15px;">
                            <strong>Total Pendiente Seleccionado:</strong>
                            <span id="total_pedidos_seleccionados" style="font-size: 1.3rem; font-weight: bold; color: #00296b; margin-left: 10px;">$0.00</span>
                        </div>
                    </div>

                    <div class="section-title">💰 Detalles de Costos</div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Peso Total (kg) <span class="required">*</span></label>
                            <input type="number" class="form-control" name="peso_total" id="peso_total"
                                   step="0.001" min="0" placeholder="0.000" required>
                            <small class="info-text">Menos de 1kg = $10 fijo | De 1kg en adelante = peso x $10</small>
                        </div>

                        <div class="form-group">
                            <label>Costo por Peso</label>
                            <input type="text" class="form-control readonly-field" id="costo_peso_display"
                                   value="$0.00" readonly>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Total de Paquetes</label>
                            <input type="number" class="form-control" name="total_paquetes" id="total_paquetes"
                                   min="0" value="0">
                        </div>

                        <div class="form-group">
                            <label>Total de Guías <span class="required">*</span></label>
                            <input type="number" class="form-control" name="total_guias" id="total_guias"
                                   min="0" value="0" required>
                            <small class="info-text">Desaduanaje: $5 x guía</small>
                        </div>
                    </div>

                    <!-- Campo oculto para peso ajustado (modo DESDE_GUIA) -->
                    <input type="hidden" name="peso_ajustado_calculado" id="peso_ajustado_calculado" value="0">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Cantidad Cambio de Consignatario</label>
                            <input type="number" class="form-control" name="cantidad_cambio_consignatario"
                                   id="cantidad_cambio_consignatario" min="0" value="0">
                            <small class="info-text">$3 por unidad</small>
                        </div>

                        <div class="form-group">
                            <label>Cantidad de Reempaques</label>
                            <input type="number" class="form-control" name="cantidad_reempaque"
                                   id="cantidad_reempaque" min="0" value="0">
                            <small class="info-text">$5 por unidad</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Envío a Provincia</label>
                        <div class="checkbox-group">
                            <input type="checkbox" name="envio_provincia_check" id="envio_provincia_check" value="SI">
                            <label for="envio_provincia_check" style="margin: 0;">Aplica envío a provincia ($3)</label>
                        </div>
                        <input type="hidden" name="envio_provincia" id="envio_provincia" value="NO">
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Gastos Adicionales ($)</label>
                            <input type="number" class="form-control" name="gastos_adicionales"
                                   id="gastos_adicionales" step="0.01" min="0" value="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label>Detalle de Gastos Adicionales</label>
                            <input type="text" class="form-control" name="detalle_gastos_adicionales"
                                   id="detalle_gastos_adicionales" placeholder="Descripción...">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Descuento ($)</label>
                            <input type="number" class="form-control" name="descuento"
                                   id="descuento" step="0.01" min="0" value="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label>Detalle de Descuento</label>
                            <input type="text" class="form-control" name="detalle_descuento"
                                   id="detalle_descuento" placeholder="Descripción del descuento...">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Canal de Aduanas</label>
                        <select class="form-control" name="canal_aduanas" id="canal_aduanas">
                            <option value="">-- Seleccionar --</option>
                            <option value="VERDE">🟢 CANAL VERDE</option>
                            <option value="NARANJA">🟠 CANAL NARANJA</option>
                            <option value="ROJO">🔴 CANAL ROJO</option>
                        </select>
                        <small class="info-text">Solo informativo</small>
                    </div>

                    <div class="section-title">📷 Imagen Adjunta (Opcional)</div>
                    <div class="form-group">
                        <label>Adjuntar Imagen</label>
                        <input type="file" class="form-control" name="imagen" id="imagen"
                               accept="image/png,image/jpeg,image/jpg">
                        <small class="info-text">Formatos permitidos: PNG, JPEG, JPG (Máximo 5MB)</small>
                    </div>
                </div>

                <div class="card totales-box">
                    <div class="section-title">💵 Resumen de Totales</div>
                    <div id="subtotal_row" style="display: none;">
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
                    <button type="submit" class="btn btn-primary">💾 Generar Documento</button>
                    <a href="index.php" class="btn btn-back">Cancelar</a>
                </div>
            </form>
        </div>
    </main>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
    <script>
        // Variables para almacenar valores
        let pendientePago = 0;
        let modoActual = 'MANUAL';
        let guiasSeleccionadas = [];
        let pesoAjustadoGuias = 0; // Peso ajustado para cálculo de costo
        let pedidosSeleccionados = []; // IDs de pedidos seleccionados
        let pedidosDisponibles = []; // Pedidos pendientes del cliente
        let tienePedidosPendientes = false; // Flag para validación

        // Función para calcular totales
        function calcularTotales() {
            const tipoDoc = document.getElementById('tipo_documento').value;

            // Obtener tarifa seleccionada
            const tarifaSeleccionada = document.querySelector('input[name="tarifa_aplicada"]:checked').value;

            // Definir tarifas según selección
            let tarifaPeso = 10.00;
            let tarifaDesaduanaje = 5.00;

            switch (tarifaSeleccionada) {
                case 'TARIFA_1':
                    tarifaPeso = 10.00;
                    tarifaDesaduanaje = 5.00;
                    break;
                case 'TARIFA_2':
                    tarifaPeso = 9.50;
                    tarifaDesaduanaje = 5.00;
                    break;
                case 'TARIFA_3':
                    tarifaPeso = 9.90;
                    tarifaDesaduanaje = 0.00;
                    break;
            }

            // 1. Costo por peso
            const peso = parseFloat(document.getElementById('peso_total').value) || 0;
            let costoPeso;

            if (modoActual === 'DESDE_GUIA' && pesoAjustadoGuias > 0) {
                // Modo DESDE_GUIA: usar peso ajustado directamente
                costoPeso = pesoAjustadoGuias * tarifaPeso;
            } else {
                // Modo MANUAL: aplicar regla de peso mínimo
                costoPeso = peso < 1 ? tarifaPeso : (peso * tarifaPeso);
            }

            // 2. Desaduanaje (tarifa variable)
            const totalGuias = parseInt(document.getElementById('total_guias').value) || 0;
            const costoDesaduanaje = totalGuias * tarifaDesaduanaje;

            // 3. Cambio consignatario
            const cantidadCambio = parseInt(document.getElementById('cantidad_cambio_consignatario').value) || 0;
            const costoCambio = cantidadCambio * 3.00;

            // 4. Reempaque
            const cantidadReempaque = parseInt(document.getElementById('cantidad_reempaque').value) || 0;
            const costoReempaque = cantidadReempaque * 5.00;

            // 5. Envío provincia
            const envioCheck = document.getElementById('envio_provincia_check').checked;
            const costoEnvio = envioCheck ? 3.00 : 0.00;
            document.getElementById('envio_provincia').value = envioCheck ? 'SI' : 'NO';

            // 6. Gastos adicionales
            const gastosAdicionales = parseFloat(document.getElementById('gastos_adicionales').value) || 0;

            // 7. Descuento
            const descuento = parseFloat(document.getElementById('descuento').value) || 0;

            // Subtotal
            let subtotal = costoPeso + costoDesaduanaje + costoCambio + costoReempaque +
                          costoEnvio + pendientePago + gastosAdicionales - descuento;

            // IGV y Total
            let igv = 0;
            let total = 0;

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

            // Actualizar displays
            document.getElementById('costo_peso_display').value = '$' + costoPeso.toFixed(2);
            document.getElementById('total_display').textContent = '$' + total.toFixed(2);
        }

        // ========================================
        // FUNCIONES PARA MODO DESDE_GUIA
        // ========================================

        // Cambio de modo (Manual <-> Desde Guía)
        function cambiarModo() {
            modoActual = document.querySelector('input[name="modo_creacion"]:checked').value;

            if (modoActual === 'DESDE_GUIA') {
                document.getElementById('seccion_guias').style.display = 'block';
                // Hacer campos de solo lectura
                document.getElementById('peso_total').readOnly = true;
                document.getElementById('total_paquetes').readOnly = true;
                document.getElementById('total_guias').readOnly = true;
                document.getElementById('peso_total').classList.add('readonly-field');
                document.getElementById('total_paquetes').classList.add('readonly-field');
                document.getElementById('total_guias').classList.add('readonly-field');

                // Cargar guías si hay cliente seleccionado
                const clienteId = document.getElementById('cliente_id').value;
                if (clienteId) {
                    cargarGuias(clienteId);
                }
            } else {
                document.getElementById('seccion_guias').style.display = 'none';
                // Permitir edición manual
                document.getElementById('peso_total').readOnly = false;
                document.getElementById('total_paquetes').readOnly = false;
                document.getElementById('total_guias').readOnly = false;
                document.getElementById('peso_total').classList.remove('readonly-field');
                document.getElementById('total_paquetes').classList.remove('readonly-field');
                document.getElementById('total_guias').classList.remove('readonly-field');

                // Limpiar valores
                document.getElementById('peso_total').value = '';
                document.getElementById('total_paquetes').value = '0';
                document.getElementById('total_guias').value = '0';
                document.getElementById('peso_ajustado_calculado').value = '0'; // Resetear campo hidden
                guiasSeleccionadas = [];
                pesoAjustadoGuias = 0; // Resetear peso ajustado
                calcularTotales();
            }
        }

        // Cargar guías desde la API
        function cargarGuias(clienteId) {
            document.getElementById('guias_loading').style.display = 'block';
            document.getElementById('guias_vacio').style.display = 'none';
            document.getElementById('guias_container').innerHTML = '';

            fetch(`api_guias.php?cliente_id=${clienteId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('guias_loading').style.display = 'none';

                    if (data.success && data.guias.length > 0) {
                        renderizarGuias(data.guias);
                    } else {
                        document.getElementById('guias_vacio').style.display = 'block';
                    }
                })
                .catch(error => {
                    document.getElementById('guias_loading').style.display = 'none';
                    console.error('Error al cargar guías:', error);
                    alert('Error al cargar las guías. Por favor intenta nuevamente.');
                });
        }

        // Renderizar lista de guías con checkboxes
        function renderizarGuias(guias) {
            const container = document.getElementById('guias_container');
            container.innerHTML = '';

            guias.forEach(guia => {
                const guiaCard = document.createElement('div');
                guiaCard.style.cssText = 'border: 2px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 10px; background: white; transition: all 0.3s;';
                guiaCard.innerHTML = `
                    <label style="display: flex; gap: 15px; cursor: pointer; align-items: start;">
                        <input type="checkbox" name="guias_seleccionadas[]" value="${guia.id}"
                               data-peso="${guia.peso_kg}"
                               data-pcs="${guia.pcs}"
                               onchange="actualizarSeleccionGuias()"
                               style="width: 20px; height: 20px; margin-top: 5px;">
                        <div style="flex: 1;">
                            <div style="font-weight: 700; font-size: 1rem; color: #00296b; margin-bottom: 5px;">
                                📦 ${guia.nro_guia}
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; font-size: 0.9rem; color: #555;">
                                <div><strong>Consignatario:</strong> ${guia.consignatario}</div>
                                <div><strong>PCS:</strong> ${guia.pcs}</div>
                                <div><strong>Peso:</strong> ${parseFloat(guia.peso_kg).toFixed(2)} kg</div>
                                <div><strong>Valor FOB:</strong> $${parseFloat(guia.valor_fob_usd).toFixed(2)}</div>
                            </div>
                            <div style="margin-top: 5px; font-size: 0.85rem; color: #666;">
                                <strong>Descripción:</strong> ${guia.descripcion || '-'}
                            </div>
                        </div>
                    </label>
                `;

                // Hover effect
                guiaCard.addEventListener('mouseenter', function() {
                    this.style.borderColor = '#00509d';
                    this.style.boxShadow = '0 3px 10px rgba(0,80,157,0.2)';
                });
                guiaCard.addEventListener('mouseleave', function() {
                    this.style.borderColor = '#e0e0e0';
                    this.style.boxShadow = 'none';
                });

                container.appendChild(guiaCard);
            });
        }

        // Actualizar campos cuando se seleccionan/deseleccionan guías
        function actualizarSeleccionGuias() {
            const checkboxes = document.querySelectorAll('input[name="guias_seleccionadas[]"]:checked');
            guiasSeleccionadas = [];

            let pesoRealTotal = 0; // Peso real para mostrar
            let pesoTotalAjustado = 0; // Peso ajustado para cálculo
            let paquetesTotal = 0;
            let contadorGuias = 0;

            checkboxes.forEach(checkbox => {
                guiasSeleccionadas.push(checkbox.value);

                // Obtener peso individual de la guía
                let pesoIndividual = parseFloat(checkbox.dataset.peso) || 0;

                // Sumar el peso real
                pesoRealTotal += pesoIndividual;

                // Aplicar regla: si pesa menos de 1kg, se asume como 1kg
                let pesoAjustado = pesoIndividual < 1 ? 1.0 : pesoIndividual;

                // Sumar el peso ajustado
                pesoTotalAjustado += pesoAjustado;

                paquetesTotal += parseInt(checkbox.dataset.pcs) || 0;
                contadorGuias++;
            });

            // Guardar peso ajustado en variable global para calcularTotales()
            pesoAjustadoGuias = pesoTotalAjustado;

            // Actualizar campos - mostrar peso REAL
            document.getElementById('peso_total').value = pesoRealTotal.toFixed(3);
            document.getElementById('total_paquetes').value = paquetesTotal;
            document.getElementById('total_guias').value = contadorGuias;

            // Guardar peso ajustado en campo hidden para enviarlo al servidor
            document.getElementById('peso_ajustado_calculado').value = pesoTotalAjustado.toFixed(3);

            // Recalcular totales
            calcularTotales();
        }

        // ========================================
        // FUNCIONES PARA PEDIDOS PENDIENTES
        // ========================================

        // Cargar pedidos pendientes desde la API
        function cargarPedidosPendientes(clienteId) {
            document.getElementById('pedidos_loading').style.display = 'block';
            document.getElementById('pedidos_vacio').style.display = 'none';
            document.getElementById('pedidos_container').innerHTML = '';
            document.getElementById('seccion_pedidos_pendientes').style.display = 'block';

            fetch(`api_pedidos_pendientes.php?cliente_id=${clienteId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('pedidos_loading').style.display = 'none';

                    if (data.success && data.pedidos.length > 0) {
                        pedidosDisponibles = data.pedidos;
                        tienePedidosPendientes = true;
                        renderizarPedidos(data.pedidos);
                    } else {
                        pedidosDisponibles = [];
                        tienePedidosPendientes = false;
                        document.getElementById('pedidos_vacio').style.display = 'block';
                        // Reset pendiente de pago
                        pendientePago = 0;
                        document.getElementById('total_pedidos_seleccionados').textContent = '$0.00';
                        calcularTotales();
                    }
                })
                .catch(error => {
                    document.getElementById('pedidos_loading').style.display = 'none';
                    console.error('Error al cargar pedidos:', error);
                    alert('Error al cargar los pedidos pendientes. Por favor intenta nuevamente.');
                    tienePedidosPendientes = false;
                });
        }

        // Renderizar lista de pedidos con checkboxes
        function renderizarPedidos(pedidos) {
            const container = document.getElementById('pedidos_container');
            container.innerHTML = '';

            pedidos.forEach(pedido => {
                const pedidoCard = document.createElement('div');
                pedidoCard.style.cssText = 'border: 2px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 10px; background: white; transition: all 0.3s;';

                const fecha = new Date(pedido.subido_en);
                const fechaFormateada = fecha.toLocaleDateString('es-PE', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });

                pedidoCard.innerHTML = `
                    <label style="display: flex; gap: 15px; cursor: pointer; align-items: start;">
                        <input type="checkbox" name="pedidos_seleccionados[]" value="${pedido.id}"
                               data-monto="${pedido.monto_pendiente}"
                               onchange="actualizarPendientePago()"
                               style="width: 20px; height: 20px; margin-top: 5px;">
                        <div style="flex: 1;">
                            <div style="font-weight: 700; font-size: 1rem; color: #00296b; margin-bottom: 5px;">
                                💰 Pedido #${pedido.id}
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; font-size: 0.9rem; color: #555;">
                                <div><strong>Monto:</strong> $${parseFloat(pedido.monto_pendiente).toFixed(2)}</div>
                                <div><strong>Fecha:</strong> ${fechaFormateada}</div>
                                <div><strong>Trackings:</strong> ${pedido.total_trackings}</div>
                            </div>
                        </div>
                    </label>
                `;

                // Hover effect
                pedidoCard.addEventListener('mouseenter', function() {
                    this.style.borderColor = '#00509d';
                    this.style.boxShadow = '0 3px 10px rgba(0,80,157,0.2)';
                });
                pedidoCard.addEventListener('mouseleave', function() {
                    this.style.borderColor = '#e0e0e0';
                    this.style.boxShadow = 'none';
                });

                container.appendChild(pedidoCard);
            });
        }

        // Actualizar pendiente de pago cuando se seleccionan/deseleccionan pedidos
        function actualizarPendientePago() {
            const checkboxes = document.querySelectorAll('input[name="pedidos_seleccionados[]"]:checked');
            pedidosSeleccionados = [];
            let total = 0;

            checkboxes.forEach(checkbox => {
                pedidosSeleccionados.push(checkbox.value);
                total += parseFloat(checkbox.dataset.monto);
            });

            pendientePago = total;
            document.getElementById('total_pedidos_seleccionados').textContent = '$' + total.toFixed(2);
            calcularTotales();
        }

        // Event listeners
        document.getElementById('modo_manual').addEventListener('change', cambiarModo);
        document.getElementById('modo_desde_guia').addEventListener('change', cambiarModo);

        document.getElementById('tipo_documento').addEventListener('change', calcularTotales);
        document.getElementById('peso_total').addEventListener('input', calcularTotales);
        document.getElementById('total_guias').addEventListener('input', calcularTotales);
        document.getElementById('cantidad_cambio_consignatario').addEventListener('input', calcularTotales);
        document.getElementById('cantidad_reempaque').addEventListener('input', calcularTotales);
        document.getElementById('envio_provincia_check').addEventListener('change', calcularTotales);
        document.getElementById('gastos_adicionales').addEventListener('input', calcularTotales);
        document.getElementById('descuento').addEventListener('input', calcularTotales);

        // Event listeners para cambio de tarifa
        document.getElementById('tarifa_1').addEventListener('change', calcularTotales);
        document.getElementById('tarifa_2').addEventListener('change', calcularTotales);
        document.getElementById('tarifa_3').addEventListener('change', calcularTotales);

        // Cuando se selecciona un cliente, cargar pedidos pendientes y/o guías
        document.getElementById('cliente_id').addEventListener('change', function() {
            const clienteId = this.value;

            if (clienteId) {
                // Cargar pedidos pendientes SIEMPRE
                cargarPedidosPendientes(clienteId);

                // Si está en modo DESDE_GUIA, cargar las guías del cliente
                if (modoActual === 'DESDE_GUIA') {
                    cargarGuias(clienteId);
                }
            } else {
                // Si no hay cliente seleccionado, ocultar sección de pedidos
                document.getElementById('seccion_pedidos_pendientes').style.display = 'none';
                pendientePago = 0;
                pedidosSeleccionados = [];
                tienePedidosPendientes = false;
                calcularTotales();
            }
        });

        // Validación antes de enviar
        document.getElementById('formFacturacion').addEventListener('submit', function(e) {
            const tipoDoc = document.getElementById('tipo_documento').value;
            const clienteId = document.getElementById('cliente_id').value;
            const peso = parseFloat(document.getElementById('peso_total').value);

            if (!tipoDoc) {
                e.preventDefault();
                alert('Debe seleccionar un tipo de documento');
                return false;
            }

            if (!clienteId) {
                e.preventDefault();
                alert('Debe seleccionar un cliente');
                return false;
            }

            if (!peso || peso <= 0) {
                e.preventDefault();
                alert('Debe ingresar un peso total válido');
                return false;
            }

            // VALIDACIÓN OBLIGATORIA: Si el cliente tiene pedidos pendientes, debe seleccionar al menos uno
            if (tienePedidosPendientes && pedidosSeleccionados.length === 0) {
                e.preventDefault();
                alert('⚠️ Este cliente tiene pedidos pendientes de pago.\n\nDebe seleccionar al menos un pedido pendiente para incluir en esta factura.');
                // Scroll a la sección de pedidos pendientes
                document.getElementById('seccion_pedidos_pendientes').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        });
    </script>
</body>
</html>

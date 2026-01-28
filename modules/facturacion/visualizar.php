<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("ID inválido");
}

$database = new Database();
$conn = $database->getConnection();

// Obtener datos del documento
$stmt = $conn->prepare("SELECT df.*, c.nombre_razon_social, c.apellido, c.tipo_documento as cliente_tipo_doc,
                              c.documento, c.direccion, c.distrito, c.provincia, c.departamento,
                              c.email, c.celular
                       FROM documentos_facturacion df
                       INNER JOIN clientes c ON df.cliente_id = c.id
                       WHERE df.id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$doc = $stmt->fetch();

if (!$doc) {
    die("Documento no encontrado");
}

// Si es modo DESDE_GUIA, obtener las guías asociadas
$guias = [];
if ($doc['modo_creacion'] === 'DESDE_GUIA' && !empty($doc['guias_asociadas'])) {
    $guias_ids = explode(',', $doc['guias_asociadas']);
    $placeholders = implode(',', array_fill(0, count($guias_ids), '?'));

    $stmt_guias = $conn->prepare("
        SELECT consignatario, pcs, peso_kg
        FROM guias_masivas
        WHERE id IN ($placeholders)
        ORDER BY consignatario ASC
    ");
    $stmt_guias->execute($guias_ids);
    $guias = $stmt_guias->fetchAll();
}

// Determinar tarifas aplicadas
$tarifa_aplicada = $doc['tarifa_aplicada'] ?? 'TARIFA_1';
$tarifa_peso = 10.00;
$tarifa_desaduanaje = 5.00;
$nombre_tarifa = 'Tarifa 1';

switch ($tarifa_aplicada) {
    case 'TARIFA_1':
        $tarifa_peso = 10.00;
        $tarifa_desaduanaje = 5.00;
        $nombre_tarifa = 'Tarifa 1';
        break;
    case 'TARIFA_2':
        $tarifa_peso = 9.50;
        $tarifa_desaduanaje = 5.00;
        $nombre_tarifa = 'Tarifa 2';
        break;
    case 'TARIFA_3':
        $tarifa_peso = 9.90;
        $tarifa_desaduanaje = 0.00;
        $nombre_tarifa = 'Tarifa 3 (Flat)';
        break;
}

$tipo_usuario = obtenerTipoUsuario();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $doc['numero_documento']; ?></title>
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

        .btn-container {
            max-width: 900px;
            margin: 0 auto 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
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
            background: linear-gradient(135deg, #00296b 0%, #00509d 100%);
            color: white;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-back {
            background: #95a5a6;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .factura-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .factura-header {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #00296b;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-section img {
            max-width: 250px;
            width: 100%;
            height: auto;
        }

        .factura-info {
            border: 2px solid #00296b;
            padding: 15px;
            text-align: center;
        }

        .factura-info h1 {
            color: #00296b;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .factura-info .numero {
            font-size: 1.2rem;
            font-weight: bold;
            color: #00509d;
            margin-bottom: 10px;
        }

        .factura-info .ruc {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .factura-info .fecha {
            font-size: 0.9rem;
            color: #666;
        }

        .empresa-section {
            background: #00296b;
            color: white;
            padding: 8px 15px;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .empresa-datos {
            background: #f8f9fa;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            line-height: 1.6;
        }

        .cliente-section {
            background: #00296b;
            color: white;
            padding: 8px 15px;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .cliente-datos {
            background: #f8f9fa;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }

        .cliente-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .dato-item {
            margin: 3px 0;
        }

        .dato-label {
            font-weight: bold;
            color: #00296b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        thead {
            background: #00296b;
            color: white;
        }

        th {
            padding: 12px 8px;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 600;
        }

        th.center {
            text-align: center;
        }

        th.right {
            text-align: right;
        }

        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 0.85rem;
        }

        td.center {
            text-align: center;
        }

        td.right {
            text-align: right;
        }

        .observaciones {
            margin: 20px 0;
        }

        .observaciones-title {
            background: #00296b;
            color: white;
            padding: 8px 15px;
            font-weight: bold;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .observaciones-content {
            background: #f8f9fa;
            padding: 10px 15px;
            min-height: 60px;
            font-size: 0.85rem;
        }

        .totales-section {
            float: right;
            width: 300px;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 15px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 0.9rem;
        }

        .total-row.subtotal {
            background: #f8f9fa;
        }

        .total-row.final {
            background: #00296b;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
            border: none;
        }

        .footer-text {
            clear: both;
            margin-top: 80px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            font-size: 0.75rem;
            color: #666;
            line-height: 1.8;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .btn-container {
                display: none;
            }

            .factura-container {
                box-shadow: none;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="btn-container">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir</button>
        <a href="descargar.php?id=<?php echo $id; ?>" class="btn btn-success">⬇️ Descargar PDF</a>
        <a href="index.php" class="btn btn-back">← Volver al Listado</a>
    </div>

    <div class="factura-container">
        <!-- HEADER -->
        <div class="factura-header">
            <div class="logo-section">
                <img src="../../assets/logo/logo_fact.png" alt="Logo">
            </div>
            <div class="factura-info">
                <h1><?php echo $doc['tipo_documento']; ?></h1>
                <div class="numero"><?php echo $doc['numero_documento']; ?></div>
                <div class="ruc">RUC: 20611227982</div>
                <div class="fecha">Fecha: <?php echo formatearFecha($doc['creado_en'], 'd/m/Y'); ?></div>
            </div>
        </div>

        <!-- DATOS DE LA EMPRESA -->
        <div class="empresa-section">DATOS DEL EMISOR</div>
        <div class="empresa-datos">
            <strong>INTERNATIONAL COURIER SERVICE S.A.C.</strong><br>
            Dirección: MZA. A LOTE. 10 URB. LOS PRODUCTORES LIMA - LIMA - SANTA ANITA<br>
            Cel: (+51) 902 937 040 | Email: info@ifast.com.pe
        </div>

        <!-- DATOS DEL CLIENTE -->
        <div class="cliente-section">DATOS DEL CLIENTE</div>
        <div class="cliente-datos">
            <div class="cliente-grid">
                <div class="dato-item">
                    <span class="dato-label">Nombre/Razón Social:</span><br>
                    <?php echo $doc['nombre_razon_social']; ?>
                    <?php if ($doc['apellido']): ?>
                        <?php echo ' ' . $doc['apellido']; ?>
                    <?php endif; ?>
                </div>
                <div class="dato-item">
                    <span class="dato-label"><?php echo $doc['cliente_tipo_doc']; ?>:</span><br>
                    <?php echo $doc['documento']; ?>
                </div>
                <?php if ($doc['direccion']): ?>
                <div class="dato-item" style="grid-column: 1 / -1;">
                    <span class="dato-label">Dirección:</span><br>
                    <?php echo $doc['direccion']; ?>
                    <?php if ($doc['distrito']): ?>
                        , <?php echo $doc['distrito']; ?>
                    <?php endif; ?>
                    <?php if ($doc['provincia']): ?>
                        , <?php echo $doc['provincia']; ?>
                    <?php endif; ?>
                    <?php if ($doc['departamento']): ?>
                        , <?php echo $doc['departamento']; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($doc['email'] || $doc['celular']): ?>
                <div class="dato-item">
                    <?php if ($doc['email']): ?>
                    <span class="dato-label">Email:</span> <?php echo $doc['email']; ?><br>
                    <?php endif; ?>
                    <?php if ($doc['celular']): ?>
                    <span class="dato-label">Teléfono:</span> <?php echo $doc['celular']; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($guias)): ?>
        <!-- DESGLOSE POR GUÍA -->
        <div style="margin-bottom: 20px;">
            <div class="empresa-section">DESGLOSE POR GUÍA</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 40%;">Consignatario</th>
                        <th class="center" style="width: 15%;"># Paquetes</th>
                        <th class="center" style="width: 15%;">Peso (kg)</th>
                        <th class="center" style="width: 15%;">Peso a Cobrar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guias as $guia): ?>
                    <?php
                        $peso_real = floatval($guia['peso_kg']);
                        $peso_a_cobrar = $peso_real < 1 ? 1.0 : $peso_real;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($guia['consignatario']); ?></td>
                        <td class="center"><?php echo intval($guia['pcs']); ?></td>
                        <td class="center"><?php echo number_format($peso_real, 2); ?></td>
                        <td class="center"><?php echo number_format($peso_a_cobrar, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- TABLA DE SERVICIOS -->
        <table>
            <thead>
                <tr>
                    <th class="center" style="width: 60px;">CANT.</th>
                    <th>DESCRIPCIÓN</th>
                    <th class="right" style="width: 100px;">P.UNIT</th>
                    <th class="right" style="width: 100px;">IMPORTE</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($doc['peso_total'] > 0): ?>
                <tr>
                    <td class="center"><?php echo number_format($doc['peso_total'], 3); ?> kg</td>
                    <td>Servicio de Envío por Peso (<?php echo $nombre_tarifa; ?>)</td>
                    <td class="right">$<?php echo number_format($tarifa_peso, 2); ?></td>
                    <td class="right">$<?php echo number_format($doc['costo_peso'], 2); ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($doc['total_paquetes'] > 0): ?>
                <tr>
                    <td class="center"><?php echo $doc['total_paquetes']; ?></td>
                    <td>Total de Paquetes (Informativo)</td>
                    <td class="right">-</td>
                    <td class="right">-</td>
                </tr>
                <?php endif; ?>

                <?php if ($doc['total_guias'] > 0): ?>
                <tr>
                    <td class="center"><?php echo $doc['total_guias']; ?></td>
                    <td>Servicio de Desaduanaje</td>
                    <td class="right">$<?php echo number_format($tarifa_desaduanaje, 2); ?></td>
                    <td class="right">$<?php echo number_format($doc['costo_desaduanaje'], 2); ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($doc['cantidad_cambio_consignatario'] > 0): ?>
                <tr>
                    <td class="center"><?php echo $doc['cantidad_cambio_consignatario']; ?></td>
                    <td>Cambio de Consignatario</td>
                    <td class="right">$3.00</td>
                    <td class="right">$<?php echo number_format($doc['costo_cambio_consignatario'], 2); ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($doc['cantidad_reempaque'] > 0): ?>
                <tr>
                    <td class="center"><?php echo $doc['cantidad_reempaque']; ?></td>
                    <td>Servicio de Reempaque</td>
                    <td class="right">$5.00</td>
                    <td class="right">$<?php echo number_format($doc['costo_reempaque'], 2); ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($doc['envio_provincia'] === 'SI'): ?>
                <tr>
                    <td class="center">1</td>
                    <td>Envío a Provincia</td>
                    <td class="right">$3.00</td>
                    <td class="right">$<?php echo number_format($doc['costo_envio_provincia'], 2); ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($doc['pendiente_pago'] > 0): ?>
                <tr>
                    <td class="center">-</td>
                    <td>Pendiente de Pago</td>
                    <td class="right">-</td>
                    <td class="right">$<?php echo number_format($doc['pendiente_pago'], 2); ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($doc['gastos_adicionales'] > 0): ?>
                <tr>
                    <td class="center">-</td>
                    <td>
                        Gastos Adicionales
                        <?php if ($doc['detalle_gastos_adicionales']): ?>
                        <br><small style="color: #666;">(<?php echo htmlspecialchars($doc['detalle_gastos_adicionales']); ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td class="right">-</td>
                    <td class="right">$<?php echo number_format($doc['gastos_adicionales'], 2); ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($doc['descuento'] > 0): ?>
                <tr style="background: #ffe6e6;">
                    <td class="center">-</td>
                    <td>
                        <strong style="color: #c0392b;">Descuento</strong>
                        <?php if ($doc['detalle_descuento']): ?>
                        <br><small style="color: #666;">(<?php echo htmlspecialchars($doc['detalle_descuento']); ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td class="right">-</td>
                    <td class="right" style="color: #c0392b;"><strong>-$<?php echo number_format($doc['descuento'], 2); ?></strong></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- OBSERVACIONES -->
        <div class="observaciones">
            <div class="observaciones-title">OBSERVACIONES</div>
            <div class="observaciones-content">
                <strong>Tarifa Aplicada:</strong> <?php echo $nombre_tarifa; ?>
                ($<?php echo number_format($tarifa_peso, 2); ?>/kg<?php if ($tarifa_desaduanaje > 0): ?> + $<?php echo number_format($tarifa_desaduanaje, 2); ?> Desaduanaje<?php else: ?> - Sin cargo por Desaduanaje<?php endif; ?>)
                <br>
                <?php if ($doc['canal_aduanas']): ?>
                <strong>Canal de Aduanas:</strong>
                <?php
                if ($doc['canal_aduanas'] === 'VERDE') echo '🟢 CANAL VERDE';
                elseif ($doc['canal_aduanas'] === 'NARANJA') echo '🟠 CANAL NARANJA';
                elseif ($doc['canal_aduanas'] === 'ROJO') echo '🔴 CANAL ROJO';
                ?>
                <br>
                <?php endif; ?>
                Documento generado electrónicamente por el Sistema de Gestión.
            </div>
        </div>

        <!-- IMAGEN ADJUNTA -->
        <?php if (!empty($doc['imagen_adjunta']) && file_exists($doc['imagen_adjunta'])): ?>
        <div class="observaciones" style="margin-top: 20px;">
            <div class="observaciones-title">📷 IMAGEN ADJUNTA</div>
            <div class="observaciones-content" style="text-align: center; padding: 20px;">
                <img src="<?php echo $doc['imagen_adjunta']; ?>" alt="Imagen adjunta al documento"
                     style="max-width: 100%; max-height: 500px; border: 2px solid #ddd; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            </div>
        </div>
        <?php endif; ?>

        <!-- TOTALES -->
        <div class="totales-section">
            <?php if ($doc['tipo_documento'] === 'FACTURA' || $doc['tipo_documento'] === 'BOLETA'): ?>
            <div class="total-row subtotal">
                <span>SUBTOTAL:</span>
                <span>$<?php echo number_format($doc['subtotal'], 2); ?></span>
            </div>
            <div class="total-row">
                <span>IGV (18%):</span>
                <span>$<?php echo number_format($doc['igv'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row final">
                <span>TOTAL:</span>
                <span>$<?php echo number_format($doc['total'], 2); ?></span>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer-text">
            SON: <?php
            // Convertir número a texto - (simplificado) <br>"Autorizado mediante Resolución de Superintendencia N° XXX-XXXX-SUNAT"
            $total_entero = floor($doc['total']);
            $total_decimales = round(($doc['total'] - $total_entero) * 100);
            echo strtoupper("DÓLARES AMERICANOS CON $total_decimales/100");
            ?><br>
            Documento generado electrónicamente - Sistema de Gestión <?php echo date('Y'); ?>
        </div>
    </div>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</body>
</html>


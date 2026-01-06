<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();
$tipo_usuario = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

$id_guia = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_guia <= 0) {
    header("Location: index.php?error=ID inválido");
    exit();
}

// Obtener datos del embarque
$stmt = $conn->prepare("SELECT * FROM guias_embarque WHERE id_guia = :id");
$stmt->bindParam(':id', $id_guia);
$stmt->execute();
$guia = $stmt->fetch();

if (!$guia) {
    header("Location: index.php?error=Embarque no encontrado");
    exit();
}

// Obtener trackings asociados
$stmt_trackings = $conn->prepare("
    SELECT
        pt.tracking_code,
        COALESCE(rp.nombre_original, rp.nombre_archivo) as nombre_archivo,
        rp.ruta,
        rp.pendiente_pago,
        rp.monto_pendiente,
        DATE_FORMAT(pt.fecha_creacion, '%d/%m/%Y') as fecha
    FROM guia_pedidos gp
    INNER JOIN pedidos_trackings pt ON gp.tracking_id = pt.id
    INNER JOIN recibos_pedidos rp ON pt.recibo_pedido_id = rp.id
    WHERE gp.id_guia = :id_guia
    ORDER BY pt.fecha_creacion DESC
");
$stmt_trackings->bindParam(':id_guia', $id_guia);
$stmt_trackings->execute();
$trackings = $stmt_trackings->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía de Embarque - <?php echo htmlspecialchars($guia['nro_guia']); ?></title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 260px; }
        .header { background: white; padding: 20px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header h1 { font-size: 1.8rem; color: #2c3e50; font-weight: 600; }
        .content { padding: 30px; }
        .card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); max-width: 900px; margin: 0 auto; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 3px solid #ecf0f1; }
        .card-title { font-size: 1.6rem; color: #00296b; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .badge { display: inline-block; padding: 6px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .badge-activo { background: #d4edda; color: #155724; }
        .badge-inactivo { background: #f8d7da; color: #721c24; }
        .badge-pendiente { background: #fff3cd; color: #856404; }
        .badge-pagado { background: #d4edda; color: #155724; }

        .section { margin: 30px 0; }
        .section-title { font-size: 1.1rem; color: #2c3e50; font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .info-item { }
        .info-label { font-size: 0.75rem; color: #7f8c8d; text-transform: uppercase; margin-bottom: 5px; font-weight: 600; }
        .info-value { font-size: 1rem; color: #2c3e50; padding: 10px 15px; background: #f8f9fa; border-left: 4px solid #00509d; border-radius: 5px; }
        .info-value.highlight { background: #d4edda; color: #155724; font-weight: 600; font-size: 1.1rem; }

        .trackings-list { }
        .tracking-item { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #00509d; }
        .tracking-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .tracking-code { font-size: 1.1rem; font-weight: 600; color: #00296b; }
        .tracking-details { display: flex; gap: 20px; font-size: 0.85rem; color: #7f8c8d; }
        .tracking-detail { display: flex; align-items: center; gap: 5px; }

        .btn-group { display: flex; gap: 15px; justify-content: flex-start; margin-top: 30px; padding-top: 20px; border-top: 2px solid #ecf0f1; }
        .btn { padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.95rem; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3); }
        .btn-primary { background: linear-gradient(135deg, #00296b 0%, #00509d 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 80, 157, 0.3); }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>📦 Guía de Embarque</h1>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class='bx bx-file'></i> Guía de Embarque
                        </h2>
                        <span class="badge badge-<?php echo strtolower($guia['estado']); ?>">
                            <?php echo htmlspecialchars($guia['estado']); ?>
                        </span>
                    </div>

                    <!-- INFORMACIÓN GENERAL -->
                    <div class="section">
                        <div class="section-title">
                            <i class='bx bx-info-circle'></i> Información General
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">N° DE GUÍA</div>
                                <div class="info-value"><?php echo htmlspecialchars($guia['nro_guia']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">FECHA DE CREACIÓN</div>
                                <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($guia['fecha_creacion'])); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- DATOS DEL CLIENTE -->
                    <div class="section">
                        <div class="section-title">
                            <i class='bx bx-user'></i> Datos del Cliente
                        </div>
                        <div class="info-grid">
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <div class="info-label">NOMBRE Y APELLIDO</div>
                                <div class="info-value"><?php echo htmlspecialchars($guia['nombre_completo']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">TIPO DOCUMENTO</div>
                                <div class="info-value"><?php echo htmlspecialchars($guia['tipo_documento']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">DNI/RUC</div>
                                <div class="info-value"><?php echo htmlspecialchars($guia['documento']); ?></div>
                            </div>
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <div class="info-label">CONSIGNATARIO</div>
                                <div class="info-value"><?php echo htmlspecialchars($guia['consignatario']); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- TRACKINGS ASOCIADOS -->
                    <div class="section">
                        <div class="section-title">
                            <i class='bx bx-package'></i> Trackings Asociados (<?php echo count($trackings); ?>)
                        </div>
                        <div class="trackings-list">
                            <?php foreach ($trackings as $tracking): ?>
                            <div class="tracking-item">
                                <div class="tracking-header">
                                    <div class="tracking-code"><?php echo htmlspecialchars($tracking['tracking_code']); ?></div>
                                    <?php if ($tracking['pendiente_pago'] === 'SI'): ?>
                                        <span class="badge badge-pendiente">Pendiente: $<?php echo number_format($tracking['monto_pendiente'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-pagado">Pagado</span>
                                    <?php endif; ?>
                                </div>
                                <div class="tracking-details">
                                    <?php if (!empty($tracking['nombre_archivo'])): ?>
                                    <span class="tracking-detail">
                                        <i class='bx bx-file'></i> Archivo: <?php echo htmlspecialchars($tracking['nombre_archivo']); ?>
                                    </span>
                                    <?php endif; ?>
                                    <span class="tracking-detail">
                                        <i class='bx bx-calendar'></i> Fecha: <?php echo htmlspecialchars($tracking['fecha']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- DETALLES DEL EMBARQUE -->
                    <div class="section">
                        <div class="section-title">
                            <i class='bx bx-clipboard'></i> Detalles del Embarque
                        </div>
                        <div class="info-grid">
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <div class="info-label">PROVEEDOR(ES)</div>
                                <div class="info-value"><?php echo !empty($guia['proveedor']) ? htmlspecialchars($guia['proveedor']) : '—'; ?></div>
                            </div>
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <div class="info-label">CONTENIDO</div>
                                <div class="info-value"><?php echo !empty($guia['contenido']) ? htmlspecialchars($guia['contenido']) : '—'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">VALOR USD</div>
                                <div class="info-value highlight">$<?php echo number_format($guia['valor_usd'], 2); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">ÚLTIMA ACTUALIZACIÓN</div>
                                <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($guia['fecha_creacion'])); ?></div>
                            </div>
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <div class="info-label">INDICACIONES</div>
                                <div class="info-value"><?php echo !empty($guia['indicaciones']) ? htmlspecialchars($guia['indicaciones']) : '—'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- BOTONES -->
                    <div class="btn-group">
                        <a href="exportar_excel.php?id=<?php echo $guia['id_guia']; ?>" class="btn btn-success">
                            <i class='bx bx-download'></i> Descargar Excel
                        </a>
                        <a href="editar.php?id=<?php echo $guia['id_guia']; ?>" class="btn btn-primary">
                            <i class='bx bx-edit'></i> Editar
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class='bx bx-arrow-back'></i> Volver al Listado
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

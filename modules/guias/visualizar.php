<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();
$tipo_usuario = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

// Obtener ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    header("Location: index.php");
    exit();
}

// Obtener guía
$stmt = $conn->prepare("
    SELECT gm.*, CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario
    FROM guias_masivas gm
    LEFT JOIN usuarios u ON gm.creado_por = u.id
    WHERE gm.id = :id
");
$stmt->bindParam(':id', $id);
$stmt->execute();
$guia = $stmt->fetch();

if (!$guia) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Guía - <?php echo htmlspecialchars($guia['nro_guia']); ?></title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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

        /* HEADER */
        .header {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FDC500 0%, #FFD500 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .user-details {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: #7f8c8d;
            display: inline-block;
            padding: 2px 8px;
            background: #ecf0f1;
            border-radius: 10px;
            margin-top: 2px;
        }

        .btn-logout {
            padding: 10px 20px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-logout:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .content {
            padding: 30px;
            max-width: 900px;
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
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f5f7fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.5rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
        }

        .detail-value {
            color: #2c3e50;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .content {
                padding: 15px;
            }

            .detail-row {
                grid-template-columns: 1fr;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="header">
            <div class="header-left">
                <h1>Ver Guía</h1>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo substr($nombre_usuario, 0, 1); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo $nombre_usuario; ?></div>
                        <span class="user-role"><?php echo $tipo_usuario; ?></span>
                    </div>
                </div>
                <a href="../../logout.php" class="btn-logout">
                    <box-icon name='log-out' color='white' size='20px'></box-icon>
                </a>
            </div>
        </header>

        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class='bx bx-file'></i>
                        Detalles de Guía
                    </h2>
                </div>

                <div class="detail-row">
                    <div class="detail-label">N° Guía:</div>
                    <div class="detail-value"><strong style="font-size: 1.2rem;"><?php echo htmlspecialchars($guia['nro_guia']); ?></strong></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Consignatario:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($guia['consignatario']); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Cliente:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($guia['cliente'] ?? '-'); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Descripción:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($guia['descripcion']) ?: '-'; ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Piezas (PCS):</div>
                    <div class="detail-value"><?php echo $guia['pcs']; ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Peso (kg):</div>
                    <div class="detail-value"><?php echo number_format($guia['peso_kg'], 2); ?> kg</div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Valor FOB (USD):</div>
                    <div class="detail-value">$<?php echo number_format($guia['valor_fob_usd'], 2); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Fecha de Embarque:</div>
                    <div class="detail-value"><?php echo $guia['fecha_embarque'] ? formatearFecha($guia['fecha_embarque'], 'd/m/Y') : '-'; ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Asesor:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($guia['asesor'] ?? '-'); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Estado:</div>
                    <div class="detail-value">
                        <span class="badge <?php
                            if ($guia['estado'] === 'ENTREGADO') echo 'badge-success';
                            elseif ($guia['estado'] === 'PENDIENTE') echo 'badge-warning';
                            elseif ($guia['estado'] === 'OBSERVADO') echo 'badge-danger';
                            elseif ($guia['estado'] === 'LIQUIDADO') echo 'badge-info';
                        ?>">
                            <?php echo $guia['estado']; ?>
                        </span>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Método de Ingreso:</div>
                    <div class="detail-value">
                        <span class="badge <?php echo $guia['metodo_ingreso'] === 'EXCEL' ? 'badge-info' : 'badge-warning'; ?>">
                            <?php echo $guia['metodo_ingreso']; ?>
                        </span>
                    </div>
                </div>

                <?php if ($guia['nombre_archivo_origen']): ?>
                <div class="detail-row">
                    <div class="detail-label">Archivo Origen:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($guia['nombre_archivo_origen']); ?></div>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <div class="detail-label">Creado por:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($guia['nombre_usuario'] ?: 'Usuario no disponible'); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Fecha de Creación:</div>
                    <div class="detail-value"><?php echo formatearFecha($guia['fecha_creacion'], 'd/m/Y H:i'); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Última Actualización:</div>
                    <div class="detail-value"><?php echo formatearFecha($guia['fecha_actualizacion'], 'd/m/Y H:i'); ?></div>
                </div>

                <div style="margin-top: 30px;">
                    <a href="index.php" class="btn btn-secondary">
                        <i class='bx bx-arrow-back'></i>
                        Volver al Listado
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</body>
</html>

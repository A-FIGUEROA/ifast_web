<?php
/**
 * DETALLE DE HORARIO DE UN USUARIO
 * Muestra el historial completo de cambios de estado de un usuario en un día
 */

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/control_horario_functions.php';

// Solo ADMIN puede acceder
requierePermiso(['ADMIN']);

// Obtener parámetros
$usuario_id = $_GET['usuario_id'] ?? 0;
$fecha = $_GET['fecha'] ?? date('Y-m-d');

// Obtener conexión
$database = new Database();
$conn = $database->getConnection();

// Obtener información del usuario
$stmt = $conn->prepare("SELECT nombre, apellido, email, tipo FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: index.php?error=usuario_no_encontrado');
    exit;
}

// Obtener historial del día
$historial = obtenerHistorialDia($conn, $usuario_id, $fecha);

// Obtener resumen del día
$stmt = $conn->prepare("SELECT * FROM sesiones_trabajo WHERE usuario_id = ? AND fecha = ?");
$stmt->execute([$usuario_id, $fecha]);
$sesion = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Horario - <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></title>
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
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            transition: all 0.3s;
        }

        .content {
            padding: 30px;
        }

        .header-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .header-section h2 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-section p {
            color: #7f8c8d;
            margin-bottom: 15px;
        }

        .btn-back {
            padding: 10px 20px;
            background: #95a5a6;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .summary-card .label {
            font-size: 0.85rem;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .summary-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .timeline-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .timeline-card h3 {
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .timeline {
            position: relative;
            padding-left: 40px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 30px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -29px;
            top: 5px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px;
        }

        .timeline-dot.conectado {
            background: #27ae60;
            box-shadow: 0 0 0 2px #27ae60;
        }

        .timeline-dot.refrigerio {
            background: #f39c12;
            box-shadow: 0 0 0 2px #f39c12;
        }

        .timeline-dot.desconectado {
            background: #e74c3c;
            box-shadow: 0 0 0 2px #e74c3c;
        }

        .timeline-content {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid;
        }

        .timeline-content.conectado {
            border-left-color: #27ae60;
        }

        .timeline-content.refrigerio {
            border-left-color: #f39c12;
        }

        .timeline-content.desconectado {
            border-left-color: #e74c3c;
        }

        .timeline-time {
            font-weight: 700;
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .timeline-status {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .timeline-status.conectado { color: #27ae60; }
        .timeline-status.refrigerio { color: #f39c12; }
        .timeline-status.desconectado { color: #e74c3c; }

        .timeline-note {
            margin-top: 8px;
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }

        .empty-state i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .content {
                padding: 15px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <header>
            <?php require_once '../../includes/header.php'; ?>
        </header>

        <div class="content">
            <!-- Encabezado -->
            <div class="header-section">
                <h2>
                    <i class='bx bx-user-circle'></i>
                    <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?>
                </h2>
                <p>
                    <?php echo htmlspecialchars($usuario['email']); ?> •
                    <?php echo htmlspecialchars($usuario['tipo']); ?> •
                    <?php echo date('d/m/Y', strtotime($fecha)); ?>
                </p>
                <a href="index.php?fecha=<?php echo $fecha; ?>" class="btn-back">
                    <i class='bx bx-arrow-back'></i>
                    Volver al Dashboard
                </a>
            </div>

            <!-- Resumen del día -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="label">⏰ Hora Inicio</div>
                    <div class="value">
                        <?php
                        if ($sesion && $sesion['hora_inicio']) {
                            echo date('H:i', strtotime($sesion['hora_inicio']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="label">🏁 Hora Fin</div>
                    <div class="value">
                        <?php
                        if ($sesion && $sesion['hora_fin']) {
                            echo date('H:i', strtotime($sesion['hora_fin']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="label">💼 Tiempo Trabajado</div>
                    <div class="value">
                        <?php echo $sesion ? formatearTiempo($sesion['tiempo_trabajado']) : '0h 0m'; ?>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="label">☕ Tiempo Refrigerio</div>
                    <div class="value">
                        <?php echo $sesion ? formatearTiempo($sesion['tiempo_refrigerio']) : '0h 0m'; ?>
                    </div>
                </div>
            </div>

            <!-- Línea de tiempo -->
            <div class="timeline-card">
                <h3>
                    <i class='bx bx-time'></i>
                    Línea de Tiempo
                </h3>

                <?php if (count($historial) > 0): ?>
                <div class="timeline">
                    <?php foreach ($historial as $registro): ?>
                    <?php
                    $estado_class = strtolower($registro['estado']);
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?php echo $estado_class; ?>"></div>
                        <div class="timeline-content <?php echo $estado_class; ?>">
                            <div class="timeline-time">
                                <?php echo date('H:i:s', strtotime($registro['fecha_hora'])); ?>
                            </div>
                            <div class="timeline-status <?php echo $estado_class; ?>">
                                <?php echo htmlspecialchars($registro['estado']); ?>
                            </div>
                            <?php if ($registro['notas']): ?>
                            <div class="timeline-note">
                                💬 <?php echo htmlspecialchars($registro['notas']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-time'></i>
                    <p>No hay registros para este día</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>

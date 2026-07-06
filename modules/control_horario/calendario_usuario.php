<?php
/**
 * CALENDARIO MENSUAL DE ASISTENCIA DE UN USUARIO
 * Muestra los días trabajados del mes en formato calendario
 */

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/control_horario_functions.php';

// Solo ADMIN puede acceder
requierePermiso(['ADMIN']);

$usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$mes = $_GET['mes'] ?? date('Y-m');

$database = new Database();
$conn = $database->getConnection();

// Información del usuario
$stmt = $conn->prepare("SELECT id, nombre, apellido, email, tipo FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: index.php?vista=mensual&error=usuario_no_encontrado');
    exit;
}

// Datos del calendario y resumen del mes
$dias_calendario = obtenerCalendarioMensualUsuario($conn, $usuario_id, $mes);
$resumen_mensual = obtenerReporteMensual($conn, $mes, $usuario_id);
$resumen = $resumen_mensual[0] ?? null;

// Etiqueta del mes en español
$meses_es = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
             7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
$mes_label = $meses_es[(int)date('n', strtotime($mes . '-01'))] . ' ' . date('Y', strtotime($mes . '-01'));

// Mes anterior / siguiente para navegación
$mes_anterior = date('Y-m', strtotime($mes . '-01 -1 month'));
$mes_siguiente = date('Y-m', strtotime($mes . '-01 +1 month'));
$permite_mes_siguiente = $mes_siguiente <= date('Y-m');

// Construir grilla del calendario (Lunes a Domingo)
$primer_dia = new DateTime($mes . '-01');
$dias_en_mes = (int)$primer_dia->format('t');
$dia_semana_inicio = (int)$primer_dia->format('N'); // 1=lunes .. 7=domingo

$celdas = [];
for ($i = 1; $i < $dia_semana_inicio; $i++) {
    $celdas[] = null;
}
for ($dia = 1; $dia <= $dias_en_mes; $dia++) {
    $fecha_dia = $mes . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
    $registro = $dias_calendario[$dia] ?? null;
    $celdas[] = [
        'dia' => $dia,
        'fecha' => $fecha_dia,
        'registro' => $registro,
        'estado' => estadoDiaCalendario($fecha_dia, $registro)
    ];
}
while (count($celdas) % 7 !== 0) {
    $celdas[] = null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Asistencia - <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></title>
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

        .calendar-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .calendar-nav h3 {
            font-size: 1.4rem;
            color: #2c3e50;
        }

        .nav-btn {
            padding: 8px 16px;
            background: #f0f2f5;
            color: #2c3e50;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .nav-btn:hover {
            background: #00509d;
            color: white;
        }

        .nav-btn.disabled {
            visibility: hidden;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        .calendar-day-header {
            text-align: center;
            font-weight: 700;
            color: #7f8c8d;
            padding: 8px;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .calendar-cell {
            min-height: 90px;
            border-radius: 10px;
            padding: 10px;
            border: 2px solid transparent;
        }

        .calendar-cell.empty {
            background: transparent;
        }

        .calendar-cell.completo {
            background: #d5f4e6;
            border-color: #27ae60;
        }

        .calendar-cell.parcial {
            background: #fef5e7;
            border-color: #f39c12;
        }

        .calendar-cell.ausente {
            background: #fadbd8;
            border-color: #e74c3c;
        }

        .calendar-cell.descanso {
            background: #f0f0f0;
            border-color: #e0e0e0;
        }

        .calendar-cell.futuro {
            background: #fafbfc;
            border: 2px dashed #e0e0e0;
            color: #bbb;
        }

        .calendar-cell.clickable {
            cursor: pointer;
            transition: all 0.15s;
        }

        .calendar-cell.clickable:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.12);
        }

        .calendar-day-num {
            font-weight: 700;
            font-size: 1.05rem;
            color: #2c3e50;
        }

        .calendar-day-hours {
            font-size: 0.8rem;
            margin-top: 6px;
            font-weight: 600;
            color: #2c3e50;
        }

        .calendar-day-tag {
            font-size: 0.7rem;
            margin-top: 4px;
            color: #7f8c8d;
        }

        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #555;
        }

        .legend-dot {
            width: 14px;
            height: 14px;
            border-radius: 4px;
        }

        .legend-dot.completo { background: #27ae60; }
        .legend-dot.parcial { background: #f39c12; }
        .legend-dot.ausente { background: #e74c3c; }
        .legend-dot.descanso { background: #bdbdbd; }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .content {
                padding: 15px;
            }

            .calendar-cell {
                min-height: 60px;
                padding: 6px;
            }

            .calendar-day-hours {
                font-size: 0.7rem;
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
                    <i class='bx bx-calendar-check'></i>
                    <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?>
                </h2>
                <p>
                    <?php echo htmlspecialchars($usuario['email']); ?> •
                    <?php echo htmlspecialchars($usuario['tipo']); ?>
                </p>
                <a href="index.php?vista=mensual&mes=<?php echo $mes; ?>" class="btn-back">
                    <i class='bx bx-arrow-back'></i>
                    Volver al Dashboard
                </a>
            </div>

            <!-- Resumen del mes -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="label">📅 Días Trabajados</div>
                    <div class="value"><?php echo $resumen['dias_trabajados'] ?? 0; ?></div>
                </div>

                <div class="summary-card">
                    <div class="label">💼 Total Horas Trabajadas</div>
                    <div class="value"><?php echo $resumen['total_trabajado_format'] ?? '0h 0m'; ?></div>
                </div>

                <div class="summary-card">
                    <div class="label">☕ Total Refrigerio</div>
                    <div class="value"><?php echo $resumen['total_refrigerio_format'] ?? '0h 0m'; ?></div>
                </div>

                <div class="summary-card">
                    <div class="label">📊 Promedio Diario</div>
                    <div class="value"><?php echo $resumen['promedio_diario_format'] ?? '0h 0m'; ?></div>
                </div>
            </div>

            <!-- Calendario -->
            <div class="calendar-card">
                <div class="calendar-nav">
                    <a href="calendario_usuario.php?usuario_id=<?php echo $usuario_id; ?>&mes=<?php echo $mes_anterior; ?>" class="nav-btn">
                        <i class='bx bx-chevron-left'></i> Anterior
                    </a>
                    <h3><?php echo $mes_label; ?></h3>
                    <a href="calendario_usuario.php?usuario_id=<?php echo $usuario_id; ?>&mes=<?php echo $mes_siguiente; ?>"
                       class="nav-btn <?php echo $permite_mes_siguiente ? '' : 'disabled'; ?>">
                        Siguiente <i class='bx bx-chevron-right'></i>
                    </a>
                </div>

                <div class="calendar-grid">
                    <div class="calendar-day-header">Lun</div>
                    <div class="calendar-day-header">Mar</div>
                    <div class="calendar-day-header">Mié</div>
                    <div class="calendar-day-header">Jue</div>
                    <div class="calendar-day-header">Vie</div>
                    <div class="calendar-day-header">Sáb</div>
                    <div class="calendar-day-header">Dom</div>

                    <?php foreach ($celdas as $celda): ?>
                        <?php if ($celda === null): ?>
                        <div class="calendar-cell empty"></div>
                        <?php else: ?>
                        <?php
                        $es_clickable = !in_array($celda['estado'], ['futuro']);
                        $onclick = $es_clickable
                            ? "onclick=\"window.location.href='detalle_usuario.php?usuario_id={$usuario_id}&fecha={$celda['fecha']}'\""
                            : '';
                        ?>
                        <div class="calendar-cell <?php echo $celda['estado']; ?> <?php echo $es_clickable ? 'clickable' : ''; ?>" <?php echo $onclick; ?>>
                            <div class="calendar-day-num"><?php echo $celda['dia']; ?></div>
                            <?php if ($celda['registro'] && $celda['registro']['tiempo_trabajado'] > 0): ?>
                                <div class="calendar-day-hours"><?php echo $celda['registro']['tiempo_trabajado_format']; ?></div>
                            <?php elseif ($celda['estado'] === 'descanso'): ?>
                                <div class="calendar-day-tag">Descanso</div>
                            <?php elseif ($celda['estado'] === 'ausente'): ?>
                                <div class="calendar-day-tag">Sin registro</div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Leyenda -->
                <div class="legend">
                    <div class="legend-item"><span class="legend-dot completo"></span> Jornada completa (≥ 8h)</div>
                    <div class="legend-item"><span class="legend-dot parcial"></span> Jornada parcial</div>
                    <div class="legend-item"><span class="legend-dot ausente"></span> Ausente (día hábil sin registro)</div>
                    <div class="legend-item"><span class="legend-dot descanso"></span> Descanso (domingo)</div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>

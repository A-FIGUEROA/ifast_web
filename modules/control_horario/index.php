<?php
/**
 * MÓDULO DE CONTROL DE HORARIOS Y ASISTENCIA
 * Dashboard principal para administradores
 */

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/control_horario_functions.php';

// Solo ADMIN puede acceder
requierePermiso(['ADMIN']);

// Obtener conexión
$database = new Database();
$conn = $database->getConnection();

// Obtener tipo de usuario y nombre
$tipo_usuario = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

// Vista activa: diario (por defecto) o mensual
$vista = ($_GET['vista'] ?? 'diario') === 'mensual' ? 'mensual' : 'diario';

// Fecha actual (puede ser modificada por filtro) - vista diaria
$fecha = $_GET['fecha'] ?? date('Y-m-d');

// Mes actual (puede ser modificado por filtro) - vista mensual
$mes = $_GET['mes'] ?? date('Y-m');

// Filtro por usuario (aplica a ambas vistas)
$usuario_filtro = (isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '') ? (int)$_GET['usuario_id'] : null;

// Lista de usuarios para el selector de filtro
$stmt_usuarios_filtro = $conn->query("
    SELECT id, nombre, apellido
    FROM usuarios
    WHERE tipo IN ('SUPERVISOR', 'VENTAS', 'ASESOR')
    ORDER BY nombre ASC, apellido ASC
");
$lista_usuarios_filtro = $stmt_usuarios_filtro->fetchAll(PDO::FETCH_ASSOC);

if ($vista === 'mensual') {
    // Obtener reporte mensual (opcionalmente filtrado por usuario)
    $reporte_mensual = obtenerReporteMensual($conn, $mes, $usuario_filtro);

    $meses_es = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    $mes_label = $meses_es[(int)date('n', strtotime($mes . '-01'))] . ' ' . date('Y', strtotime($mes . '-01'));
} else {
    // Obtener reporte diario
    $usuarios = obtenerReporteDiario($conn, $fecha);

    if ($usuario_filtro) {
        $usuarios = array_values(array_filter($usuarios, function ($u) use ($usuario_filtro) {
            return (int)$u['id'] === $usuario_filtro;
        }));
    }
}

// Obtener conteo de estados
$conteo_estados = obtenerConteoEstados($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Horarios - Sistema de Gestión</title>
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

        /* Banner de bienvenida */
        .welcome-banner {
            background: linear-gradient(135deg, #00296B 0%, #00509D 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 41, 107, 0.3);
        }

        .welcome-banner h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-banner p {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Filtro de fecha */
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .filter-card label {
            font-weight: 600;
            color: #2c3e50;
        }

        .filter-card input[type="date"],
        .filter-card input[type="month"],
        .filter-card select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
            font-family: inherit;
        }

        .filter-card input[type="date"]:focus,
        .filter-card input[type="month"]:focus,
        .filter-card select:focus {
            outline: none;
            border-color: #00509d;
        }

        .btn-filter {
            padding: 10px 20px;
            background: linear-gradient(135deg, #00509d 0%, #00296B 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 80, 157, 0.3);
        }

        a.btn-filter {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-tab-inactive {
            background: #ecf0f1 !important;
            color: #7f8c8d !important;
        }

        /* Cards de resumen */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .stat-icon.green { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .stat-icon.orange { background: linear-gradient(135deg, #f39c12, #f1c40f); }
        .stat-icon.red { background: linear-gradient(135deg, #e74c3c, #ec7063); }

        .stat-details h3 {
            font-size: 2rem;
            color: #2c3e50;
            font-weight: 700;
        }

        .stat-details p {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        /* Tabla de usuarios */
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f5f7fa;
        }

        .card-header h3 {
            font-size: 1.3rem;
            color: #2c3e50;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 18px 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #555;
            font-size: 0.95rem;
        }

        tbody tr:nth-child(odd) {
            background: #fafbfc;
        }

        tbody tr:hover {
            background: #e8f4f8 !important;
        }

        .badge-estado {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-estado.conectado {
            background: #d5f4e6;
            color: #27ae60;
        }

        .badge-estado.refrigerio {
            background: #fef5e7;
            color: #f39c12;
        }

        .badge-estado.desconectado {
            background: #fadbd8;
            color: #e74c3c;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .status-dot.green { background: #27ae60; }
        .status-dot.orange { background: #f39c12; }
        .status-dot.red { background: #e74c3c; }

        .btn-ver {
            padding: 6px 12px;
            background: #00509d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-ver:hover {
            background: #00296B;
            transform: translateY(-2px);
        }

        /* Empty state */
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

            .stats-grid {
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
            <!-- Banner de bienvenida -->
            <div class="welcome-banner">
                <h2>
                    <i class='bx bx-time-five'></i>
                    Control de Horarios y Asistencia
                </h2>
                <p>Monitoreo en tiempo real del registro de jornadas laborales</p>
            </div>

            <!-- Selector de vista -->
            <div class="filter-card">
                <a href="index.php?vista=diario" class="btn-filter <?php echo $vista === 'diario' ? '' : 'btn-tab-inactive'; ?>">
                    <i class='bx bx-calendar-alt'></i> Vista Diaria
                </a>
                <a href="index.php?vista=mensual" class="btn-filter <?php echo $vista === 'mensual' ? '' : 'btn-tab-inactive'; ?>">
                    <i class='bx bx-calendar'></i> Vista Mensual
                </a>
            </div>

            <?php if ($vista === 'diario'): ?>
            <!-- Filtro de fecha -->
            <div class="filter-card">
                <label for="fecha">📅 Fecha:</label>
                <input type="date" id="fecha" name="fecha" value="<?php echo $fecha; ?>" max="<?php echo date('Y-m-d'); ?>">

                <label for="usuario_id_diario">👤 Usuario:</label>
                <select id="usuario_id_diario">
                    <option value="">Todos</option>
                    <?php foreach ($lista_usuarios_filtro as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $usuario_filtro === (int)$u['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn-filter" onclick="aplicarFiltro()">
                    <i class='bx bx-filter-alt'></i> Aplicar Filtro
                </button>
                <button class="btn-filter" onclick="location.href='index.php'">
                    <i class='bx bx-refresh'></i> Hoy
                </button>
            </div>

            <!-- Resumen general -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $conteo_estados['CONECTADO']; ?></h3>
                        <p>Conectados</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class='bx bx-coffee'></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $conteo_estados['REFRIGERIO']; ?></h3>
                        <p>En Refrigerio</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class='bx bx-x-circle'></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $conteo_estados['DESCONECTADO']; ?></h3>
                        <p>Desconectados</p>
                    </div>
                </div>
            </div>

            <!-- Tabla de usuarios -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class='bx bx-group'></i>
                        Usuarios - <?php echo date('d/m/Y', strtotime($fecha)); ?>
                    </h3>
                </div>

                <?php if (count($usuarios) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Hora Inicio</th>
                            <th>Hora Fin</th>
                            <th>Tiempo Trabajado</th>
                            <th>Refrigerio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong>
                                <br>
                                <small style="color: #95a5a6;"><?php echo htmlspecialchars($usuario['email']); ?></small>
                            </td>
                            <td>
                                <span class="badge-estado <?php echo strtolower($usuario['tipo']); ?>">
                                    <?php echo htmlspecialchars($usuario['tipo']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $estado_class = strtolower($usuario['estado_actual']);
                                $dot_class = $estado_class === 'conectado' ? 'green' : ($estado_class === 'refrigerio' ? 'orange' : 'red');
                                ?>
                                <span class="badge-estado <?php echo $estado_class; ?>">
                                    <span class="status-dot <?php echo $dot_class; ?>"></span>
                                    <?php echo htmlspecialchars($usuario['estado_actual']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if ($usuario['hora_inicio']) {
                                    echo date('H:i', strtotime($usuario['hora_inicio']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($usuario['hora_fin']) {
                                    echo date('H:i', strtotime($usuario['hora_fin']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <strong><?php echo $usuario['tiempo_trabajado_format']; ?></strong>
                            </td>
                            <td>
                                <?php echo $usuario['tiempo_refrigerio_format']; ?>
                            </td>
                            <td>
                                <button class="btn-ver" onclick="verDetalle(<?php echo $usuario['id']; ?>, '<?php echo $fecha; ?>')">
                                    <i class='bx bx-show'></i> Ver
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-time'></i>
                    <p>No hay registros para esta fecha</p>
                </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- Filtro mensual -->
            <div class="filter-card">
                <label for="mes">📅 Mes:</label>
                <input type="month" id="mes" name="mes" value="<?php echo $mes; ?>" max="<?php echo date('Y-m'); ?>">

                <label for="usuario_id_mensual">👤 Usuario:</label>
                <select id="usuario_id_mensual">
                    <option value="">Todos</option>
                    <?php foreach ($lista_usuarios_filtro as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $usuario_filtro === (int)$u['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn-filter" onclick="aplicarFiltroMensual()">
                    <i class='bx bx-filter-alt'></i> Aplicar Filtro
                </button>
                <button class="btn-filter" onclick="location.href='index.php?vista=mensual'">
                    <i class='bx bx-refresh'></i> Mes Actual
                </button>
            </div>

            <!-- Tabla de totales mensuales -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class='bx bx-group'></i>
                        Totales Mensuales - <?php echo $mes_label; ?>
                    </h3>
                </div>

                <?php if (count($reporte_mensual) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Días Trabajados</th>
                            <th>Total Horas Trabajadas</th>
                            <th>Total Refrigerio</th>
                            <th>Promedio Diario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reporte_mensual as $usuario): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong>
                                <br>
                                <small style="color: #95a5a6;"><?php echo htmlspecialchars($usuario['email']); ?></small>
                            </td>
                            <td>
                                <span class="badge-estado <?php echo strtolower($usuario['tipo']); ?>">
                                    <?php echo htmlspecialchars($usuario['tipo']); ?>
                                </span>
                            </td>
                            <td><?php echo (int)$usuario['dias_trabajados']; ?></td>
                            <td><strong><?php echo $usuario['total_trabajado_format']; ?></strong></td>
                            <td><?php echo $usuario['total_refrigerio_format']; ?></td>
                            <td><?php echo $usuario['promedio_diario_format']; ?></td>
                            <td>
                                <button class="btn-ver" onclick="location.href='calendario_usuario.php?usuario_id=<?php echo $usuario['id']; ?>&mes=<?php echo $mes; ?>'">
                                    <i class='bx bx-calendar'></i> Ver Calendario
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-calendar-x'></i>
                    <p>No hay registros para este mes</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function aplicarFiltro() {
            const fecha = document.getElementById('fecha').value;
            const usuarioId = document.getElementById('usuario_id_diario').value;
            if (fecha) {
                let url = `index.php?vista=diario&fecha=${fecha}`;
                if (usuarioId) url += `&usuario_id=${usuarioId}`;
                window.location.href = url;
            }
        }

        function aplicarFiltroMensual() {
            const mes = document.getElementById('mes').value;
            const usuarioId = document.getElementById('usuario_id_mensual').value;
            if (mes) {
                let url = `index.php?vista=mensual&mes=${mes}`;
                if (usuarioId) url += `&usuario_id=${usuarioId}`;
                window.location.href = url;
            }
        }

        function verDetalle(usuarioId, fecha) {
            window.location.href = `detalle_usuario.php?usuario_id=${usuarioId}&fecha=${fecha}`;
        }

        <?php if ($vista === 'diario'): ?>
        // Auto-refresh cada 30 segundos (solo en vista diaria, monitoreo en tiempo real)
        setInterval(() => {
            location.reload();
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>

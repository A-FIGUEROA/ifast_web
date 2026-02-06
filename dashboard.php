<?php
// dashboard.php
// Panel principal del sistema - Dashboard de Administrador

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Verificar que el usuario esté logueado
requiereLogin();

// Obtener conexión
$database = new Database();
$conn = $database->getConnection();

// Obtener tipo de usuario
$tipo_usuario = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

// Obtener estadísticas mensuales y de vendedores (solo para ADMIN)
$estadisticas_mensuales = [];
$vendedores_mensuales = [];
$stats_basicas = [];

if ($tipo_usuario === 'ADMIN') {
    $estadisticas_mensuales = obtenerEstadisticasMensualesGenerales($conn);
    $vendedores_mensuales = obtenerEstadisticasVendedoresMensual($conn);
} else {
    // Estadísticas básicas para usuarios no admin
    $stats_basicas = obtenerEstadisticas($conn, $tipo_usuario);
}

// Obtener nombre del mes actual en español
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$mes_actual = $meses[date('n')];
$anio_actual = date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Gestión</title>
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

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            margin-left: 260px;
            transition: all 0.3s;
        }

        /* CONTENT */
        .content {
            padding: 30px;
        }

        /* WELCOME BANNER */
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

        /* SECTION TITLES */
        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin: 40px 0 20px 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
            border-radius: 0 15px 0 100%;
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

        .stat-details {
            flex: 1;
        }

        .stat-details h3 {
            font-size: 2.2rem;
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 5px;
            line-height: 1;
        }

        .stat-details p {
            color: #7f8c8d;
            font-size: 0.95rem;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .stat-comparison {
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(0, 0, 0, 0.05);
        }

        .stat-comparison.positive {
            color: #27ae60;
            background: #d5f4e6;
        }

        .stat-comparison.negative {
            color: #e74c3c;
            background: #fadbd8;
        }

        /* CARD */
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* TABLE */
        .table-container {
            overflow-x: auto;
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

        th.text-right {
            text-align: right;
        }

        td {
            padding: 18px 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #555;
            font-size: 0.95rem;
        }

        td.text-right {
            text-align: right;
        }

        tbody tr {
            transition: background 0.2s;
        }

        tbody tr:nth-child(odd) {
            background: #fafbfc;
        }

        tbody tr:hover {
            background: #e8f4f8 !important;
        }

        td strong {
            font-size: 1rem;
            color: #2c3e50;
        }

        td small {
            font-size: 0.8rem;
            color: #95a5a6;
            display: block;
            margin-top: 3px;
        }

        /* BADGE */
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-admin {
            background: #667eea;
            color: white;
        }

        .badge-vendedor {
            background: #11998e;
            color: white;
        }

        .badge-usuario {
            background: #95a5a6;
            color: white;
        }

        .badge-new-clients {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            background: #d5f4e6;
            color: #27ae60;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.95rem;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }

        .empty-state box-icon {
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 1.1rem;
        }

        /* STATS FOR NON-ADMIN */
        .stats-grid-basic {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        /* RESPONSIVE */
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

            .stat-card {
                padding: 20px;
            }

            .stat-details h3 {
                font-size: 1.8rem;
            }

            .card {
                padding: 20px;
            }

            .table-container {
                font-size: 0.9rem;
            }

            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <!-- HEADER -->
        <header>
            <?php require_once 'includes/header.php'; ?>
        </header>

        <!-- CONTENT -->
        <div class="content">
            <!-- WELCOME BANNER -->
            <div class="welcome-banner">
                <h2>
                    Bienvenido, <?php echo explode(' ', $nombre_usuario)[0]; ?>!
                    <box-icon name='happy-beaming' type='solid' color='white' size='30px'></box-icon>
                </h2>
                <p>Dashboard de gestión y métricas del sistema</p>
            </div>

            <?php if ($tipo_usuario === 'ADMIN'): ?>
                <!-- ============================================ -->
                <!-- DASHBOARD ADMINISTRADOR -->
                <!-- ============================================ -->

                <!-- TÍTULO DE SECCIÓN -->
                <h2 class="section-title">
                    <box-icon name='line-chart' type='solid' color='#2c3e50'></box-icon>
                    Métricas del Mes - <?php echo $mes_actual . ' ' . $anio_actual; ?>
                </h2>

                <!-- MÉTRICAS MENSUALES -->
                <div class="stats-grid">
                    <!-- 1. PESO TOTAL DEL MES -->
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <box-icon name='package' type='solid' color='white'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($estadisticas_mensuales['peso_total_mes'], 2); ?> kg</h3>
                            <p>Peso Total del Mes</p>
                            <?php
                            $variacion_peso = 0;
                            if ($estadisticas_mensuales['peso_total_mes_anterior'] > 0) {
                                $variacion_peso = (($estadisticas_mensuales['peso_total_mes'] - $estadisticas_mensuales['peso_total_mes_anterior']) / $estadisticas_mensuales['peso_total_mes_anterior']) * 100;
                            } elseif ($estadisticas_mensuales['peso_total_mes'] > 0) {
                                $variacion_peso = 100;
                            }
                            $clase_peso = $variacion_peso >= 0 ? 'positive' : 'negative';
                            $icono_peso = $variacion_peso >= 0 ? '↗' : '↘';
                            ?>
                            <span class="stat-comparison <?php echo $clase_peso; ?>">
                                <?php echo $icono_peso; ?> <?php echo number_format(abs($variacion_peso), 1); ?>% vs mes pasado
                            </span>
                        </div>
                    </div>

                    <!-- 2. GUÍAS TOTALES DEL MES -->
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <box-icon name='file-blank' type='solid' color='white'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($estadisticas_mensuales['guias_mes']); ?></h3>
                            <p>Guías Totales del Mes</p>
                            <?php
                            $variacion_guias = 0;
                            if ($estadisticas_mensuales['guias_mes_anterior'] > 0) {
                                $variacion_guias = (($estadisticas_mensuales['guias_mes'] - $estadisticas_mensuales['guias_mes_anterior']) / $estadisticas_mensuales['guias_mes_anterior']) * 100;
                            } elseif ($estadisticas_mensuales['guias_mes'] > 0) {
                                $variacion_guias = 100;
                            }
                            $clase_guias = $variacion_guias >= 0 ? 'positive' : 'negative';
                            $icono_guias = $variacion_guias >= 0 ? '↗' : '↘';
                            ?>
                            <span class="stat-comparison <?php echo $clase_guias; ?>">
                                <?php echo $icono_guias; ?> <?php echo number_format(abs($variacion_guias), 1); ?>% vs mes pasado
                            </span>
                        </div>
                    </div>

                    <!-- 3. FACTURACIÓN MENSUAL -->
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <box-icon name='dollar-circle' type='solid' color='white'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3>$<?php echo number_format($estadisticas_mensuales['facturacion_mes'], 2); ?></h3>
                            <p>Facturación Total del Mes</p>
                            <?php
                            $variacion_fact = 0;
                            if ($estadisticas_mensuales['facturacion_mes_anterior'] > 0) {
                                $variacion_fact = (($estadisticas_mensuales['facturacion_mes'] - $estadisticas_mensuales['facturacion_mes_anterior']) / $estadisticas_mensuales['facturacion_mes_anterior']) * 100;
                            } elseif ($estadisticas_mensuales['facturacion_mes'] > 0) {
                                $variacion_fact = 100;
                            }
                            $clase_fact = $variacion_fact >= 0 ? 'positive' : 'negative';
                            $icono_fact = $variacion_fact >= 0 ? '↗' : '↘';
                            ?>
                            <span class="stat-comparison <?php echo $clase_fact; ?>">
                                <?php echo $icono_fact; ?> <?php echo number_format(abs($variacion_fact), 1); ?>% vs mes pasado
                            </span>
                        </div>
                    </div>

                    <!-- 4. DOCUMENTOS GENERADOS (BONUS) -->
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <box-icon name='receipt' type='solid' color='white'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($estadisticas_mensuales['documentos_mes']); ?></h3>
                            <p>Documentos Generados</p>
                            <span style="font-size: 0.85rem; color: #666;">
                                <?php echo $estadisticas_mensuales['facturas_mes']; ?> Facturas ·
                                <?php echo $estadisticas_mensuales['boletas_mes']; ?> Boletas ·
                                <?php echo $estadisticas_mensuales['recibos_mes']; ?> Recibos
                            </span>
                        </div>
                    </div>
                </div>

                <!-- TABLA DE VENDEDORES -->
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <box-icon name='group' type='solid' color='#2c3e50'></box-icon>
                            Rendimiento de Vendedores - <?php echo $mes_actual . ' ' . $anio_actual; ?>
                        </h3>
                    </div>
                    <div class="table-container">
                        <?php if (count($vendedores_mensuales) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Vendedor</th>
                                    <th>Rol</th>
                                    <th class="text-right">Peso (kg)</th>
                                    <th class="text-right">Guías</th>
                                    <th class="text-right">Facturación</th>
                                    <th class="text-right">Clientes Nuevos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendedores_mensuales as $vendedor): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($vendedor['nombre_completo']); ?></strong>
                                        <small><?php echo htmlspecialchars($vendedor['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $rol_lower = strtolower($vendedor['rol']);
                                        $badge_class = 'badge-usuario';
                                        if ($rol_lower === 'admin') {
                                            $badge_class = 'badge-admin';
                                        } elseif (strpos($rol_lower, 'vend') !== false) {
                                            $badge_class = 'badge-vendedor';
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($vendedor['rol']); ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <strong><?php echo number_format($vendedor['peso_mes'], 2); ?> kg</strong>
                                        <small>Total: <?php echo number_format($vendedor['peso_total'], 2); ?> kg</small>
                                    </td>
                                    <td class="text-right">
                                        <strong><?php echo number_format($vendedor['guias_mes']); ?></strong>
                                        <small>Total: <?php echo number_format($vendedor['guias_total']); ?></small>
                                    </td>
                                    <td class="text-right">
                                        <strong style="color: #00509d; font-size: 1.05rem;">$<?php echo number_format($vendedor['facturacion_mes'], 2); ?></strong>
                                        <small>Total: $<?php echo number_format($vendedor['facturacion_total'], 2); ?></small>
                                    </td>
                                    <td class="text-right">
                                        <?php if ($vendedor['clientes_nuevos_mes'] > 0): ?>
                                        <span class="badge-new-clients">
                                            <box-icon name='user-plus' type='solid' size='16px' color='#27ae60'></box-icon>
                                            +<?php echo $vendedor['clientes_nuevos_mes']; ?>
                                        </span>
                                        <?php else: ?>
                                        <span style="color: #95a5a6;">0</span>
                                        <?php endif; ?>
                                        <small>Total: <?php echo $vendedor['clientes_total']; ?> clientes</small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <box-icon name='user-x' size='60px' color='#95a5a6'></box-icon>
                            <p>No hay datos de vendedores para mostrar</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- ============================================ -->
                <!-- DASHBOARD PARA USUARIOS NO ADMIN -->
                <!-- ============================================ -->
                <h2 class="section-title">
                    <box-icon name='pie-chart-alt-2' type='solid' color='#2c3e50'></box-icon>
                    Estadísticas Generales
                </h2>

                <div class="stats-grid-basic">
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <box-icon type='solid' name='user-check' color='white'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats_basicas['total_clientes']; ?></h3>
                            <p>Total Clientes</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <box-icon name='package' type='solid' color='white'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats_basicas['total_pedidos']; ?></h3>
                            <p>Total Pedidos</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <box-icon name='file' type='solid' color='white'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats_basicas['total_archivos']; ?></h3>
                            <p>Total Archivos</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</body>
</html>

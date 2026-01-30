<?php
// dashboard.php
// Panel principal del sistema

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Verificar que el usuario est茅 logueado
requiereLogin();

// Obtener conexi贸n
$database = new Database();
$conn = $database->getConnection();

// Obtener tipo de usuario
$tipo_usuario = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

// Obtener estad铆sticas (pasando tipo de usuario para estad铆sticas avanzadas)
$stats = obtenerEstadisticas($conn, $tipo_usuario);

// Obtener estad铆sticas de embarques y facturaci贸n por usuario (solo para ADMIN)
$embarques_por_usuario = [];
$facturacion_por_usuario = [];
$evolucion_facturacion = [];

if ($tipo_usuario === 'ADMIN') {
    $embarques_por_usuario = obtenerEstadisticasEmbarquesPorUsuario($conn);
    $facturacion_por_usuario = obtenerEstadisticasFacturacionPorUsuario($conn);
    $evolucion_facturacion = obtenerEvolucionDiariaFacturacion($conn);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Gestion</title>
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

        .welcome-banner {
            background: linear-gradient(135deg, #00296B 0%, #00509D 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .welcome-banner h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .welcome-banner p {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
            gap: 20px;
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
        }

        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

        .stat-details h3 {
            font-size: 2rem;
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-details p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        /* TABLE */
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f5f7fa;
        }

        .card-header h3 {
            font-size: 1.3rem;
            color: #2c3e50;
            font-weight: 600;
        }

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
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #555;
        }

        tr:hover td {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* ESTADÍSTICAS COLORES */
        .text-success {
            color: #28a745;
        }

        .text-danger {
            color: #dc3545;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {

            .main-content {
                margin-left: 0;
            }

            .header {
                padding: 15px;
            }

            .header-left h1 {
                font-size: 1.3rem;
            }

            .user-details {
                display: none;
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
 <?php require_once 'includes/sidebar.php'; ?>
        <!-- MAIN CONTENT -->
<main class="main-content">
            <!-- HEADER -->
     <header >
         <?php require_once 'includes/header.php';?>
     </header>

            <!-- CONTENT -->
            <div class="content">
                <!-- WELCOME BANNER -->
                <div class="welcome-banner">
                    <h2>Bienvenido, <?php echo explode(' ', $nombre_usuario)[0]; ?>! <box-icon name='happy-beaming' type='solid' color= 'white' size='30px'></box-icon></h2>
                    <p>Aqui tienes un resumen de tu sistema de gestion</p>
                </div>

                <!-- STATS -->
                <?php if ($tipo_usuario === 'ADMIN'): ?>
                <!-- DASHBOARD ADMINISTRADOR CON ESTADÍSTICAS AVANZADAS -->

                <!-- Tarjetas de Hoy con Comparación -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <box-icon type='solid' name='user-check'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['clientes_hoy']; ?></h3>
                            <p>Clientes Hoy</p>
                            <?php
                            $variacion_clientes = 0;
                            if ($stats['clientes_ayer'] > 0) {
                                $variacion_clientes = (($stats['clientes_hoy'] - $stats['clientes_ayer']) / $stats['clientes_ayer']) * 100;
                            } elseif ($stats['clientes_hoy'] > 0) {
                                $variacion_clientes = 100;
                            }
                            $clase_variacion = $variacion_clientes >= 0 ? 'text-success' : 'text-danger';
                            $icono_variacion = $variacion_clientes >= 0 ? '↗️' : '↘️';
                            ?>
                            <small class="<?php echo $clase_variacion; ?>" style="font-size: 0.85rem; font-weight: 600;">
                                <?php echo $icono_variacion; ?> <?php echo number_format(abs($variacion_clientes), 1); ?>% vs ayer
                            </small>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green">
                            <box-icon name='package' type='solid'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['pedidos_hoy']; ?></h3>
                            <p>Pedidos Hoy</p>
                            <?php
                            $variacion_pedidos = 0;
                            if ($stats['pedidos_ayer'] > 0) {
                                $variacion_pedidos = (($stats['pedidos_hoy'] - $stats['pedidos_ayer']) / $stats['pedidos_ayer']) * 100;
                            } elseif ($stats['pedidos_hoy'] > 0) {
                                $variacion_pedidos = 100;
                            }
                            $clase_variacion = $variacion_pedidos >= 0 ? 'text-success' : 'text-danger';
                            $icono_variacion = $variacion_pedidos >= 0 ? '↗️' : '↘️';
                            ?>
                            <small class="<?php echo $clase_variacion; ?>" style="font-size: 0.85rem; font-weight: 600;">
                                <?php echo $icono_variacion; ?> <?php echo number_format(abs($variacion_pedidos), 1); ?>% vs ayer
                            </small>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <box-icon name='ship' type='solid'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['embarques_hoy']; ?></h3>
                            <p>Embarques Hoy</p>
                            <?php
                            $variacion_embarques = 0;
                            if ($stats['embarques_ayer'] > 0) {
                                $variacion_embarques = (($stats['embarques_hoy'] - $stats['embarques_ayer']) / $stats['embarques_ayer']) * 100;
                            } elseif ($stats['embarques_hoy'] > 0) {
                                $variacion_embarques = 100;
                            }
                            $clase_variacion = $variacion_embarques >= 0 ? 'text-success' : 'text-danger';
                            $icono_variacion = $variacion_embarques >= 0 ? '↗️' : '↘️';
                            ?>
                            <small class="<?php echo $clase_variacion; ?>" style="font-size: 0.85rem; font-weight: 600;">
                                <?php echo $icono_variacion; ?> <?php echo number_format(abs($variacion_embarques), 1); ?>% vs ayer
                            </small>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <box-icon name='dollar-circle' type='solid'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3>$<?php echo number_format($stats['total_facturacion_hoy'], 2); ?></h3>
                            <p>Facturado Hoy</p>
                            <?php
                            $variacion_facturacion = 0;
                            if ($stats['total_facturacion_ayer'] > 0) {
                                $variacion_facturacion = (($stats['total_facturacion_hoy'] - $stats['total_facturacion_ayer']) / $stats['total_facturacion_ayer']) * 100;
                            } elseif ($stats['total_facturacion_hoy'] > 0) {
                                $variacion_facturacion = 100;
                            }
                            $clase_variacion = $variacion_facturacion >= 0 ? 'text-success' : 'text-danger';
                            $icono_variacion = $variacion_facturacion >= 0 ? '↗️' : '↘️';
                            ?>
                            <small class="<?php echo $clase_variacion; ?>" style="font-size: 0.85rem; font-weight: 600;">
                                <?php echo $icono_variacion; ?> <?php echo number_format(abs($variacion_facturacion), 1); ?>% vs ayer
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Tarjetas de Totales Acumulados -->
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3>📈 Totales Acumulados del Sistema</h3>
                    </div>
                    <div class="stats-grid" style="padding: 10px 0;">
                        <div class="stat-card">
                            <div class="stat-icon blue">
                                <box-icon name='group' type='solid'></box-icon>
                            </div>
                            <div class="stat-details">
                                <h3><?php echo $stats['total_usuarios']; ?></h3>
                                <p>Total Usuarios</p>
                                <small style="font-size: 0.8rem; color: #28a745; font-weight: 600;">
                                    <?php echo $stats['usuarios_activos']; ?> activos
                                </small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon green">
                                <box-icon name='user-check' type='solid'></box-icon>
                            </div>
                            <div class="stat-details">
                                <h3><?php echo $stats['total_clientes']; ?></h3>
                                <p>Total Clientes</p>
                                <small style="font-size: 0.8rem; color: #666;">
                                    Registrados en el sistema
                                </small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon orange">
                                <box-icon name='ship' type='solid'></box-icon>
                            </div>
                            <div class="stat-details">
                                <h3><?php echo $stats['total_guias']; ?></h3>
                                <p>Total Guías</p>
                                <small style="font-size: 0.8rem; color: #666;">
                                    <?php echo $stats['guias_pendientes']; ?> pendientes |
                                    <?php echo $stats['guias_entregadas']; ?> entregadas
                                </small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon purple">
                                <box-icon name='file-blank' type='solid'></box-icon>
                            </div>
                            <div class="stat-details">
                                <h3><?php echo $stats['total_documentos_facturacion']; ?></h3>
                                <p>Total Documentos</p>
                                <small style="font-size: 0.8rem; color: #666;">
                                    Facturas, Boletas y Recibos
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos de Estadísticas -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <!-- Gráfico: Clientes, Pedidos y Embarques por Periodo -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📊 Actividad por Periodo</h3>
                        </div>
                        <div style="padding: 20px;">
                            <canvas id="chartActividadPeriodo" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico: Distribución de Documentos -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📄 Documentos Emitidos (Últimos 30 Días)</h3>
                        </div>
                        <div style="padding: 20px;">
                            <canvas id="chartDistribucionDocumentos" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico: Estados de Guías/Embarques -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📦 Estados de Guías/Embarques</h3>
                        </div>
                        <div style="padding: 20px;">
                            <canvas id="chartEstadosGuias" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Gráfico: Facturación por Periodo -->
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3>💰 Facturación por Periodo</h3>
                    </div>
                    <div style="padding: 20px;">
                        <canvas id="chartFacturacionPeriodo" height="120"></canvas>
                    </div>
                </div>

                <!-- Gráficos adicionales -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <!-- Gráfico: Top 10 Clientes con Más Pedidos -->
                    <div class="card">
                        <div class="card-header">
                            <h3>🏆 Top 10 Clientes - Más Pedidos</h3>
                        </div>
                        <div style="padding: 20px;">
                            <canvas id="chartTopClientesPedidos" height="300"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico: Top 10 Clientes con Mayor Facturación -->
                    <div class="card">
                        <div class="card-header">
                            <h3>💎 Top 10 Clientes - Mayor Facturación</h3>
                        </div>
                        <div style="padding: 20px;">
                            <canvas id="chartTopClientesFacturacion" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Gráfico: Tendencia Mensual -->
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3>📈 Tendencia Últimos 6 Meses</h3>
                    </div>
                    <div style="padding: 20px;">
                        <canvas id="chartTendenciaMensual" height="120"></canvas>
                    </div>
                </div>

                <!-- ===================================== -->
                <!-- SECCIÓN: EMBARQUES POR USUARIO -->
                <!-- ===================================== -->
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3>📦 Embarques por Usuario</h3>
                    </div>
                    <div class="table-container" style="padding: 10px 0;">
                        <?php if (!empty($embarques_por_usuario)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th style="text-align: center;">Hoy</th>
                                    <th style="text-align: center;">Últimos 7 Días</th>
                                    <th style="text-align: center;">Últimos 30 Días</th>
                                    <th style="text-align: center;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($embarques_por_usuario as $embarque): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($embarque['nombre_completo']); ?></strong></td>
                                    <td>
                                        <?php
                                        $badge_class = 'badge';
                                        if ($embarque['rol'] == 'ADMIN') {
                                            $badge_class .= ' badge-danger';
                                            echo '<span class="' . $badge_class . '" style="background: #667eea; color: white;">' . $embarque['rol'] . '</span>';
                                        } elseif ($embarque['rol'] == 'SUPERVISOR') {
                                            echo '<span class="badge" style="background: #11998e; color: white;">' . $embarque['rol'] . '</span>';
                                        } else {
                                            echo '<span class="badge" style="background: #f093fb; color: white;">' . $embarque['rol'] . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td style="text-align: center;"><strong><?php echo number_format($embarque['hoy']); ?></strong></td>
                                    <td style="text-align: center;"><?php echo number_format($embarque['semana']); ?></td>
                                    <td style="text-align: center;"><?php echo number_format($embarque['mes']); ?></td>
                                    <td style="text-align: center;"><strong style="color: #667eea;"><?php echo number_format($embarque['total']); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td colspan="2">TOTALES</td>
                                    <td style="text-align: center;">
                                        <?php echo number_format(array_sum(array_column($embarques_por_usuario, 'hoy'))); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php echo number_format(array_sum(array_column($embarques_por_usuario, 'semana'))); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php echo number_format(array_sum(array_column($embarques_por_usuario, 'mes'))); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php echo number_format(array_sum(array_column($embarques_por_usuario, 'total'))); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <i>--</i>
                            <p>No hay datos de embarques disponibles</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Gráfico de Embarques Mensuales por Usuario -->
                    <?php if (!empty($embarques_por_usuario)): ?>
                    <div style="padding: 20px; border-top: 2px solid #f5f7fa;">
                        <h4 style="margin-bottom: 15px; color: #2c3e50; font-size: 1.1rem;">📊 Embarques Mensuales por Usuario</h4>
                        <canvas id="chartEmbarquesPorUsuario" height="100"></canvas>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ===================================== -->
                <!-- SECCIÓN: FACTURACIÓN POR USUARIO -->
                <!-- ===================================== -->
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3>💰 Facturación por Usuario</h3>
                    </div>
                    <div class="table-container" style="padding: 10px 0;">
                        <?php if (!empty($facturacion_por_usuario)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th style="text-align: right;">Hoy</th>
                                    <th style="text-align: right;">Últimos 7 Días</th>
                                    <th style="text-align: right;">Últimos 30 Días</th>
                                    <th style="text-align: right;">Total</th>
                                    <th style="text-align: center;">Docs</th>
                                    <th style="text-align: center;">Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($facturacion_por_usuario as $factura): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($factura['nombre_completo']); ?></strong></td>
                                    <td>
                                        <?php
                                        if ($factura['rol'] == 'ADMIN') {
                                            echo '<span class="badge" style="background: #667eea; color: white;">' . $factura['rol'] . '</span>';
                                        } elseif ($factura['rol'] == 'SUPERVISOR') {
                                            echo '<span class="badge" style="background: #11998e; color: white;">' . $factura['rol'] . '</span>';
                                        } else {
                                            echo '<span class="badge" style="background: #f093fb; color: white;">' . $factura['rol'] . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td style="text-align: right;"><strong>$<?php echo number_format($factura['hoy'], 2); ?></strong></td>
                                    <td style="text-align: right;">$<?php echo number_format($factura['semana'], 2); ?></td>
                                    <td style="text-align: right;">$<?php echo number_format($factura['mes'], 2); ?></td>
                                    <td style="text-align: right;"><strong style="color: #11998e;">$<?php echo number_format($factura['total'], 2); ?></strong></td>
                                    <td style="text-align: center;"><?php echo number_format($factura['cantidad_documentos']); ?></td>
                                    <td style="text-align: center;">
                                        <small style="display: block; color: #666;">
                                            F: <?php echo $factura['total_facturas']; ?> |
                                            B: <?php echo $factura['total_boletas']; ?> |
                                            R: <?php echo $factura['total_recibos']; ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td colspan="2">TOTALES</td>
                                    <td style="text-align: right;">
                                        $<?php echo number_format(array_sum(array_column($facturacion_por_usuario, 'hoy')), 2); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        $<?php echo number_format(array_sum(array_column($facturacion_por_usuario, 'semana')), 2); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        $<?php echo number_format(array_sum(array_column($facturacion_por_usuario, 'mes')), 2); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        $<?php echo number_format(array_sum(array_column($facturacion_por_usuario, 'total')), 2); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php echo number_format(array_sum(array_column($facturacion_por_usuario, 'cantidad_documentos'))); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <small style="display: block; color: #666;">
                                            F: <?php echo array_sum(array_column($facturacion_por_usuario, 'total_facturas')); ?> |
                                            B: <?php echo array_sum(array_column($facturacion_por_usuario, 'total_boletas')); ?> |
                                            R: <?php echo array_sum(array_column($facturacion_por_usuario, 'total_recibos')); ?>
                                        </small>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <i>--</i>
                            <p>No hay datos de facturación disponibles</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Gráfico de Facturación Mensual por Usuario -->
                    <?php if (!empty($facturacion_por_usuario)): ?>
                    <div style="padding: 20px; border-top: 2px solid #f5f7fa;">
                        <h4 style="margin-bottom: 15px; color: #2c3e50; font-size: 1.1rem;">📊 Facturación Mensual por Usuario</h4>
                        <canvas id="chartFacturacionPorUsuario" height="100"></canvas>
                    </div>
                    <?php endif; ?>

                    <!-- Gráfico de Evolución Diaria de Facturación -->
                    <?php if (!empty($evolucion_facturacion)): ?>
                    <div style="padding: 20px; border-top: 2px solid #f5f7fa;">
                        <h4 style="margin-bottom: 15px; color: #2c3e50; font-size: 1.1rem;">📅 Evolución Diaria de Facturación (Últimos 30 Días)</h4>
                        <canvas id="chartEvolucionFacturacion" height="100"></canvas>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tablas de Datos Recientes -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 20px; margin-bottom: 30px;">

                    <!-- Tabla: Embarques/Guías Recientes -->
                    <div class="card">
                        <div class="card-header">
                            <h3>🚢 Embarques Recientes</h3>
                        </div>
                        <div class="table-container">
                            <?php if (!empty($stats['embarques_recientes'])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nro Guía</th>
                                        <th>Cliente</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['embarques_recientes'] as $embarque): ?>
                                    <tr>
                                        <td><strong><?php echo $embarque['nro_guia']; ?></strong></td>
                                        <td>
                                            <?php
                                            echo $embarque['nombre_razon_social'];
                                            if ($embarque['apellido']) {
                                                echo ' ' . $embarque['apellido'];
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $clase_badge = 'badge-success';
                                            if ($embarque['estado'] == 'PENDIENTE') {
                                                $clase_badge = 'badge badge-warning';
                                                echo '<span class="' . $clase_badge . '" style="background: #fff3cd; color: #856404;">Pendiente</span>';
                                            } elseif ($embarque['estado'] == 'ENTREGADO') {
                                                echo '<span class="badge badge-success">Entregado</span>';
                                            } elseif ($embarque['estado'] == 'OBSERVADO') {
                                                echo '<span class="badge badge-danger" style="background: #f8d7da; color: #721c24;">Observado</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo formatearFecha($embarque['creado_en'], 'd/m/Y'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="empty-state">
                                <i>--</i>
                                <p>No hay embarques registrados aún</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tabla: Documentos de Facturación Recientes -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📄 Documentos Recientes</h3>
                        </div>
                        <div class="table-container">
                            <?php if (!empty($stats['documentos_recientes'])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['documentos_recientes'] as $doc): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $doc['tipo_documento']; ?></strong><br>
                                            <small style="color: #666;"><?php echo $doc['numero_documento']; ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            echo $doc['nombre_razon_social'];
                                            if ($doc['apellido']) {
                                                echo ' ' . $doc['apellido'];
                                            }
                                            ?>
                                        </td>
                                        <td><strong>$<?php echo number_format($doc['total'], 2); ?></strong></td>
                                        <td><?php echo formatearFecha($doc['creado_en'], 'd/m/Y'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="empty-state">
                                <i>--</i>
                                <p>No hay documentos registrados aún</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tabla: Clientes Registrados Recientemente -->
                    <div class="card">
                        <div class="card-header">
                            <h3>👥 Clientes Nuevos</h3>
                        </div>
                        <div class="table-container">
                            <?php if (!empty($stats['clientes_recientes'])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Documento</th>
                                        <th>Contacto</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['clientes_recientes'] as $cliente): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <?php
                                                echo $cliente['nombre_razon_social'];
                                                if ($cliente['apellido']) {
                                                    echo ' ' . $cliente['apellido'];
                                                }
                                                ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <small style="color: #666;">
                                                <?php echo $cliente['tipo_documento']; ?>:
                                                <?php echo $cliente['numero_documento']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small><?php echo $cliente['email']; ?></small>
                                        </td>
                                        <td><?php echo formatearFecha($cliente['creado_en'], 'd/m/Y'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="empty-state">
                                <i>--</i>
                                <p>No hay clientes registrados aún</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Accesos Rápidos a Módulos -->
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3>🚀 Accesos Rápidos</h3>
                    </div>
                    <div style="padding: 20px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">

                            <a href="modules/clientes/index.php" style="text-decoration: none;">
                                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                                    <box-icon name='group' type='solid' color='white' size='40px'></box-icon>
                                    <h4 style="margin: 10px 0 5px 0; font-size: 1rem;">Clientes</h4>
                                    <small style="opacity: 0.9;">Gestionar clientes</small>
                                </div>
                            </a>

                            <a href="modules/pedidos/index.php" style="text-decoration: none;">
                                <div style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                                    <box-icon name='package' type='solid' color='white' size='40px'></box-icon>
                                    <h4 style="margin: 10px 0 5px 0; font-size: 1rem;">Pedidos</h4>
                                    <small style="opacity: 0.9;">Ver pedidos</small>
                                </div>
                            </a>

                            <a href="modules/guias/index.php" style="text-decoration: none;">
                                <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                                    <box-icon name='ship' type='solid' color='white' size='40px'></box-icon>
                                    <h4 style="margin: 10px 0 5px 0; font-size: 1rem;">Guías</h4>
                                    <small style="opacity: 0.9;">Gestionar guías</small>
                                </div>
                            </a>

                            <a href="modules/embarques/index.php" style="text-decoration: none;">
                                <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                                    <box-icon name='world' type='solid' color='white' size='40px'></box-icon>
                                    <h4 style="margin: 10px 0 5px 0; font-size: 1rem;">Embarques</h4>
                                    <small style="opacity: 0.9;">Ver embarques</small>
                                </div>
                            </a>

                            <a href="modules/facturacion/index.php" style="text-decoration: none;">
                                <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                                    <box-icon name='dollar-circle' type='solid' color='white' size='40px'></box-icon>
                                    <h4 style="margin: 10px 0 5px 0; font-size: 1rem;">Facturación</h4>
                                    <small style="opacity: 0.9;">Documentos</small>
                                </div>
                            </a>

                            <a href="modules/usuarios/index.php" style="text-decoration: none;">
                                <div style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                                    <box-icon name='user-circle' type='solid' color='white' size='40px'></box-icon>
                                    <h4 style="margin: 10px 0 5px 0; font-size: 1rem;">Usuarios</h4>
                                    <small style="opacity: 0.9;">Gestionar usuarios</small>
                                </div>
                            </a>

                            <a href="modules/reportes/index.php" style="text-decoration: none;">
                                <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                                    <box-icon name='bar-chart-alt-2' type='solid' color='#333' size='40px'></box-icon>
                                    <h4 style="margin: 10px 0 5px 0; font-size: 1rem;">Reportes</h4>
                                    <small style="opacity: 0.9;">Ver reportes</small>
                                </div>
                            </a>

                            <a href="modules/facturacion/crear.php" style="text-decoration: none;">
                                <div style="background: linear-gradient(135deg, #ff6e7f 0%, #bfe9ff 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                                    <box-icon name='plus-circle' type='solid' color='white' size='40px'></box-icon>
                                    <h4 style="margin: 10px 0 5px 0; font-size: 1rem;">Nuevo Documento</h4>
                                    <small style="opacity: 0.9;">Crear factura/boleta</small>
                                </div>
                            </a>

                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- DASHBOARD PARA OTROS USUARIOS -->
                <div class="stats-grid">
                    <?php if ($tipo_usuario === 'ADMIN'): ?>
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <box-icon name="rocket"></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_usuarios']; ?></h3>
                            <p>Total Usuarios</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="stat-card">
                        <div class="stat-icon green">
                            <box-icon type='solid' name='user-check'></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_clientes']; ?></h3>
                            <p>Total Clientes</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <box-icon name='package' type='solid' flip='horizontal' ></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_pedidos']; ?></h3>
                            <p>Total Pedidos</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <box-icon name='file' type='solid' flip='horizontal' ></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_archivos']; ?></h3>
                            <p>Total Archivos</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <?php if ($tipo_usuario === 'ADMIN'): ?>
    <!-- JavaScript para Gráficos del Dashboard ADMIN -->
    <script>
        // ========================================
        // GRÁFICO 1: ACTIVIDAD POR PERIODO (Barras Agrupadas)
        // ========================================
        const ctxActividad = document.getElementById('chartActividadPeriodo');
        if (ctxActividad) {
            new Chart(ctxActividad, {
                type: 'bar',
                data: {
                    labels: ['Hoy', 'Últimos 7 Días', 'Últimos 30 Días'],
                    datasets: [
                        {
                            label: 'Clientes',
                            data: [
                                <?php echo $stats['clientes_hoy']; ?>,
                                <?php echo $stats['clientes_ultimos_7_dias']; ?>,
                                <?php echo $stats['clientes_ultimos_30_dias']; ?>
                            ],
                            backgroundColor: 'rgba(102, 126, 234, 0.8)',
                            borderColor: 'rgba(102, 126, 234, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Pedidos',
                            data: [
                                <?php echo $stats['pedidos_hoy']; ?>,
                                <?php echo $stats['pedidos_ultimos_7_dias']; ?>,
                                <?php echo $stats['pedidos_ultimos_30_dias']; ?>
                            ],
                            backgroundColor: 'rgba(17, 153, 142, 0.8)',
                            borderColor: 'rgba(17, 153, 142, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Embarques',
                            data: [
                                <?php echo $stats['embarques_hoy']; ?>,
                                <?php echo $stats['embarques_ultimos_7_dias']; ?>,
                                <?php echo $stats['embarques_ultimos_30_dias']; ?>
                            ],
                            backgroundColor: 'rgba(245, 87, 108, 0.8)',
                            borderColor: 'rgba(245, 87, 108, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // ========================================
        // GRÁFICO 2: DISTRIBUCIÓN DE DOCUMENTOS (Dona)
        // ========================================
        const ctxDocumentos = document.getElementById('chartDistribucionDocumentos');
        if (ctxDocumentos) {
            const totalFacturas = <?php echo $stats['facturas_ultimos_30_dias']; ?>;
            const totalBoletas = <?php echo $stats['boletas_ultimos_30_dias']; ?>;
            const totalRecibos = <?php echo $stats['recibos_ultimos_30_dias']; ?>;
            const totalDocumentos = totalFacturas + totalBoletas + totalRecibos;

            new Chart(ctxDocumentos, {
                type: 'doughnut',
                data: {
                    labels: ['Facturas', 'Boletas', 'Recibos'],
                    datasets: [{
                        data: [totalFacturas, totalBoletas, totalRecibos],
                        backgroundColor: [
                            'rgba(102, 126, 234, 0.8)',
                            'rgba(56, 239, 125, 0.8)',
                            'rgba(79, 172, 254, 0.8)'
                        ],
                        borderColor: [
                            'rgba(102, 126, 234, 1)',
                            'rgba(56, 239, 125, 1)',
                            'rgba(79, 172, 254, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const percentage = totalDocumentos > 0 ? ((value / totalDocumentos) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        // ========================================
        // GRÁFICO 3: FACTURACIÓN POR PERIODO (Barras Apiladas)
        // ========================================
        const ctxFacturacion = document.getElementById('chartFacturacionPeriodo');
        if (ctxFacturacion) {
            new Chart(ctxFacturacion, {
                type: 'bar',
                data: {
                    labels: ['Hoy', 'Últimos 7 Días', 'Últimos 30 Días'],
                    datasets: [
                        {
                            label: 'Facturas',
                            data: [
                                <?php echo $stats['monto_facturas_hoy']; ?>,
                                <?php echo $stats['monto_facturas_ultimos_7_dias']; ?>,
                                <?php echo $stats['monto_facturas_ultimos_30_dias']; ?>
                            ],
                            backgroundColor: 'rgba(102, 126, 234, 0.8)',
                            borderColor: 'rgba(102, 126, 234, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Boletas',
                            data: [
                                <?php echo $stats['monto_boletas_hoy']; ?>,
                                <?php echo $stats['monto_boletas_ultimos_7_dias']; ?>,
                                <?php echo $stats['monto_boletas_ultimos_30_dias']; ?>
                            ],
                            backgroundColor: 'rgba(56, 239, 125, 0.8)',
                            borderColor: 'rgba(56, 239, 125, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Recibos',
                            data: [
                                <?php echo $stats['monto_recibos_hoy']; ?>,
                                <?php echo $stats['monto_recibos_ultimos_7_dias']; ?>,
                                <?php echo $stats['monto_recibos_ultimos_30_dias']; ?>
                            ],
                            backgroundColor: 'rgba(79, 172, 254, 0.8)',
                            borderColor: 'rgba(79, 172, 254, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += '$' + context.parsed.y.toFixed(2);
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(0);
                                }
                            }
                        }
                    }
                }
            });
        }

        // ========================================
        // GRÁFICO 4: ESTADOS DE GUÍAS/EMBARQUES (Dona)
        // ========================================
        const ctxEstadosGuias = document.getElementById('chartEstadosGuias');
        if (ctxEstadosGuias) {
            const guiasPendientes = <?php echo $stats['guias_pendientes']; ?>;
            const guiasEntregadas = <?php echo $stats['guias_entregadas']; ?>;
            const guiasObservadas = <?php echo $stats['guias_observadas']; ?>;
            const totalGuias = guiasPendientes + guiasEntregadas + guiasObservadas;

            new Chart(ctxEstadosGuias, {
                type: 'doughnut',
                data: {
                    labels: ['Pendientes', 'Entregadas', 'Observadas'],
                    datasets: [{
                        data: [guiasPendientes, guiasEntregadas, guiasObservadas],
                        backgroundColor: [
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(40, 167, 69, 0.8)',
                            'rgba(220, 53, 69, 0.8)'
                        ],
                        borderColor: [
                            'rgba(255, 193, 7, 1)',
                            'rgba(40, 167, 69, 1)',
                            'rgba(220, 53, 69, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const percentage = totalGuias > 0 ? ((value / totalGuias) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        // ========================================
        // GRÁFICO 5: TOP 10 CLIENTES - MÁS PEDIDOS (Barras Horizontales)
        // ========================================
        const ctxTopClientesPedidos = document.getElementById('chartTopClientesPedidos');
        if (ctxTopClientesPedidos) {
            const topClientesPedidos = <?php echo json_encode($stats['top_clientes_pedidos']); ?>;
            const nombresClientes = topClientesPedidos.map(c => {
                return c.nombre_razon_social + (c.apellido ? ' ' + c.apellido : '');
            });
            const totalPedidos = topClientesPedidos.map(c => parseInt(c.total_pedidos));

            new Chart(ctxTopClientesPedidos, {
                type: 'bar',
                data: {
                    labels: nombresClientes,
                    datasets: [{
                        label: 'Pedidos',
                        data: totalPedidos,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Pedidos: ' + context.parsed.x;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // ========================================
        // GRÁFICO 6: TOP 10 CLIENTES - MAYOR FACTURACIÓN (Barras Horizontales)
        // ========================================
        const ctxTopClientesFacturacion = document.getElementById('chartTopClientesFacturacion');
        if (ctxTopClientesFacturacion) {
            const topClientesFacturacion = <?php echo json_encode($stats['top_clientes_facturacion']); ?>;
            const nombresClientesFacturacion = topClientesFacturacion.map(c => {
                return c.nombre_razon_social + (c.apellido ? ' ' + c.apellido : '');
            });
            const totalFacturado = topClientesFacturacion.map(c => parseFloat(c.total_facturado));

            new Chart(ctxTopClientesFacturacion, {
                type: 'bar',
                data: {
                    labels: nombresClientesFacturacion,
                    datasets: [{
                        label: 'Facturación',
                        data: totalFacturado,
                        backgroundColor: 'rgba(17, 153, 142, 0.8)',
                        borderColor: 'rgba(17, 153, 142, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Facturado: $' + context.parsed.x.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(0);
                                }
                            }
                        }
                    }
                }
            });
        }

        // ========================================
        // GRÁFICO 7: TENDENCIA MENSUAL - ÚLTIMOS 6 MESES (Líneas)
        // ========================================
        const ctxTendenciaMensual = document.getElementById('chartTendenciaMensual');
        if (ctxTendenciaMensual) {
            const tendenciaClientes = <?php echo json_encode($stats['tendencia_clientes']); ?>;
            const tendenciaPedidos = <?php echo json_encode($stats['tendencia_pedidos']); ?>;
            const tendenciaEmbarques = <?php echo json_encode($stats['tendencia_embarques']); ?>;
            const tendenciaFacturacion = <?php echo json_encode($stats['tendencia_facturacion']); ?>;

            // Crear array de meses únicos
            const mesesSet = new Set();
            tendenciaClientes.forEach(item => mesesSet.add(item.mes_nombre));
            tendenciaPedidos.forEach(item => mesesSet.add(item.mes_nombre));
            tendenciaEmbarques.forEach(item => mesesSet.add(item.mes_nombre));
            tendenciaFacturacion.forEach(item => mesesSet.add(item.mes_nombre));

            const meses = Array.from(mesesSet);

            // Crear función helper para obtener valor por mes
            function getValorPorMes(array, mes) {
                const item = array.find(i => i.mes_nombre === mes);
                return item ? parseFloat(item.total) : 0;
            }

            new Chart(ctxTendenciaMensual, {
                type: 'line',
                data: {
                    labels: meses,
                    datasets: [
                        {
                            label: 'Clientes',
                            data: meses.map(mes => getValorPorMes(tendenciaClientes, mes)),
                            borderColor: 'rgba(102, 126, 234, 1)',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Pedidos',
                            data: meses.map(mes => getValorPorMes(tendenciaPedidos, mes)),
                            borderColor: 'rgba(17, 153, 142, 1)',
                            backgroundColor: 'rgba(17, 153, 142, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Embarques',
                            data: meses.map(mes => getValorPorMes(tendenciaEmbarques, mes)),
                            borderColor: 'rgba(245, 87, 108, 1)',
                            backgroundColor: 'rgba(245, 87, 108, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // ========================================
        // GRÁFICO 8: EMBARQUES MENSUALES POR USUARIO (Barras Horizontales)
        // ========================================
        const ctxEmbarquesPorUsuario = document.getElementById('chartEmbarquesPorUsuario');
        if (ctxEmbarquesPorUsuario) {
            const embarquesPorUsuario = <?php echo json_encode($embarques_por_usuario); ?>;
            const nombresUsuarios = embarquesPorUsuario.map(e => e.nombre_completo);
            const embarquesMes = embarquesPorUsuario.map(e => parseInt(e.mes));

            new Chart(ctxEmbarquesPorUsuario, {
                type: 'bar',
                data: {
                    labels: nombresUsuarios,
                    datasets: [{
                        label: 'Embarques (Últimos 30 Días)',
                        data: embarquesMes,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Embarques: ' + context.parsed.x;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // ========================================
        // GRÁFICO 9: FACTURACIÓN MENSUAL POR USUARIO (Barras Horizontales)
        // ========================================
        const ctxFacturacionPorUsuario = document.getElementById('chartFacturacionPorUsuario');
        if (ctxFacturacionPorUsuario) {
            const facturacionPorUsuario = <?php echo json_encode($facturacion_por_usuario); ?>;
            const nombresUsuariosFacturacion = facturacionPorUsuario.map(f => f.nombre_completo);
            const facturacionMes = facturacionPorUsuario.map(f => parseFloat(f.mes));

            new Chart(ctxFacturacionPorUsuario, {
                type: 'bar',
                data: {
                    labels: nombresUsuariosFacturacion,
                    datasets: [{
                        label: 'Facturación (Últimos 30 Días)',
                        data: facturacionMes,
                        backgroundColor: 'rgba(17, 153, 142, 0.8)',
                        borderColor: 'rgba(17, 153, 142, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Facturado: $' + context.parsed.x.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(0);
                                }
                            }
                        }
                    }
                }
            });
        }

        // ========================================
        // GRÁFICO 10: EVOLUCIÓN DIARIA DE FACTURACIÓN (Líneas)
        // ========================================
        const ctxEvolucionFacturacion = document.getElementById('chartEvolucionFacturacion');
        if (ctxEvolucionFacturacion) {
            const evolucionFacturacion = <?php echo json_encode($evolucion_facturacion); ?>;
            const fechas = evolucionFacturacion.map(e => e.fecha_formato);
            const montos = evolucionFacturacion.map(e => parseFloat(e.monto_total));

            new Chart(ctxEvolucionFacturacion, {
                type: 'line',
                data: {
                    labels: fechas,
                    datasets: [{
                        label: 'Facturación Diaria',
                        data: montos,
                        borderColor: 'rgba(79, 172, 254, 1)',
                        backgroundColor: 'rgba(79, 172, 254, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: 'rgba(79, 172, 254, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Facturado: $' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(0);
                                }
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>
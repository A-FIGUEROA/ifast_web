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

                <!-- ===================================== -->
                <!-- GRÁFICOS DE EMBARQUES POR USUARIO -->
                <!-- ===================================== -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">

                    <!-- Embarques Diarios -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📦 Embarques Diarios</h3>
                        </div>
                        <div style="padding: 20px;">
                            <canvas id="chartEmbarquesDiarios" height="300"></canvas>
                        </div>
                    </div>

                    <!-- Embarques Semanales -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📦 Embarques Semanales</h3>
                        </div>
                        <div style="padding: 20px;">
                            <canvas id="chartEmbarquesSemanales" height="300"></canvas>
                        </div>
                    </div>

                    <!-- Embarques Mensuales -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📦 Embarques Mensuales</h3>
                        </div>
                        <div style="padding: 20px;">
                            <canvas id="chartEmbarquesMensuales" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- ===================================== -->
                <!-- GRÁFICOS DE FACTURACIÓN POR USUARIO -->
                <!-- ===================================== -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">

                    <!-- Facturación Diaria -->
                    <div class="card">
                        <div class="card-header">
                            <h3>💰 Facturación Diaria</h3>
                        </div>
                        <div style="padding: 20px;">
                            <canvas id="chartFacturacionDiaria" height="300"></canvas>
                        </div>
                    </div>

                    <!-- Facturación Semanal -->
                    <div class="card">
                        <div class="card-header">
                            <h3>💰 Facturación Semanal</h3>
                        </div>
                        <div style="padding: 20px;">
                            <canvas id="chartFacturacionSemanal" height="300"></canvas>
                        </div>
                    </div>

                    <!-- Facturación Mensual -->
                    <div class="card">
                        <div class="card-header">
                            <h3>💰 Facturación Mensual</h3>
                        </div>
                        <div style="padding: 20px;">
                            <canvas id="chartFacturacionMensual" height="300"></canvas>
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
        const embarquesPorUsuario = <?php echo json_encode($embarques_por_usuario); ?>;
        const facturacionPorUsuario = <?php echo json_encode($facturacion_por_usuario); ?>;

        console.log('Embarques por usuario:', embarquesPorUsuario);
        console.log('Facturación por usuario:', facturacionPorUsuario);

        // Verificar si hay datos
        if (!embarquesPorUsuario || embarquesPorUsuario.length === 0) {
            console.error('No hay datos de embarques por usuario');
        }
        if (!facturacionPorUsuario || facturacionPorUsuario.length === 0) {
            console.error('No hay datos de facturación por usuario');
        }

        const nombresUsuarios = embarquesPorUsuario && embarquesPorUsuario.length > 0 ? embarquesPorUsuario.map(e => e.nombre_completo) : [];
        const coloresBarras = [
            'rgba(102, 126, 234, 0.8)',
            'rgba(17, 153, 142, 0.8)',
            'rgba(245, 87, 108, 0.8)',
            'rgba(79, 172, 254, 0.8)',
            'rgba(240, 147, 251, 0.8)',
            'rgba(56, 239, 125, 0.8)'
        ];

        // ========================================
        // GRÁFICO 1: EMBARQUES DIARIOS POR USUARIO
        // ========================================
        const ctxEmbarquesDiarios = document.getElementById('chartEmbarquesDiarios');
        if (ctxEmbarquesDiarios) {
            const embarquesDiarios = embarquesPorUsuario.map(e => parseInt(e.hoy));

            new Chart(ctxEmbarquesDiarios, {
                type: 'bar',
                data: {
                    labels: nombresUsuarios,
                    datasets: [{
                        label: 'Embarques Hoy',
                        data: embarquesDiarios,
                        backgroundColor: coloresBarras,
                        borderWidth: 0
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
                                    return 'Embarques: ' + context.parsed.y;
                                }
                            }
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
        // GRÁFICO 2: EMBARQUES SEMANALES POR USUARIO
        // ========================================
        const ctxEmbarquesSemanales = document.getElementById('chartEmbarquesSemanales');
        if (ctxEmbarquesSemanales) {
            const embarquesSemanales = embarquesPorUsuario.map(e => parseInt(e.semana));

            new Chart(ctxEmbarquesSemanales, {
                type: 'bar',
                data: {
                    labels: nombresUsuarios,
                    datasets: [{
                        label: 'Embarques (7 días)',
                        data: embarquesSemanales,
                        backgroundColor: coloresBarras,
                        borderWidth: 0
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
                                    return 'Embarques: ' + context.parsed.y;
                                }
                            }
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
        // GRÁFICO 3: EMBARQUES MENSUALES POR USUARIO
        // ========================================
        const ctxEmbarquesMensuales = document.getElementById('chartEmbarquesMensuales');
        if (ctxEmbarquesMensuales) {
            const embarquesMensuales = embarquesPorUsuario.map(e => parseInt(e.mes));

            new Chart(ctxEmbarquesMensuales, {
                type: 'bar',
                data: {
                    labels: nombresUsuarios,
                    datasets: [{
                        label: 'Embarques (30 días)',
                        data: embarquesMensuales,
                        backgroundColor: coloresBarras,
                        borderWidth: 0
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
                                    return 'Embarques: ' + context.parsed.y;
                                }
                            }
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
        // GRÁFICO 4: FACTURACIÓN DIARIA POR USUARIO
        // ========================================
        const ctxFacturacionDiaria = document.getElementById('chartFacturacionDiaria');
        if (ctxFacturacionDiaria) {
            const facturacionDiaria = facturacionPorUsuario.map(f => parseFloat(f.hoy));

            new Chart(ctxFacturacionDiaria, {
                type: 'bar',
                data: {
                    labels: nombresUsuarios,
                    datasets: [{
                        label: 'Facturación Hoy',
                        data: facturacionDiaria,
                        backgroundColor: coloresBarras,
                        borderWidth: 0
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
                        }
                    }
                }
            });
        }

        // ========================================
        // GRÁFICO 5: FACTURACIÓN SEMANAL POR USUARIO
        // ========================================
        const ctxFacturacionSemanal = document.getElementById('chartFacturacionSemanal');
        if (ctxFacturacionSemanal) {
            const facturacionSemanal = facturacionPorUsuario.map(f => parseFloat(f.semana));

            new Chart(ctxFacturacionSemanal, {
                type: 'bar',
                data: {
                    labels: nombresUsuarios,
                    datasets: [{
                        label: 'Facturación (7 días)',
                        data: facturacionSemanal,
                        backgroundColor: coloresBarras,
                        borderWidth: 0
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
                        }
                    }
                }
            });
        }

        // ========================================
        // GRÁFICO 6: FACTURACIÓN MENSUAL POR USUARIO
        // ========================================
        const ctxFacturacionMensual = document.getElementById('chartFacturacionMensual');
        if (ctxFacturacionMensual) {
            const facturacionMensual = facturacionPorUsuario.map(f => parseFloat(f.mes));

            new Chart(ctxFacturacionMensual, {
                type: 'bar',
                data: {
                    labels: nombresUsuarios,
                    datasets: [{
                        label: 'Facturación (30 días)',
                        data: facturacionMensual,
                        backgroundColor: coloresBarras,
                        borderWidth: 0
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
                        }
                    }
                }
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>

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

// Obtener estad铆sticas
$stats = obtenerEstadisticas($conn);

// Obtener tipo de usuario
$tipo_usuario = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();
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
                            <box-icon name='file-doc' type='solid' flip='horizontal' ></box-icon>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_archivos']; ?></h3>
                            <p>Total Archivos</p>
                        </div>
                    </div>
                </div>

                <!-- RECENT ORDERS -->
                <div class="card">
                    <div class="card-header">
                        <h3> Pedidos Recientes</h3>
                    </div>
                    <div class="table-container">
                        <?php if (!empty($stats['pedidos_recientes'])): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Tracking</th>
                                    <th>Cliente</th>
                                    <th>Archivo</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['pedidos_recientes'] as $pedido): ?>
                                <tr>
                                    <td><strong><?php echo $pedido['tracking_pedido']; ?></strong></td>
                                    <td>
                                        <?php 
                                        echo $pedido['nombre_razon_social'];
                                        if ($pedido['apellido']) {
                                            echo ' ' . $pedido['apellido'];
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $pedido['nombre_archivo']; ?></td>
                                    <td><?php echo formatearFecha($pedido['subido_en']); ?></td>
                                    <td><span class="badge badge-success">Activo</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <i>--</i>
                            <p>No hay pedidos registrados aunn</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    

 <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</body>
</html>
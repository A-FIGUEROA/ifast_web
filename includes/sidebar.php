<?php
// Obtener tipo de usuario (si no está definido ya)
if (!isset($tipo_usuario)) {
    require_once __DIR__ . '/auth.php';
    $tipo_usuario = obtenerTipoUsuario();
}

// Detectar la página actual para marcar el item activo
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Determinar qué item debe estar activo
$is_dashboard = ($current_page === 'dashboard.php');
$is_usuarios = ($current_dir === 'usuarios');
$is_clientes = ($current_dir === 'clientes');
$is_pedidos = ($current_dir === 'pedidos');
$is_embarques = ($current_dir === 'embarques');
$is_guias = ($current_dir === 'guias');
$is_reportes = ($current_dir === 'reportes');

// Determinar la ruta base según desde dónde se incluye
$base_path = ($current_page === 'dashboard.php') ? '' : '../../';
?>
<style>
    /* SIDEBAR */
    .sidebar {
        width: 260px;
        background: linear-gradient(180deg, #00296B 0%, #00509D 100%);
        color: white;
        padding: 0;
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        transition: all 0.3s;
    }

    .sidebar-header {
        padding: 25px 20px;
        background: rgba(0,0,0,0.2);
        border-bottom: 1px solid rgba(255,255,255,0.1);
        text-align: center;
    }

    .sidebar-header img {
        width: 150px;
        height: 50px;
    }

    .sidebar-menu {
        padding: 20px 0;
    }

    .menu-section {
        margin-bottom: 30px;
    }

    .menu-section-title {
        padding: 0 20px;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.6;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .menu-item {
        padding: 12px 20px;
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        transition: all 0.3s;
        border-left: 3px solid transparent;
    }

    .menu-item:hover {
        background: rgba(255,255,255,0.1);
        border-left-color: #FDC500;
    }

    .menu-item.active {
        background: rgba(253, 197, 0, 0.2);
        border-left-color: #FDC500;
    }

    .menu-item i {
        font-size: 1.2rem;
        margin-right: 12px;
        width: 32px;
        text-align: center;
    }

    .menu-item h3 {
        font-size: 1rem;
        font-weight: 500;
        margin: 0;
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 0;
            transform: translateX(-100%);
        }
    }
</style>

<div class="container">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="<?php echo $base_path; ?>assets/logo/logo.png" alt="Logo">
        </div>

        <nav class="sidebar-menu">
            <!-- SECCIÓN PRINCIPAL -->
            <div class="menu-section">
                <div class="menu-section-title">Principal</div>
                <a href="<?php echo $base_path; ?>dashboard.php" class="menu-item <?php echo $is_dashboard ? 'active' : ''; ?>">
                    <i><box-icon type='solid' name='bar-chart-alt-2' color='#FDC500' size='32px'></box-icon></i>
                    <span><h3>Dashboard</h3></span>
                </a>
            </div>

            <!-- SECCIÓN ADMINISTRACIÓN (Solo ADMIN) -->
            <?php if ($tipo_usuario === 'ADMIN'): ?>
            <div class="menu-section">
                <div class="menu-section-title">Administración</div>
                <a href="<?php echo $base_path; ?>modules/usuarios/index.php" class="menu-item <?php echo $is_usuarios ? 'active' : ''; ?>">
                    <i><box-icon name='user-plus' color='#FDC500' size='32px'></box-icon></i>
                    <span><h3>Usuarios</h3></span>
                </a>
            </div>
            <?php endif; ?>

            <!-- SECCIÓN GESTIÓN -->
            <div class="menu-section">
                <div class="menu-section-title">Gestión</div>
                <a href="<?php echo $base_path; ?>modules/clientes/index.php" class="menu-item <?php echo $is_clientes ? 'active' : ''; ?>">
                    <i><box-icon type='solid' name='user-detail' color='#FDC500' size='32px'></box-icon></i>
                    <span><h3>Clientes</h3></span>
                </a>
                <a href="<?php echo $base_path; ?>modules/pedidos/index.php" class="menu-item <?php echo $is_pedidos ? 'active' : ''; ?>">
                    <i><box-icon name='package' color='#FDC500' size='32px'></box-icon></i>
                    <span><h3>Pedidos</h3></span>
                </a>
                <a href="<?php echo $base_path; ?>modules/embarques/index.php" class="menu-item <?php echo $is_embarques ? 'active' : ''; ?>">
                    <i><box-icon type='solid' name='plane-alt' color='#FDC500' size='32px'></box-icon></i>
                    <span><h3>Embarques</h3></span>
                </a>
                <a href="<?php echo $base_path; ?>modules/guias/index.php" class="menu-item <?php echo $is_guias ? 'active' : ''; ?>">
                    <i><box-icon name='package' type='solid' color='#FDC500' size='32px'></box-icon></i>
                    <span><h3>Guías</h3></span>
                </a>
                <a href="<?php echo $base_path; ?>modules/facturacion/index.php" class="menu-item <?php echo ($current_dir === 'facturacion') ? 'active' : ''; ?>">
                    <i><box-icon type='solid' name='receipt' color='#FDC500' size='32px'></box-icon></i>
                    <span><h3>Facturación</h3></span>
                </a>
            </div>

            <!-- SECCIÓN REPORTES (SOLO ADMIN) -->
            <?php if ($tipo_usuario === 'ADMIN'): ?>
            <div class="menu-section">
                <div class="menu-section-title">Reportes</div>
                <a href="<?php echo $base_path; ?>modules/reportes/exportar_clientes.php" class="menu-item">
                    <i><box-icon type='solid' name='file-export' color='#FDC500' size='32px'></box-icon></i>
                    <span><h3>Exportar Clientes</h3></span>
                </a>
                <a href="<?php echo $base_path; ?>modules/reportes/exportar_pedidos.php" class="menu-item">
                    <i><box-icon type='solid' name='file-export' color='#FDC500' size='32px'></box-icon></i>
                    <span><h3>Exportar Pedidos</h3></span>
                </a>
            </div>
            <?php endif; ?>
        </nav>
    </aside>

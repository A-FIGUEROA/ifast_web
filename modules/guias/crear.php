<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Solo ADMIN y SUPERVISOR pueden crear
requierePermiso(['ADMIN', 'SUPERVISOR']);

$database = new Database();
$conn = $database->getConnection();
$tipo_usuario = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

// Obtener lista de clientes
$stmt_clientes = $conn->query("SELECT id, tipo_documento, documento, nombre_razon_social, apellido FROM clientes ORDER BY nombre_razon_social ASC");
$clientes = $stmt_clientes->fetchAll();

$errores = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y limpiar datos
    $nro_guia = limpiarDatos($_POST['nro_guia']);
    $consignatario = limpiarDatos($_POST['consignatario']);
    $cliente = limpiarDatos($_POST['cliente'] ?? '');
    $documento_cliente = limpiarDatos($_POST['documento_cliente'] ?? '');
    $descripcion = limpiarDatos($_POST['descripcion'] ?? '');
    $pcs = (int)($_POST['pcs'] ?? 0);
    $peso_kg = (float)($_POST['peso_kg'] ?? 0);
    $valor_fob_usd = (float)($_POST['valor_fob_usd'] ?? 0);
    $fecha_embarque = limpiarDatos($_POST['fecha_embarque'] ?? '');
    $asesor = limpiarDatos($_POST['asesor'] ?? '');
    $estado = limpiarDatos($_POST['estado']);
    $cliente_id = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;

    // Validaciones
    if (empty($nro_guia)) {
        $errores[] = "El número de guía es obligatorio";
    } else {
        // Verificar si ya existe
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM guias_masivas WHERE nro_guia = :nro_guia");
        $stmt->bindParam(':nro_guia', $nro_guia);
        $stmt->execute();
        if ($stmt->fetch()['total'] > 0) {
            $errores[] = "El número de guía ya existe";
        }
    }

    if (empty($consignatario)) {
        $errores[] = "El consignatario es obligatorio";
    }

    if ($pcs < 0) {
        $errores[] = "Las piezas no pueden ser negativas";
    }

    if ($peso_kg < 0) {
        $errores[] = "El peso no puede ser negativo";
    }

    if ($valor_fob_usd < 0) {
        $errores[] = "El valor FOB no puede ser negativo";
    }

    // Si no hay errores, insertar
    if (count($errores) === 0) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO guias_masivas
                (nro_guia, consignatario, cliente, documento_cliente, descripcion, pcs, peso_kg, valor_fob_usd, fecha_embarque, asesor, estado, cliente_id, metodo_ingreso, creado_por)
                VALUES
                (:nro_guia, :consignatario, :cliente, :documento_cliente, :descripcion, :pcs, :peso_kg, :valor_fob_usd, :fecha_embarque, :asesor, :estado, :cliente_id, 'MANUAL', :creado_por)
            ");

            $stmt->bindParam(':nro_guia', $nro_guia);
            $stmt->bindParam(':consignatario', $consignatario);
            $stmt->bindParam(':cliente', $cliente);
            $stmt->bindParam(':documento_cliente', $documento_cliente);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':pcs', $pcs);
            $stmt->bindParam(':peso_kg', $peso_kg);
            $stmt->bindParam(':valor_fob_usd', $valor_fob_usd);
            $stmt->bindParam(':fecha_embarque', $fecha_embarque);
            $stmt->bindParam(':asesor', $asesor);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':cliente_id', $cliente_id);
            $stmt->bindParam(':creado_por', $_SESSION['usuario_id']);

            if ($stmt->execute()) {
                header("Location: index.php?success=creado");
                exit();
            }
        } catch (PDOException $e) {
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Guía - Manual</title>
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
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .card-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f5f7fa;
        }

        .card-title {
            font-size: 1.5rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #00509D;
            box-shadow: 0 0 0 4px rgba(0, 80, 157, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00296B 0%, #00509D 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 41, 107, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .required {
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .content {
                padding: 15px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="header">
            <div class="header-left">
                <h1>Agregar Guía</h1>
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
                        <i class='bx bx-plus-circle'></i>
                        Agregar Guía Manual
                    </h2>
                </div>

                <?php if (count($errores) > 0): ?>
                    <div class="alert alert-danger">
                        <strong>Errores:</strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>N° Guía <span class="required">*</span></label>
                        <input
                            type="text"
                            name="nro_guia"
                            class="form-control"
                            placeholder="Ej: HAWBO0086411"
                            value="<?php echo isset($_POST['nro_guia']) ? htmlspecialchars($_POST['nro_guia']) : ''; ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Consignatario <span class="required">*</span></label>
                        <input
                            type="text"
                            name="consignatario"
                            class="form-control"
                            placeholder="Nombre completo del consignatario"
                            value="<?php echo isset($_POST['consignatario']) ? htmlspecialchars($_POST['consignatario']) : ''; ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Cliente</label>
                        <input
                            type="text"
                            name="cliente"
                            class="form-control"
                            placeholder="Nombre del cliente"
                            value="<?php echo isset($_POST['cliente']) ? htmlspecialchars($_POST['cliente']) : ''; ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>RUC/DNI del Cliente</label>
                        <input
                            type="text"
                            name="documento_cliente"
                            class="form-control"
                            placeholder="Ej: 20123456789"
                            maxlength="20"
                            value="<?php echo isset($_POST['documento_cliente']) ? htmlspecialchars($_POST['documento_cliente']) : ''; ?>"
                        >
                        <small style="color: #666; font-size: 0.85rem; display: block; margin-top: 5px;">
                            Documento para búsqueda automática de cliente en base de datos
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Cliente BD (Opcional)</label>
                        <select name="cliente_id" class="form-control">
                            <option value="">-- Sin cliente asignado --</option>
                            <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>"
                                    <?php echo (isset($_POST['cliente_id']) && $_POST['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php
                                echo htmlspecialchars($cliente['nombre_razon_social']);
                                if ($cliente['apellido']) echo ' ' . htmlspecialchars($cliente['apellido']);
                                echo ' - ' . $cliente['tipo_documento'] . ': ' . $cliente['documento'];
                                ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666; font-size: 0.85rem; display: block; margin-top: 5px;">
                            Asociar esta guía a un cliente permitirá usarla para facturación automática
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea
                            name="descripcion"
                            class="form-control"
                            placeholder="Descripción del contenido"
                        ><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Piezas (PCS)</label>
                            <input
                                type="number"
                                name="pcs"
                                class="form-control"
                                min="0"
                                step="1"
                                value="<?php echo isset($_POST['pcs']) ? $_POST['pcs'] : '0'; ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label>Peso (kg)</label>
                            <input
                                type="number"
                                name="peso_kg"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?php echo isset($_POST['peso_kg']) ? $_POST['peso_kg'] : '0'; ?>"
                            >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Valor FOB (USD)</label>
                            <input
                                type="number"
                                name="valor_fob_usd"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?php echo isset($_POST['valor_fob_usd']) ? $_POST['valor_fob_usd'] : '0'; ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label>Fecha de Embarque</label>
                            <input
                                type="date"
                                name="fecha_embarque"
                                class="form-control"
                                value="<?php echo isset($_POST['fecha_embarque']) ? $_POST['fecha_embarque'] : ''; ?>"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Asesor</label>
                        <input
                            type="text"
                            name="asesor"
                            class="form-control"
                            placeholder="Nombre del asesor"
                            value="<?php echo isset($_POST['asesor']) ? htmlspecialchars($_POST['asesor']) : ''; ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Estado <span class="required">*</span></label>
                        <select name="estado" class="form-control" required>
                            <option value="PENDIENTE" <?php echo (!isset($_POST['estado']) || $_POST['estado'] === 'PENDIENTE') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="ENTREGADO" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'ENTREGADO') ? 'selected' : ''; ?>>Entregado</option>
                            <option value="OBSERVADO" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'OBSERVADO') ? 'selected' : ''; ?>>Observado</option>
                            <option value="LIQUIDADO" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'LIQUIDADO') ? 'selected' : ''; ?>>Liquidado</option>
                        </select>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-save'></i>
                            Guardar Guía
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class='bx bx-x'></i>
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</body>
</html>

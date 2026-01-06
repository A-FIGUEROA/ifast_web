<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN']);

$database = new Database();
$conn = $database->getConnection();
$tipo_usuario = obtenerTipoUsuario();
$nombre_usuario = obtenerNombreUsuario();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    header("Location: index.php");
    exit();
}

// Obtener embarque
$stmt = $conn->prepare("SELECT * FROM guias_embarque WHERE id_guia = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$guia = $stmt->fetch();

if (!$guia) {
    header("Location: index.php");
    exit();
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proveedor = limpiarDatos($_POST['proveedor'] ?? '');
    $contenido = limpiarDatos($_POST['contenido'] ?? '');
    $valor_usd = (float)($_POST['valor_usd'] ?? 0);
    $indicaciones = limpiarDatos($_POST['indicaciones'] ?? '');
    $estado = limpiarDatos($_POST['estado']);

    if ($valor_usd < 0) {
        $errores[] = "El valor USD no puede ser negativo";
    }

    if (count($errores) === 0) {
        try {
            $stmt = $conn->prepare("
                UPDATE guias_embarque
                SET proveedor = :proveedor,
                    contenido = :contenido,
                    valor_usd = :valor_usd,
                    indicaciones = :indicaciones,
                    estado = :estado
                WHERE id_guia = :id
            ");

            $stmt->bindParam(':proveedor', $proveedor);
            $stmt->bindParam(':contenido', $contenido);
            $stmt->bindParam(':valor_usd', $valor_usd);
            $stmt->bindParam(':indicaciones', $indicaciones);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':id', $id);

            if ($stmt->execute()) {
                header("Location: ver.php?id=" . $id);
                exit();
            }
        } catch (PDOException $e) {
            $errores[] = "Error al actualizar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Embarque</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 260px; }
        .header { background: white; padding: 20px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header h1 { font-size: 1.8rem; color: #2c3e50; font-weight: 600; }
        .content { padding: 30px; }
        .card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); max-width: 900px; margin: 0 auto; }
        .card-title { font-size: 1.5rem; color: #2c3e50; font-weight: 600; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #ecf0f1; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-row.full { grid-template-columns: 1fr; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; color: #2c3e50; margin-bottom: 8px; font-size: 0.9rem; }
        .form-group input, .form-group select, .form-group textarea { padding: 12px; border: 2px solid #ecf0f1; border-radius: 8px; font-size: 0.9rem; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #00509d; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .btn-group { display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; }
        .btn { padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.95rem; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: linear-gradient(135deg, #00296b 0%, #00509d 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 80, 157, 0.3); }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }
        .info-section { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .info-section label { font-weight: 600; color: #7f8c8d; font-size: 0.8rem; display: block; margin-bottom: 5px; }
        .info-section p { color: #2c3e50; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1>✏️ Editar Embarque</h1>
            </div>

            <div class="content">
                <div class="card">
                    <h2 class="card-title">Editar Embarque - <?php echo htmlspecialchars($guia['nro_guia']); ?></h2>

                    <?php if (count($errores) > 0): ?>
                    <div class="alert">
                        <strong>⚠️ Errores:</strong>
                        <ul><?php foreach ($errores as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
                    </div>
                    <?php endif; ?>

                    <div class="info-section">
                        <div class="form-row">
                            <div>
                                <label>Cliente</label>
                                <p><?php echo htmlspecialchars($guia['nombre_completo']); ?></p>
                            </div>
                            <div>
                                <label>Documento</label>
                                <p><?php echo htmlspecialchars($guia['tipo_documento']); ?>: <?php echo htmlspecialchars($guia['documento']); ?></p>
                            </div>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Proveedor(es)</label>
                                <input type="text" name="proveedor" value="<?php echo htmlspecialchars($guia['proveedor']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Valor USD *</label>
                                <input type="number" name="valor_usd" step="0.01" min="0" value="<?php echo htmlspecialchars($guia['valor_usd']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row full">
                            <div class="form-group">
                                <label>Contenido</label>
                                <textarea name="contenido"><?php echo htmlspecialchars($guia['contenido']); ?></textarea>
                            </div>
                        </div>

                        <div class="form-row full">
                            <div class="form-group">
                                <label>Indicaciones</label>
                                <textarea name="indicaciones"><?php echo htmlspecialchars($guia['indicaciones']); ?></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Estado *</label>
                                <select name="estado" required>
                                    <option value="ACTIVO" <?php echo $guia['estado'] === 'ACTIVO' ? 'selected' : ''; ?>>ACTIVO</option>
                                    <option value="INACTIVO" <?php echo $guia['estado'] === 'INACTIVO' ? 'selected' : ''; ?>>INACTIVO</option>
                                </select>
                            </div>
                        </div>

                        <div class="btn-group">
                            <a href="ver.php?id=<?php echo $id; ?>" class="btn btn-secondary">← Cancelar</a>
                            <button type="submit" class="btn btn-primary">💾 Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

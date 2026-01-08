<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();
$tipo_usuario = obtenerTipoUsuario();

$cliente_id = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;

// Obtener información del cliente
$stmt = $conn->prepare("SELECT * FROM clientes WHERE id = :id");
$stmt->bindParam(':id', $cliente_id);
$stmt->execute();
$cliente = $stmt->fetch();

if (!$cliente) {
    header("Location: index.php?error=Cliente no encontrado");
    exit();
}

$errores = [];
$success = '';

// SUBIR ARCHIVO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_archivo'])) {
    if ($tipo_usuario === 'SUPERVISOR') {
        $errores[] = "No tienes permiso para subir archivos";
    } else {
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $carpeta = "../../uploads/clientes/{$cliente_id}";
            $resultado = subirArchivo($_FILES['archivo'], $carpeta, ['pdf', 'xlsx', 'xls']);
            
            if ($resultado['exito']) {
                // Guardar en base de datos
                $tipo_archivo = obtenerTipoArchivo($resultado['nombre_archivo']);

                $stmt = $conn->prepare("
                    INSERT INTO archivos_clientes (cliente_id, nombre_archivo, nombre_original, tipo_archivo, ruta)
                    VALUES (:cliente_id, :nombre_archivo, :nombre_original, :tipo_archivo, :ruta)
                ");

                $stmt->bindParam(':cliente_id', $cliente_id);
                $stmt->bindParam(':nombre_archivo', $resultado['nombre_archivo']);
                $stmt->bindParam(':nombre_original', $resultado['nombre_original']);
                $stmt->bindParam(':tipo_archivo', $tipo_archivo);
                $stmt->bindParam(':ruta', $resultado['ruta']);
                
                if ($stmt->execute()) {
                    $success = "Archivo subido exitosamente";
                } else {
                    $errores[] = "Error al guardar en la base de datos";
                }
            } else {
                $errores[] = $resultado['mensaje'];
            }
        } else {
            $errores[] = "No se seleccionó ningún archivo";
        }
    }
}

// ELIMINAR ARCHIVO
if (isset($_GET['eliminar_archivo'])) {
    if ($tipo_usuario === 'SUPERVISOR') {
        header("Location: archivos.php?cliente_id={$cliente_id}&error=No tienes permiso para eliminar");
        exit();
    }
    
    $archivo_id = (int)$_GET['eliminar_archivo'];
    
    // Obtener ruta del archivo
    $stmt = $conn->prepare("SELECT ruta FROM archivos_clientes WHERE id = :id AND cliente_id = :cliente_id");
    $stmt->bindParam(':id', $archivo_id);
    $stmt->bindParam(':cliente_id', $cliente_id);
    $stmt->execute();
    $archivo = $stmt->fetch();
    
    if ($archivo) {
        // Eliminar archivo físico
        if (file_exists($archivo['ruta'])) {
            unlink($archivo['ruta']);
        }
        
        // Eliminar de BD
        $stmt = $conn->prepare("DELETE FROM archivos_clientes WHERE id = :id");
        $stmt->bindParam(':id', $archivo_id);
        
        if ($stmt->execute()) {
            header("Location: archivos.php?cliente_id={$cliente_id}&success=Archivo eliminado");
            exit();
        }
    }
}

// Obtener archivos del cliente
$stmt = $conn->prepare("
    SELECT * FROM archivos_clientes 
    WHERE cliente_id = :cliente_id 
    ORDER BY subido_en DESC
");
$stmt->bindParam(':cliente_id', $cliente_id);
$stmt->execute();
$archivos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archivos del Cliente</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .cliente-info {
            background: linear-gradient(135deg, #00296b 0%, #00509d 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .cliente-info h2 {
            font-size: 1.3rem;
            margin-bottom: 10px;
        }

        .cliente-info p {
            margin: 5px 0;
            opacity: 0.9;
        }

        .upload-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .upload-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-input-label {
            padding: 12px 24px;
            background: #3498db;
            color: white;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s;
        }

        .file-input-label:hover {
            background: #2980b9;
        }

        .file-name {
            margin-left: 15px;
            color: #666;
            font-style: italic;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            margin-right: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00296b 0%, #00509d 100%);
            color: white;
        }

        .btn-back {
            background: linear-gradient(135deg, #8c0000 0%, #f00000 100%);;
            color: white;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 0.85rem;
        }

        .btn-download {
            background: #27ae60;
            color: white;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .file-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
        }

        .file-card:hover {
            border-color: #00509d;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .file-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            text-align: center;
        }

        .file-info {
            text-align: center;
        }

        .file-info h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 0.95rem;
            word-break: break-word;
        }

        .file-info p {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin: 5px 0;
        }

        .file-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            justify-content: center;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin: 5px 0;
        }

        .badge-pdf {
            background: #fee;
            color: #c33;
        }

        .badge-excel {
            background: #efe;
            color: #3c3;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Cliente Info -->
        <div class="cliente-info">
            <h2>📁 Archivos de: <?php echo $cliente['nombre_razon_social']; ?></h2>
            <p><strong>Documento:</strong> <?php echo $cliente['tipo_documento'] . ' - ' . $cliente['documento']; ?></p>
            <p><strong>Email:</strong> <?php echo $cliente['email']; ?></p>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1>📄 Gestión de Archivos</h1>
                <a href="index.php" class="btn btn-back">← Volver a Clientes</a>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <strong>⚠️ Errores:</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (in_array($tipo_usuario, ['ADMIN', 'SUPERVISOR', 'VENTAS'])): ?>
            <!-- Upload Section -->
            <div class="upload-section">
                <h3>📤 Subir Nuevo Archivo</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="file-input-wrapper">
                        <input type="file" name="archivo" id="archivo" accept=".pdf,.xlsx,.xls" required>
                        <label for="archivo" class="file-input-label">📎 Seleccionar Archivo</label>
                        <span class="file-name" id="fileName">PDF o Excel (máx. 5MB)</span>
                    </div>
                    <br>
                    <button type="submit" name="subir_archivo" class="btn btn-primary">⬆️ Subir Archivo</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Files Grid -->
            <h3 style="margin-bottom: 20px; color: #2c3e50;">📂 Archivos Subidos (<?php echo count($archivos); ?>)</h3>
            
            <?php if (count($archivos) > 0): ?>
            <div class="files-grid">
                <?php foreach ($archivos as $archivo): ?>
                <div class="file-card">
                    <div class="file-icon">
                        <?php
                        // Usar nombre original si existe, sino usar nombre_archivo
                        $nombre_mostrar = isset($archivo['nombre_original']) && !empty($archivo['nombre_original'])
                                         ? $archivo['nombre_original']
                                         : $archivo['nombre_archivo'];
                        $extension = strtolower(pathinfo($nombre_mostrar, PATHINFO_EXTENSION));
                        echo $extension === 'pdf' ? '📕' : '📗';
                        ?>
                    </div>
                    <div class="file-info">
                        <h4><?php echo htmlspecialchars($nombre_mostrar); ?></h4>
                        <span class="badge badge-<?php echo $extension === 'pdf' ? 'pdf' : 'excel'; ?>">
                            <?php echo strtoupper($extension); ?>
                        </span>
                        <p>Subido: <?php echo formatearFecha($archivo['subido_en']); ?></p>
                    </div>
                    <div class="file-actions">
                        <a href="descargar.php?id=<?php echo $archivo['id']; ?>"
                           class="btn btn-small btn-download" title="Descargar">
                           ⬇️
                        </a>
                        <?php if (in_array($tipo_usuario, ['ADMIN', 'SUPERVISOR', 'VENTAS'])): ?>
                        <a href="?cliente_id=<?php echo $cliente_id; ?>&eliminar_archivo=<?php echo $archivo['id']; ?>"
                           class="btn btn-small btn-delete" title="Eliminar"
                           onclick="return confirm('¿Eliminar este archivo?')">
                           🗑️
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i>📂</i>
                <h3>No hay archivos subidos</h3>
                <p>Comienza subiendo documentos del cliente</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mostrar nombre del archivo seleccionado
        document.getElementById('archivo').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'PDF o Excel (máx. 5MB)';
            document.getElementById('fileName').textContent = fileName;
        });
    </script>
</body>
</html>

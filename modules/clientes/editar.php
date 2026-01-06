<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN', 'VENTAS']);

$database = new Database();
$conn = $database->getConnection();

$errores = [];
$esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

// Obtener cliente
$stmt = $conn->prepare("SELECT * FROM clientes WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$cliente = $stmt->fetch();

if (!$cliente) {
    if ($esAjax || isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Cliente no encontrado'
        ]);
        exit();
    } else {
        header("Location: index.php?error=Cliente no encontrado");
        exit();
    }
}

// Si es petición AJAX GET (cargar datos), devolver JSON
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'cliente' => [
            'id' => $cliente['id'],
            'tipo_documento' => $cliente['tipo_documento'],
            'documento' => $cliente['documento'],
            'nombre_razon_social' => $cliente['nombre_razon_social'],
            'apellido' => $cliente['apellido'],
            'email' => $cliente['email'],
            'telif' => $cliente['telif'],
            'celular' => $cliente['celular'],
            'direccion' => $cliente['direccion'],
            'distrito' => $cliente['distrito'],
            'provincia' => $cliente['provincia'],
            'departamento' => $cliente['departamento']
        ]
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_documento = $_POST['tipo_documento'];
    $documento = limpiarDatos($_POST['documento']);
    $nombre_razon_social = limpiarDatos($_POST['nombre_razon_social']);
    $apellido = limpiarDatos($_POST['apellido']);
    $email = limpiarDatos($_POST['email']);
    $telif = limpiarDatos($_POST['telif']);
    $celular = limpiarDatos($_POST['celular']);
    $direccion = limpiarDatos($_POST['direccion']);
    $distrito = limpiarDatos($_POST['distrito']);
    $provincia = limpiarDatos($_POST['provincia']);
    $departamento = limpiarDatos($_POST['departamento']);

    // Validaciones
    if ($tipo_documento === 'DNI' && !validarDNI($documento)) {
        $errores[] = "El DNI debe tener 8 dígitos";
    }
    
    if ($tipo_documento === 'RUC' && !validarRUC($documento)) {
        $errores[] = "El RUC debe tener 11 dígitos";
    }
    
    if (!validarEmail($email)) {
        $errores[] = "El email no es válido";
    }

    if (empty($errores)) {
        // Verificar documento único
        $stmt = $conn->prepare("SELECT id FROM clientes WHERE documento = :documento AND id != :id");
        $stmt->bindParam(':documento', $documento);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $errores[] = "El documento ya está registrado por otro cliente";
        } else {
            $stmt = $conn->prepare("
                UPDATE clientes SET 
                    tipo_documento = :tipo_documento,
                    documento = :documento,
                    nombre_razon_social = :nombre_razon_social,
                    apellido = :apellido,
                    email = :email,
                    telif = :telif,
                    celular = :celular,
                    direccion = :direccion,
                    distrito = :distrito,
                    provincia = :provincia,
                    departamento = :departamento
                WHERE id = :id
            ");
            
            $stmt->bindParam(':tipo_documento', $tipo_documento);
            $stmt->bindParam(':documento', $documento);
            $stmt->bindParam(':nombre_razon_social', $nombre_razon_social);
            $stmt->bindParam(':apellido', $apellido);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':telif', $telif);
            $stmt->bindParam(':celular', $celular);
            $stmt->bindParam(':direccion', $direccion);
            $stmt->bindParam(':distrito', $distrito);
            $stmt->bindParam(':provincia', $provincia);
            $stmt->bindParam(':departamento', $departamento);
            $stmt->bindParam(':id', $id);

            if ($stmt->execute()) {
                // Respuesta para AJAX
                if ($esAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cliente actualizado exitosamente'
                    ]);
                    exit();
                } else {
                    header("Location: index.php?success=Cliente actualizado exitosamente");
                    exit();
                }
            } else {
                $errores[] = "Error al actualizar el cliente";
            }
        }
    }
}

// Si hay errores y es petición AJAX, responder con JSON
if ($esAjax && !empty($errores)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => implode('<br>', $errores)
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente</title>
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
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .card-header h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 30px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-back {
            background: #95a5a6;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .section-title {
            font-size: 1.2rem;
            color: #2c3e50;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>✏️ Editar Cliente</h1>
            </div>

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

            <form method="POST">
                <div class="section-title">📋 Información del Documento</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Documento *</label>
                        <select class="form-control" name="tipo_documento" required>
                            <option value="DNI" <?php echo $cliente['tipo_documento'] === 'DNI' ? 'selected' : ''; ?>>DNI</option>
                            <option value="RUC" <?php echo $cliente['tipo_documento'] === 'RUC' ? 'selected' : ''; ?>>RUC</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Número de Documento *</label>
                        <input type="text" class="form-control" name="documento" 
                               value="<?php echo htmlspecialchars($cliente['documento']); ?>" 
                               maxlength="11" required>
                    </div>
                </div>

                <div class="section-title">👤 Datos Personales / Empresa</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre / Razón Social *</label>
                        <input type="text" class="form-control" name="nombre_razon_social" 
                               value="<?php echo htmlspecialchars($cliente['nombre_razon_social']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Apellido</label>
                        <input type="text" class="form-control" name="apellido" 
                               value="<?php echo htmlspecialchars($cliente['apellido']); ?>">
                    </div>
                </div>

                <div class="section-title">📧 Información de Contacto</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($cliente['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Teléfono Fijo</label>
                        <input type="text" class="form-control" name="telif" 
                               value="<?php echo htmlspecialchars($cliente['telif']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Celular *</label>
                    <input type="text" class="form-control" name="celular" 
                           value="<?php echo htmlspecialchars($cliente['celular']); ?>" required>
                </div>

                <div class="section-title">📍 Dirección</div>

                <div class="form-group">
                    <label>Dirección Completa *</label>
                    <input type="text" class="form-control" name="direccion" 
                           value="<?php echo htmlspecialchars($cliente['direccion']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Distrito *</label>
                        <input type="text" class="form-control" name="distrito" 
                               value="<?php echo htmlspecialchars($cliente['distrito']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Provincia *</label>
                        <input type="text" class="form-control" name="provincia" 
                               value="<?php echo htmlspecialchars($cliente['provincia']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Departamento *</label>
                        <input type="text" class="form-control" name="departamento" 
                               value="<?php echo htmlspecialchars($cliente['departamento']); ?>" required>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">💾 Actualizar Cliente</button>
                    <a href="index.php" class="btn btn-back">← Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
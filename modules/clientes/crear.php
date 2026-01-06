<?php
// modules/clientes/crear.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Solo ADMIN y VENTAS pueden crear
requierePermiso(['ADMIN', 'VENTAS']);

$database = new Database();
$conn = $database->getConnection();

$errores = [];
$esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

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
    if (!in_array($tipo_documento, ['DNI', 'RUC'])) {
        $errores[] = "Tipo de documento inválido";
    }
    
    if ($tipo_documento === 'DNI' && !validarDNI($documento)) {
        $errores[] = "El DNI debe tener 8 dígitos";
    }
    
    if ($tipo_documento === 'RUC' && !validarRUC($documento)) {
        $errores[] = "El RUC debe tener 11 dígitos";
    }
    
    if (empty($nombre_razon_social)) {
        $errores[] = "El nombre/razón social es requerido";
    }
    
    if (!validarEmail($email)) {
        $errores[] = "El email no es válido";
    }
    
    if (empty($celular)) {
        $errores[] = "El celular es requerido";
    }
    
    if (empty($direccion)) {
        $errores[] = "La dirección es requerida";
    }
    
    if (empty($distrito) || empty($provincia) || empty($departamento)) {
        $errores[] = "Distrito, provincia y departamento son requeridos";
    }

    if (empty($errores)) {
        // Verificar si el documento ya existe
        $stmt = $conn->prepare("SELECT id FROM clientes WHERE documento = :documento");
        $stmt->bindParam(':documento', $documento);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $errores[] = "El documento ya está registrado";
        } else {
            try {
                // Obtener ID del usuario actual
                $usuario_id = $_SESSION['usuario_id'];

                // Insertar cliente
                $stmt = $conn->prepare("
                    INSERT INTO clientes (
                        tipo_documento, documento, nombre_razon_social, apellido,
                        email, telif, celular, direccion, distrito, provincia, departamento,
                        creado_por
                    ) VALUES (
                        :tipo_documento, :documento, :nombre_razon_social, :apellido,
                        :email, :telif, :celular, :direccion, :distrito, :provincia, :departamento,
                        :creado_por
                    )
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
                $stmt->bindParam(':creado_por', $usuario_id);

                if ($stmt->execute()) {
                    $cliente_id = $conn->lastInsertId();

                    // Respuesta para AJAX
                    if ($esAjax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'message' => 'Cliente creado exitosamente',
                            'cliente_id' => $cliente_id
                        ]);
                        exit();
                    } else {
                        header("Location: archivos.php?cliente_id={$cliente_id}&success=Cliente creado. Ahora puedes subir archivos");
                        exit();
                    }
                }
            } catch(PDOException $e) {
                $errores[] = "Error al crear el cliente: " . $e->getMessage();
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
    <title>Crear Cliente</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #00296B 0%, #00509D 50%);
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

        .card-header {
            margin-bottom: 30px;
        }

        .card-header h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 10px;
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

        .form-group label .required {
            color: #e74c3c;
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
            border-color: #FDC500;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        select.form-control {
            cursor: pointer;
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
            background: linear-gradient(135deg, #00296B 0%, #00509D 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-back {
            background: linear-gradient(135deg, #FDC500 0%, #FFD500 100%);
            color: white;
        }

        .btn-back:hover {
            background: #7f8c8d;
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

        .alert ul {
            margin-left: 20px;
            margin-top: 10px;
        }

        .section-title {
            font-size: 1.2rem;
            color: #2c3e50;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><box-icon name='user-plus' type='solid' color='#00296B' size='32px'></box-icon> Crear Nuevo Cliente</h1>
            </div>

            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <strong>⚠️ Errores encontrados:</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="info-box">
                ℹ️ Los campos marcados con <span style="color: #e74c3c;">*</span> son obligatorios
            </div>

            <form method="POST">
                <div class="section-title">📋 Información del Documento</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Documento <span class="required">*</span></label>
                        <select class="form-control" name="tipo_documento" id="tipo_documento" required>
                            <option value="DNI" <?php echo (isset($_POST['tipo_documento']) && $_POST['tipo_documento'] === 'DNI') ? 'selected' : ''; ?>>DNI</option>
                            <option value="RUC" <?php echo (isset($_POST['tipo_documento']) && $_POST['tipo_documento'] === 'RUC') ? 'selected' : ''; ?>>RUC</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Número de Documento <span class="required">*</span></label>
                        <input type="text" class="form-control" name="documento" 
                               value="<?php echo isset($_POST['documento']) ? htmlspecialchars($_POST['documento']) : ''; ?>" 
                               maxlength="11" required>
                        <small style="color: #666; font-size: 0.85rem;">DNI: 8 dígitos | RUC: 11 dígitos</small>
                    </div>
                </div>

                <div class="section-title">👤 Datos Personales / Empresa</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre / Razón Social <span class="required">*</span></label>
                        <input type="text" class="form-control" name="nombre_razon_social" 
                               value="<?php echo isset($_POST['nombre_razon_social']) ? htmlspecialchars($_POST['nombre_razon_social']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Apellido (opcional)</label>
                        <input type="text" class="form-control" name="apellido" 
                               value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : ''; ?>">
                    </div>
                </div>

                <div class="section-title">📧 Información de Contacto</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Teléfono Fijo (opcional)</label>
                        <input type="text" class="form-control" name="telif" 
                               value="<?php echo isset($_POST['telif']) ? htmlspecialchars($_POST['telif']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Celular <span class="required">*</span></label>
                    <input type="text" class="form-control" name="celular" 
                           value="<?php echo isset($_POST['celular']) ? htmlspecialchars($_POST['celular']) : ''; ?>" required>
                </div>

                <div class="section-title">📍 Dirección</div>

                <div class="form-group">
                    <label>Dirección Completa <span class="required">*</span></label>
                    <input type="text" class="form-control" name="direccion" 
                           value="<?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Distrito <span class="required">*</span></label>
                        <input type="text" class="form-control" name="distrito" 
                               value="<?php echo isset($_POST['distrito']) ? htmlspecialchars($_POST['distrito']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Provincia <span class="required">*</span></label>
                        <input type="text" class="form-control" name="provincia" 
                               value="<?php echo isset($_POST['provincia']) ? htmlspecialchars($_POST['provincia']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Departamento <span class="required">*</span></label>
                        <input type="text" class="form-control" name="departamento" 
                               value="<?php echo isset($_POST['departamento']) ? htmlspecialchars($_POST['departamento']) : ''; ?>" required>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">💾 Guardar Cliente</button>
                    <a href="index.php" class="btn btn-back">← Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Validar longitud del documento según tipo
        document.getElementById('tipo_documento').addEventListener('change', function() {
            const docInput = document.querySelector('input[name="documento"]');
            if (this.value === 'DNI') {
                docInput.maxLength = 8;
                docInput.placeholder = '12345678';
            } else {
                docInput.maxLength = 11;
                docInput.placeholder = '20123456789';
            }
        });

        // Solo permitir números en documento
        document.querySelector('input[name="documento"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
     <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</body>
</html>
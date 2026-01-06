<?php
// modules/usuarios/editar.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN']);

$database = new Database();
$conn = $database->getConnection();

$errores = [];
$esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

// Obtener usuario
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$usuario = $stmt->fetch();

if (!$usuario) {
    if ($esAjax || isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Usuario no encontrado'
        ]);
        exit();
    } else {
        header("Location: index.php?error=Usuario no encontrado");
        exit();
    }
}

// Si es petición AJAX GET (para cargar datos), devolver JSON
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'usuario' => [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'apellido' => $usuario['apellido'],
            'dni' => $usuario['dni'],
            'email' => $usuario['email'],
            'tipo' => $usuario['tipo']
        ]
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiarDatos($_POST['nombre']);
    $apellido = limpiarDatos($_POST['apellido']);
    $dni = limpiarDatos($_POST['dni']);
    $email = limpiarDatos($_POST['email']);
    $tipo = $_POST['tipo'];
    $password = $_POST['password'];

    // Validaciones
    if (empty($nombre)) $errores[] = "El nombre es requerido";
    if (empty($apellido)) $errores[] = "El apellido es requerido";
    if (!validarDNI($dni)) $errores[] = "El DNI debe tener 8 dígitos";
    if (!validarEmail($email)) $errores[] = "El email no es válido";
    if (!in_array($tipo, ['ADMIN', 'SUPERVISOR', 'VENTAS'])) $errores[] = "Tipo de usuario inválido";

    if (empty($errores)) {
        // Verificar si el email ya existe (excepto el actual)
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $errores[] = "El email ya está registrado por otro usuario";
        } else {
            // Actualizar usuario
            if (!empty($password)) {
                // Si se proporciona nueva contraseña
                $password_hash = hashPassword($password);
                $stmt = $conn->prepare("
                    UPDATE usuarios 
                    SET nombre = :nombre, apellido = :apellido, dni = :dni, 
                        email = :email, password = :password, tipo = :tipo 
                    WHERE id = :id
                ");
                $stmt->bindParam(':password', $password_hash);
            } else {
                // Sin cambiar contraseña
                $stmt = $conn->prepare("
                    UPDATE usuarios 
                    SET nombre = :nombre, apellido = :apellido, dni = :dni, 
                        email = :email, tipo = :tipo 
                    WHERE id = :id
                ");
            }
            
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':apellido', $apellido);
            $stmt->bindParam(':dni', $dni);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':id', $id);

            if ($stmt->execute()) {
                // Respuesta exitosa
                if ($esAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Usuario actualizado exitosamente'
                    ]);
                    exit();
                } else {
                    header("Location: index.php?success=Usuario actualizado exitosamente");
                    exit();
                }
            } else {
                $errores[] = "Error al actualizar el usuario";
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
    <title>Editar Usuario</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #00296B 0%, #00509D 100%);
            padding: 20px;
        }

        .container {
            max-width: 600px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
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
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>✏️ Editar Usuario</h1>
            </div>

            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="info-box">
                ℹ️ Deja el campo de contraseña vacío si no deseas cambiarla
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="nombre">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" 
                           value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : htmlspecialchars($usuario['nombre']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="apellido">Apellido *</label>
                    <input type="text" class="form-control" id="apellido" name="apellido" 
                           value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : htmlspecialchars($usuario['apellido']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="dni">DNI *</label>
                    <input type="text" class="form-control" id="dni" name="dni" maxlength="8" 
                           value="<?php echo isset($_POST['dni']) ? htmlspecialchars($_POST['dni']) : htmlspecialchars($usuario['dni']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($usuario['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Nueva Contraseña (opcional)</label>
                    <input type="password" class="form-control" id="password" name="password">
                    <small style="color: #666;">Dejar vacío para mantener la actual</small>
                </div>

                <div class="form-group">
                    <label for="tipo">Tipo de Usuario *</label>
                    <select class="form-control" id="tipo" name="tipo" required>
                        <option value="ADMIN" <?php echo $usuario['tipo'] === 'ADMIN' ? 'selected' : ''; ?>>Administrador</option>
                        <option value="SUPERVISOR" <?php echo $usuario['tipo'] === 'SUPERVISOR' ? 'selected' : ''; ?>>Supervisor</option>
                        <option value="VENTAS" <?php echo $usuario['tipo'] === 'VENTAS' ? 'selected' : ''; ?>>Ventas</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">💾 Actualizar Usuario</button>
                    <a href="index.php" class="btn btn-back">← Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

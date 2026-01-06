<?php
// login_process.php
// Procesa el formulario de login

// Evitar que se acceda directamente
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

// Incluir archivos necesarios
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Configurar respuesta JSON
header('Content-Type: application/json');

// Obtener datos del formulario
$email = isset($_POST['email']) ? limpiarDatos($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validar campos vacíos
if (empty($email) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Por favor completa todos los campos'
    ]);
    exit();
}

// Validar formato de email
if (!validarEmail($email)) {
    echo json_encode([
        'success' => false,
        'message' => 'El formato del email no es válido'
    ]);
    exit();
}

try {
    // Obtener conexión a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    // Intentar hacer login
    if (login($email, $password, $conn)) {
        echo json_encode([
            'success' => true,
            'message' => 'Login exitoso',
            'redirect' => 'dashboard.php'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Email o contraseña incorrectos'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor. Por favor intenta más tarde'
    ]);
}
?>
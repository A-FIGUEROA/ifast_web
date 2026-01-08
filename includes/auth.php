<?php
// includes/auth.php
// Control de autenticación y sesiones

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función para verificar si el usuario está logueado
function estaLogueado() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_tipo']);
}

// Función para verificar si el usuario tiene permiso
function tienePermiso($roles_permitidos = []) {
    if (!estaLogueado()) {
        return false;
    }
    
    // Si no se especifican roles, cualquier usuario logueado tiene permiso
    if (empty($roles_permitidos)) {
        return true;
    }
    
    return in_array($_SESSION['usuario_tipo'], $roles_permitidos);
}

// Función para requerir login
function requiereLogin() {
    if (!estaLogueado()) {
        header("Location: /ifast_web/index.php");
        exit();
    }
}

// Función para requerir permisos específicos
function requierePermiso($roles_permitidos = []) {
    requiereLogin();

    if (!tienePermiso($roles_permitidos)) {
        header("Location: /ifast_web/dashboard.php?error=sin_permiso");
        exit();
    }
}

// Función para hacer login
function login($email, $password, $conn) {
    try {
        $stmt = $conn->prepare("SELECT id, nombre, apellido, email, password, tipo FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $usuario = $stmt->fetch();
            
            // Verificar la contraseña
            if (password_verify($password, $usuario['password'])) {
                // Crear sesión
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_apellido'] = $usuario['apellido'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_tipo'] = $usuario['tipo'];
                
                return true;
            }
        }
        
        return false;
    } catch(PDOException $e) {
        return false;
    }
}

// Función para hacer logout
function logout() {
    session_unset();
    session_destroy();
    header("Location: /ifast_web/index.php");
    exit();
}

// Función para obtener el nombre completo del usuario logueado
function obtenerNombreUsuario() {
    if (estaLogueado()) {
        return $_SESSION['usuario_nombre'] . ' ' . $_SESSION['usuario_apellido'];
    }
    return '';
}

// Función para obtener el tipo de usuario
function obtenerTipoUsuario() {
    if (estaLogueado()) {
        return $_SESSION['usuario_tipo'];
    }
    return '';
}
?>
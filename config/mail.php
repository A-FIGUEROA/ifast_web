<?php
// config/mail.php
// Configuración de conexión SMTP
// IMPORTANTE: esta contraseña estuvo antes hardcodeada dentro de modules/embarques/enviar_correo.php.
// Si ese archivo llegó a subirse a un repositorio público, la contraseña debe rotarse.

return [
    'host'        => 'ifast.com.pe',
    'username'    => 'ventas@ifast.com.pe',
    'password'    => '*VO=ndl*&PB0e&L6',
    'port'        => 465,
    'from_email'  => 'ventas@ifast.com.pe',
    'from_name'   => 'IFAST - Sistema de Embarques',
];
?>

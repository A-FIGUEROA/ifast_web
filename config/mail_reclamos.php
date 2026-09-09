<?php
// config/mail_reclamos.php
// Configuración SMTP exclusiva para responder reclamos del Libro de Reclamaciones.
// Separado de config/mail.php (que usa ventas@ifast.com.pe para Embarques) para no interferir con ese módulo.

return [
    'host'       => 'ifast.com.pe',
    'username'   => 'sugerencias_reclamos@ifast.com.pe',
    'password'   => 'lHW_AFp%uc{wUtV2',
    'port'       => 465,
    'from_email' => 'sugerencias_reclamos@ifast.com.pe',
    'from_name'  => 'IFAST Shipping - Libro de Reclamaciones',
];
?>

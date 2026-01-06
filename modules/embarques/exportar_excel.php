<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once 'generar_excel.php';

requiereLogin();

$database = new Database();
$conn = $database->getConnection();

$id_guia = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_guia <= 0) {
    header("Location: index.php?error=ID inválido");
    exit();
}

generarExcelEmbarque($conn, $id_guia, false);
?>

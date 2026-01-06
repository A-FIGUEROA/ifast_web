<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requierePermiso(['ADMIN', 'SUPERVISOR']);

$database = new Database();
$conn = $database->getConnection();

// Obtener todos los archivos con información del cliente
$stmt = $conn->query("
    SELECT 
        ac.id,
        c.nombre_razon_social,
        c.apellido,
        c.documento,
        ac.nombre_archivo,
        ac.tipo_archivo,
        ac.subido_en
    FROM archivos_clientes ac
    INNER JOIN clientes c ON ac.cliente_id = c.id
    ORDER BY ac.subido_en DESC
");
$archivos = $stmt->fetchAll();

if (count($archivos) === 0) {
    header("Location: ../../dashboard.php?error=No hay archivos para exportar");
    exit();
}

$filename = "archivos_clientes_" . date('Y-m-d_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

$headers = [
    'ID',
    'Cliente',
    'Apellido',
    'Documento',
    'Nombre Archivo',
    'Tipo',
    'Fecha Subida'
];
fputcsv($output, $headers);

foreach ($archivos as $archivo) {
    $row = [
        $archivo['id'],
        $archivo['nombre_razon_social'],
        $archivo['apellido'],
        $archivo['documento'],
        $archivo['nombre_archivo'],
        $archivo['tipo_archivo'],
        formatearFecha($archivo['subido_en'], 'd/m/Y H:i')
    ];
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requiereLogin();
header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

$year = date('Y');

// Obtener el último número de guía del año actual
$stmt = $conn->prepare("
    SELECT nro_guia
    FROM guias_embarque
    WHERE nro_guia LIKE :patron
    ORDER BY nro_guia DESC
    LIMIT 1
");
$patron = "EMB-{$year}-%";
$stmt->bindParam(':patron', $patron);
$stmt->execute();
$ultima_guia = $stmt->fetch();

// Si existe una guía del año actual, extraer el número y sumarle 1
if ($ultima_guia && !empty($ultima_guia['nro_guia'])) {
    // Extraer el número de la última guía (EMB-2025-00005 -> 00005)
    $partes = explode('-', $ultima_guia['nro_guia']);
    $ultimo_numero = intval(end($partes));
    $nuevo_numero = $ultimo_numero + 1;
} else {
    // Si no existe ninguna guía del año actual, empezar en 1
    $nuevo_numero = 1;
}

$numero = 'EMB-' . $year . '-' . str_pad($nuevo_numero, 5, '0', STR_PAD_LEFT);

echo json_encode(['success' => true, 'numero' => $numero]);
?>

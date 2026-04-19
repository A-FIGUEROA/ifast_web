<?php
// modules/postulantes/descargar_cv.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requierePermiso(['ADMIN']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) exit('Solicitud inválida.');

$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("SELECT cv_archivo, cv_nombre_orig, cv_tipo FROM postulantes WHERE id = :id LIMIT 1");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$p = $stmt->fetch();

if (!$p) exit('Postulante no encontrado.');

$ruta = __DIR__ . '/../../uploads/postulantes/' . $p['cv_archivo'];
if (!file_exists($ruta)) exit('Archivo no encontrado.');

$tipos_mime = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

$mime = $tipos_mime[$p['cv_tipo']] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . addslashes($p['cv_nombre_orig']) . '"');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: no-cache');
readfile($ruta);
exit;

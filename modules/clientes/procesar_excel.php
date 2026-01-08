<?php
// modules/clientes/procesar_excel.php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Solo ADMIN, SUPERVISOR y VENTAS pueden importar
requierePermiso(['ADMIN', 'SUPERVISOR', 'VENTAS']);

header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

// Obtener ID del usuario actual
$usuario_id = $_SESSION['usuario_id'];

try {
    if (!isset($_FILES['archivo'])) {
        throw new Exception('No se recibió ningún archivo');
    }

    $archivo = $_FILES['archivo'];

    // Validar errores de subida
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo');
    }

    // Validar extensión
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsx', 'xls'])) {
        throw new Exception('Solo se permiten archivos .xlsx o .xls');
    }

    // Validar tamaño (10MB máximo)
    if ($archivo['size'] > 10 * 1024 * 1024) {
        throw new Exception('El archivo no puede superar los 10MB');
    }

    // Cargar archivo Excel
    $spreadsheet = IOFactory::load($archivo['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray();

    // Validar que tenga datos
    if (count($data) < 2) {
        throw new Exception('El archivo está vacío o no tiene datos');
    }

    // Validar encabezados esperados
    $headers = $data[0];
    $expected_headers = [
        'TIPO_DOC',
        'DOCUMENTO',
        'NOMBRE_RAZON_SOCIAL',
        'APELLIDO',
        'EMAIL',
        'TELIF',
        'CELULAR',
        'DIRECCION',
        'DISTRITO',
        'PROVINCIA',
        'DEPARTAMENTO'
    ];

    // Mapear indices de columnas
    $column_map = [];
    foreach ($expected_headers as $expected) {
        $found = false;
        foreach ($headers as $index => $header) {
            if (trim(strtoupper($header)) === $expected) {
                $column_map[$expected] = $index;
                $found = true;
                break;
            }
        }
        if (!$found && !in_array($expected, ['APELLIDO', 'TELIF'])) { // Campos opcionales
            throw new Exception("Falta la columna requerida: {$expected}");
        }
    }

    $preview_mode = isset($_POST['preview']) && $_POST['preview'] === '1';

    $registros_validos = [];
    $registros_error = [];
    $documentos_existentes = [];

    // Procesar filas (saltar encabezado)
    for ($i = 1; $i < count($data); $i++) {
        $row = $data[$i];

        // Saltar filas vacías
        if (empty(array_filter($row))) {
            continue;
        }

        $registro = [
            'fila' => $i + 1,
            'tipo_documento' => isset($column_map['TIPO_DOC']) ? trim($row[$column_map['TIPO_DOC']]) : '',
            'documento' => isset($column_map['DOCUMENTO']) ? trim($row[$column_map['DOCUMENTO']]) : '',
            'nombre_razon_social' => isset($column_map['NOMBRE_RAZON_SOCIAL']) ? trim($row[$column_map['NOMBRE_RAZON_SOCIAL']]) : '',
            'apellido' => isset($column_map['APELLIDO']) ? trim($row[$column_map['APELLIDO']]) : '',
            'email' => isset($column_map['EMAIL']) ? trim($row[$column_map['EMAIL']]) : '',
            'telif' => isset($column_map['TELIF']) ? trim($row[$column_map['TELIF']]) : '',
            'celular' => isset($column_map['CELULAR']) ? trim($row[$column_map['CELULAR']]) : '',
            'direccion' => isset($column_map['DIRECCION']) ? trim($row[$column_map['DIRECCION']]) : '',
            'distrito' => isset($column_map['DISTRITO']) ? trim($row[$column_map['DISTRITO']]) : '',
            'provincia' => isset($column_map['PROVINCIA']) ? trim($row[$column_map['PROVINCIA']]) : '',
            'departamento' => isset($column_map['DEPARTAMENTO']) ? trim($row[$column_map['DEPARTAMENTO']]) : '',
            'errores' => []
        ];

        // Validaciones
        if (!in_array(strtoupper($registro['tipo_documento']), ['DNI', 'RUC'])) {
            $registro['errores'][] = 'Tipo de documento inválido (debe ser DNI o RUC)';
        }

        if (empty($registro['documento'])) {
            $registro['errores'][] = 'Documento es requerido';
        } else {
            // Validar formato según tipo
            if (strtoupper($registro['tipo_documento']) === 'DNI') {
                if (!preg_match('/^\d{8}$/', $registro['documento'])) {
                    $registro['errores'][] = 'DNI debe tener 8 dígitos';
                }
            } elseif (strtoupper($registro['tipo_documento']) === 'RUC') {
                if (!preg_match('/^\d{11}$/', $registro['documento'])) {
                    $registro['errores'][] = 'RUC debe tener 11 dígitos';
                }
            }

            // Verificar si ya existe en BD
            $stmt = $conn->prepare("SELECT id FROM clientes WHERE documento = :documento");
            $stmt->bindParam(':documento', $registro['documento']);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $registro['errores'][] = 'Documento ya existe en la base de datos';
                $documentos_existentes[] = $registro['documento'];
            }
        }

        if (empty($registro['nombre_razon_social'])) {
            $registro['errores'][] = 'Nombre/Razón Social es requerido';
        }

        if (empty($registro['email'])) {
            $registro['errores'][] = 'Email es requerido';
        } elseif (!filter_var($registro['email'], FILTER_VALIDATE_EMAIL)) {
            $registro['errores'][] = 'Email no válido';
        }

        if (empty($registro['celular'])) {
            $registro['errores'][] = 'Celular es requerido';
        }

        if (empty($registro['direccion'])) {
            $registro['errores'][] = 'Dirección es requerida';
        }

        if (empty($registro['distrito']) || empty($registro['provincia']) || empty($registro['departamento'])) {
            $registro['errores'][] = 'Distrito, Provincia y Departamento son requeridos';
        }

        // Clasificar registro
        if (empty($registro['errores'])) {
            $registros_validos[] = $registro;
        } else {
            $registros_error[] = $registro;
        }
    }

    // MODO PREVIEW: Retornar primeros registros para revisión
    if ($preview_mode) {
        $preview_validos = array_slice($registros_validos, 0, 10);
        $preview_errores = array_slice($registros_error, 0, 10);

        echo json_encode([
            'success' => true,
            'preview' => true,
            'total_registros' => count($registros_validos) + count($registros_error),
            'total_validos' => count($registros_validos),
            'total_errores' => count($registros_error),
            'registros_validos' => $preview_validos,
            'registros_error' => $preview_errores,
            'nombre_archivo' => $archivo['name']
        ]);
        exit();
    }

    // MODO IMPORTACIÓN: Insertar registros válidos
    if (count($registros_validos) === 0) {
        throw new Exception('No hay registros válidos para importar');
    }

    $conn->beginTransaction();

    $insertados = 0;
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

    foreach ($registros_validos as $registro) {
        try {
            $stmt->execute([
                ':tipo_documento' => strtoupper($registro['tipo_documento']),
                ':documento' => $registro['documento'],
                ':nombre_razon_social' => $registro['nombre_razon_social'],
                ':apellido' => $registro['apellido'],
                ':email' => $registro['email'],
                ':telif' => $registro['telif'],
                ':celular' => $registro['celular'],
                ':direccion' => $registro['direccion'],
                ':distrito' => $registro['distrito'],
                ':provincia' => $registro['provincia'],
                ':departamento' => $registro['departamento'],
                ':creado_por' => $usuario_id
            ]);
            $insertados++;
        } catch (PDOException $e) {
            // Si falla uno, registrar pero continuar
            error_log("Error al insertar cliente fila {$registro['fila']}: " . $e->getMessage());
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'preview' => false,
        'insertados' => $insertados,
        'total_validos' => count($registros_validos),
        'total_errores' => count($registros_error),
        'message' => "Se importaron {$insertados} clientes exitosamente"
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

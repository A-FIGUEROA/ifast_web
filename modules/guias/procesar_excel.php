<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Solo ADMIN puede procesar Excel
requierePermiso(['ADMIN']);

header('Content-Type: application/json');

$response = [
    'success' => false,
    'mensaje' => '',
    'total_registros' => 0,
    'registros_validos' => 0,
    'registros_error' => 0,
    'preview' => [],
    'registros_importados' => 0,
    'registros_omitidos' => 0
];

try {
    // Verificar que se haya subido un archivo
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se ha subido ningún archivo');
    }

    $archivo = $_FILES['archivo'];
    $preview_mode = isset($_POST['preview']) && $_POST['preview'] == '1';

    // Validar extensión
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'])) {
        throw new Exception('El archivo debe ser de formato Excel (.xlsx o .xls)');
    }

    // Cargar el archivo Excel
    $spreadsheet = IOFactory::load($archivo['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = $worksheet->getHighestRow();

    // Verificar que tenga datos
    if ($highestRow < 2) {
        throw new Exception('El archivo Excel está vacío');
    }

    // Conectar a BD
    $database = new Database();
    $conn = $database->getConnection();

    $datos_validos = [];
    $errores = [];

    // Leer datos del Excel (desde fila 2, la 1 son encabezados)
    for ($row = 2; $row <= $highestRow; $row++) {
        try {
            // Leer las columnas (A=1, B=2, etc.)
            $nro_guia = trim($worksheet->getCell('B' . $row)->getValue());
            $consignatario = trim($worksheet->getCell('C' . $row)->getValue());
            $cliente = trim($worksheet->getCell('D' . $row)->getValue());
            $descripcion = trim($worksheet->getCell('E' . $row)->getValue());
            $pcs = (int)$worksheet->getCell('F' . $row)->getValue();
            $peso_kg = (float)$worksheet->getCell('G' . $row)->getValue();
            $valor_fob_usd = (float)$worksheet->getCell('H' . $row)->getValue();
            $fecha_embarque_raw = $worksheet->getCell('I' . $row)->getValue();
            $asesor = trim($worksheet->getCell('J' . $row)->getValue());

            // Convertir fecha
            $fecha_embarque = null;
            if (!empty($fecha_embarque_raw)) {
                try {
                    // Si es un número (fecha de Excel)
                    if (is_numeric($fecha_embarque_raw)) {
                        $fecha_embarque = Date::excelToDateTimeObject($fecha_embarque_raw)->format('Y-m-d');
                    } else {
                        // Intentar parsear fechas como "1-sep", "3-sep"
                        $fecha_str = $fecha_embarque_raw;

                        // Meses en español e inglés
                        $meses = [
                            'ene' => '01', 'jan' => '01',
                            'feb' => '02',
                            'mar' => '03',
                            'abr' => '04', 'apr' => '04',
                            'may' => '05',
                            'jun' => '06',
                            'jul' => '07',
                            'ago' => '08', 'aug' => '08',
                            'sep' => '09', 'set' => '09',
                            'oct' => '10',
                            'nov' => '11',
                            'dic' => '12', 'dec' => '12'
                        ];

                        // Extraer día y mes
                        if (preg_match('/(\d+)-([a-z]{3})/i', $fecha_str, $matches)) {
                            $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                            $mes_str = strtolower($matches[2]);

                            if (isset($meses[$mes_str])) {
                                $mes = $meses[$mes_str];
                                $anio = date('Y'); // Año actual
                                $fecha_embarque = "$anio-$mes-$dia";
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Si falla, dejar null
                    $fecha_embarque = null;
                }
            }

            // Validar campos obligatorios
            if (empty($nro_guia)) {
                throw new Exception("N° de guía vacío en fila $row");
            }

            if (empty($consignatario)) {
                throw new Exception("Consignatario vacío en fila $row");
            }

            // Verificar duplicados en BD
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM guias_masivas WHERE nro_guia = :nro_guia");
            $stmt->bindParam(':nro_guia', $nro_guia);
            $stmt->execute();
            $existe = $stmt->fetch()['total'] > 0;

            if ($existe) {
                throw new Exception("N° guía duplicado: $nro_guia");
            }

            // Si llegó aquí, el registro es válido
            $datos_validos[] = [
                'nro_guia' => $nro_guia,
                'consignatario' => $consignatario,
                'cliente' => $cliente,
                'descripcion' => $descripcion,
                'pcs' => $pcs,
                'peso_kg' => $peso_kg,
                'valor_fob_usd' => $valor_fob_usd,
                'fecha_embarque' => $fecha_embarque,
                'asesor' => $asesor,
                'fila' => $row
            ];

        } catch (Exception $e) {
            $errores[] = [
                'fila' => $row,
                'error' => $e->getMessage()
            ];
        }
    }

    $response['total_registros'] = $highestRow - 1;
    $response['registros_validos'] = count($datos_validos);
    $response['registros_error'] = count($errores);
    $response['nombre_archivo'] = $archivo['name'];

    // Si es modo preview, devolver primeras 10 filas
    if ($preview_mode) {
        $response['success'] = true;
        $response['preview'] = array_slice($datos_validos, 0, 10);
        $response['errores'] = $errores;
    } else {
        // Modo importación: insertar en BD
        if (count($datos_validos) === 0) {
            throw new Exception('No hay registros válidos para importar');
        }

        $conn->beginTransaction();

        $stmt = $conn->prepare("
            INSERT INTO guias_masivas
            (nro_guia, consignatario, cliente, descripcion, pcs, peso_kg, valor_fob_usd, fecha_embarque, asesor, estado, metodo_ingreso, nombre_archivo_origen, creado_por)
            VALUES
            (:nro_guia, :consignatario, :cliente, :descripcion, :pcs, :peso_kg, :valor_fob_usd, :fecha_embarque, :asesor, 'PENDIENTE', 'EXCEL', :nombre_archivo, :creado_por)
        ");

        $importados = 0;
        foreach ($datos_validos as $dato) {
            try {
                $stmt->bindParam(':nro_guia', $dato['nro_guia']);
                $stmt->bindParam(':consignatario', $dato['consignatario']);
                $stmt->bindParam(':cliente', $dato['cliente']);
                $stmt->bindParam(':descripcion', $dato['descripcion']);
                $stmt->bindParam(':pcs', $dato['pcs']);
                $stmt->bindParam(':peso_kg', $dato['peso_kg']);
                $stmt->bindParam(':valor_fob_usd', $dato['valor_fob_usd']);
                $stmt->bindParam(':fecha_embarque', $dato['fecha_embarque']);
                $stmt->bindParam(':asesor', $dato['asesor']);
                $stmt->bindParam(':nombre_archivo', $archivo['name']);
                $stmt->bindParam(':creado_por', $_SESSION['usuario_id']);

                if ($stmt->execute()) {
                    $importados++;
                }
            } catch (Exception $e) {
                // Registrar error pero continuar con los demás
            }
        }

        $conn->commit();

        $response['success'] = true;
        $response['mensaje'] = "Importación completada";
        $response['registros_importados'] = $importados;
        $response['registros_omitidos'] = count($datos_validos) - $importados;
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['mensaje'] = $e->getMessage();
}

echo json_encode($response);

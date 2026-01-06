<?php
// modules/clientes/descargar_plantilla.php
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Crear nuevo archivo Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Clientes');

// Encabezados
$headers = [
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

// Escribir encabezados
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);

    // Estilo del encabezado
    $sheet->getStyle($col . '1')->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 11
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '00509D']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);

    // Ajustar ancho de columna
    $sheet->getColumnDimension($col)->setWidth(20);
    $col++;
}

// Datos de ejemplo (3 filas)
$ejemplos = [
    ['DNI', '12345678', 'Juan Pérez', 'García', 'juan.perez@email.com', '014567890', '987654321', 'Av. Ejemplo 123', 'Miraflores', 'Lima', 'Lima'],
    ['RUC', '20123456789', 'Empresa SAC', '', 'contacto@empresa.com', '', '999888777', 'Jr. Comercio 456', 'San Isidro', 'Lima', 'Lima'],
    ['DNI', '87654321', 'María López', 'Rodríguez', 'maria.lopez@email.com', '', '912345678', 'Calle Los Olivos 789', 'Surco', 'Lima', 'Lima']
];

$row = 2;
foreach ($ejemplos as $ejemplo) {
    $col = 'A';
    foreach ($ejemplo as $valor) {
        $sheet->setCellValue($col . $row, $valor);

        // Estilo de datos
        $sheet->getStyle($col . $row)->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);

        $col++;
    }
    $row++;
}

// Agregar hoja de instrucciones
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Instrucciones');

$instructions = [
    ['INSTRUCCIONES PARA IMPORTAR CLIENTES'],
    [''],
    ['1. CAMPOS REQUERIDOS (obligatorios):'],
    ['   - TIPO_DOC: Debe ser "DNI" o "RUC"'],
    ['   - DOCUMENTO: 8 dígitos para DNI, 11 dígitos para RUC'],
    ['   - NOMBRE_RAZON_SOCIAL: Nombre completo o razón social'],
    ['   - EMAIL: Correo electrónico válido'],
    ['   - CELULAR: Número de celular'],
    ['   - DIRECCION: Dirección completa'],
    ['   - DISTRITO: Distrito de residencia'],
    ['   - PROVINCIA: Provincia'],
    ['   - DEPARTAMENTO: Departamento'],
    [''],
    ['2. CAMPOS OPCIONALES:'],
    ['   - APELLIDO: Apellidos (opcional para personas naturales)'],
    ['   - TELIF: Teléfono fijo (opcional)'],
    [''],
    ['3. FORMATO:'],
    ['   - No modificar los nombres de las columnas'],
    ['   - Eliminar las filas de ejemplo antes de importar'],
    ['   - Los documentos duplicados serán ignorados'],
    ['   - Máximo 1000 registros por archivo'],
    [''],
    ['4. VALIDACIONES:'],
    ['   - DNI: Exactamente 8 dígitos numéricos'],
    ['   - RUC: Exactamente 11 dígitos numéricos'],
    ['   - EMAIL: Debe tener formato válido (ejemplo@dominio.com)'],
    [''],
    ['5. EJEMPLO DE USO:'],
    ['   - Complete los datos en la hoja "Clientes"'],
    ['   - Guarde el archivo'],
    ['   - Súbalo en la pantalla de importación'],
    ['   - Revise la vista previa antes de confirmar'],
    ['   - Confirme la importación']
];

$row = 1;
foreach ($instructions as $instruction) {
    $instructionsSheet->setCellValue('A' . $row, $instruction[0]);

    if ($row === 1) {
        // Título
        $instructionsSheet->getStyle('A' . $row)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => '00509D']
            ]
        ]);
    } elseif (strpos($instruction[0], '. ') !== false && strlen($instruction[0]) < 50) {
        // Subtítulos
        $instructionsSheet->getStyle('A' . $row)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11
            ]
        ]);
    }

    $row++;
}

$instructionsSheet->getColumnDimension('A')->setWidth(80);

// Volver a la primera hoja
$spreadsheet->setActiveSheetIndex(0);

// Configurar headers para descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="PLANTILLA_CLIENTES_IFAST.xlsx"');
header('Cache-Control: max-age=0');

// Crear writer y descargar
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();

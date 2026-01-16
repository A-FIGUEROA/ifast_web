<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Solo ADMIN y SUPERVISOR pueden descargar plantilla
requierePermiso(['ADMIN', 'SUPERVISOR']);

// Crear nuevo spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Guías');

// Encabezados
$headers = ['ID', '# GUIA', 'CONSIGNATARIO', 'CLIENTE', 'RUC/DNI', 'DESCRIPCION', 'PCS', 'PESO  MANIF. KG', 'VALOR FOB US$. ', 'FECHA DE EMBARQUE', 'ASESOR'];
$columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];

// Escribir encabezados
foreach ($columns as $index => $col) {
    $sheet->setCellValue($col . '1', $headers[$index]);
}

// Estilo de encabezados
$headerStyle = [
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
];

$sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

// Ajustar ancho de columnas
$sheet->getColumnDimension('A')->setWidth(8);   // ID
$sheet->getColumnDimension('B')->setWidth(20);  // # GUIA
$sheet->getColumnDimension('C')->setWidth(30);  // CONSIGNATARIO
$sheet->getColumnDimension('D')->setWidth(30);  // CLIENTE
$sheet->getColumnDimension('E')->setWidth(15);  // RUC/DNI
$sheet->getColumnDimension('F')->setWidth(35);  // DESCRIPCION
$sheet->getColumnDimension('G')->setWidth(8);   // PCS
$sheet->getColumnDimension('H')->setWidth(15);  // PESO MANIF. KG
$sheet->getColumnDimension('I')->setWidth(15);  // VALOR FOB US$
$sheet->getColumnDimension('J')->setWidth(18);  // FECHA DE EMBARQUE
$sheet->getColumnDimension('K')->setWidth(25);  // ASESOR

// Agregar fila de ejemplo
$sheet->setCellValue('A2', '1');
$sheet->setCellValue('B2', 'HAWBO0086411');
$sheet->setCellValue('C2', 'JUAN PEREZ GARCIA');
$sheet->setCellValue('D2', 'IMPORTACIONES ABC SAC');
$sheet->setCellValue('E2', '20123456789');
$sheet->setCellValue('F2', 'MUÑECOS DE COLECCIÓN');
$sheet->setCellValue('G2', '5');
$sheet->setCellValue('H2', '8.25');
$sheet->setCellValue('I2', '194.21');
$sheet->setCellValue('J2', '1-sep');
$sheet->setCellValue('K2', 'CARLOS RAMIREZ');

// Estilo de fila de ejemplo
$exampleStyle = [
    'font' => [
        'italic' => true,
        'color' => ['rgb' => '666666']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F0F0']
    ]
];
$sheet->getStyle('A2:K2')->applyFromArray($exampleStyle);

// Agregar nota
$sheet->setCellValue('A4', 'NOTA: Esta es una fila de ejemplo. El RUC/DNI debe coincidir exactamente con un cliente registrado para asignación automática. Elimínala antes de subir tu archivo.');
$sheet->mergeCells('A4:K4');
$sheet->getStyle('A4')->applyFromArray([
    'font' => ['italic' => true, 'color' => ['rgb' => 'FF0000']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// Configurar para descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Plantilla_Guias_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();

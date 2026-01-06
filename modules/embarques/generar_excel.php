<?php
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Función compartida para generar Excel
function generarExcelEmbarque($conn, $id_guia, $solo_guardar = false) {

    $stmt = $conn->prepare("SELECT * FROM guias_embarque WHERE id_guia = :id_guia");
    $stmt->bindParam(':id_guia', $id_guia);
    $stmt->execute();
    $guia = $stmt->fetch();

    if (!$guia) return false;

    $stmt = $conn->prepare("
        SELECT pt.tracking_code, rp.pendiente_pago, rp.monto_pendiente,
               COALESCE(rp.nombre_original, rp.nombre_archivo) as nombre_original,
               pt.fecha_creacion as subido_en
        FROM guia_pedidos gp
        INNER JOIN pedidos_trackings pt ON gp.tracking_id = pt.id
        INNER JOIN recibos_pedidos rp ON pt.recibo_pedido_id = rp.id
        WHERE gp.id_guia = :id_guia
        ORDER BY pt.fecha_creacion DESC
    ");
    $stmt->bindParam(':id_guia', $id_guia);
    $stmt->execute();
    $trackings = $stmt->fetchAll();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // ENCABEZADOS EN FILA 3
    $headers = [
        'B' => 'GUÍA',
        'C' => 'LA CAJA ESTA A NOMBRE DE',
        'D' => 'DNI',
        'E' => 'PROVEEDOR',
        'F' => 'CONSIGNATARIO',
        'G' => '(' . count($trackings) . ')TRACKING',
        'H' => 'CONTENIDO',
        'I' => 'VALOR USD',
        'J' => 'INDICACIONES'
    ];

    foreach ($headers as $col => $header) {
        $sheet->setCellValue($col . '3', $header);
    }

    // Estilos de encabezados (fondo azul oscuro #376093, texto blanco, negrita, tamaño 12)
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 12
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '376093']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ]
    ];
    $sheet->getStyle('B3:J3')->applyFromArray($headerStyle);

    // Ajustar anchos de columnas
    $sheet->getColumnDimension('A')->setWidth(3.29);
    $sheet->getColumnDimension('B')->setWidth(6.14);
    $sheet->getColumnDimension('C')->setWidth(34.29);
    $sheet->getColumnDimension('D')->setWidth(18);
    $sheet->getColumnDimension('E')->setWidth(16.86);
    $sheet->getColumnDimension('F')->setWidth(28.14);
    $sheet->getColumnDimension('G')->setWidth(35.57);
    $sheet->getColumnDimension('H')->setWidth(19.43);
    $sheet->getColumnDimension('I')->setWidth(19.71);
    $sheet->getColumnDimension('J')->setWidth(31.43);

    // DATOS - Empiezan en fila 4
    $startRow = 4;
    $numTrackings = count($trackings);
    $endRow = $startRow + $numTrackings - 1;

    // Si no hay trackings, al menos 1 fila
    if ($numTrackings == 0) {
        $endRow = $startRow;
        $trackings = [['tracking_code' => '-']]; // Fila vacía
    }

    // Llenar datos (columnas que se combinan verticalmente)
    $sheet->setCellValue('B' . $startRow, '1'); // Número de guía (correlativo)
    $sheet->setCellValue('C' . $startRow, $guia['nombre_completo']);
    $sheet->setCellValue('D' . $startRow, $guia['documento']);
    $sheet->setCellValue('E' . $startRow, $guia['proveedor'] ?? '-');
    $sheet->setCellValue('F' . $startRow, $guia['nombre_completo']); // Consignatario = nombre
    $sheet->setCellValue('H' . $startRow, $guia['contenido'] ?? '-');
    $sheet->setCellValue('I' . $startRow, '$' . number_format($guia['valor_usd'], 2));
    $sheet->setCellValue('J' . $startRow, $guia['indicaciones'] ?? '-');

    // Llenar trackings (cada uno en su fila, sin combinar)
    $currentRow = $startRow;
    foreach ($trackings as $tracking) {
        $sheet->setCellValue('G' . $currentRow, $tracking['tracking_code']);
        $currentRow++;
    }

    // Combinar celdas verticalmente para columnas B, C, D, E, F, H, I, J
    if ($numTrackings > 1) {
        $mergeCols = ['B', 'C', 'D', 'E', 'F', 'H', 'I', 'J'];
        foreach ($mergeCols as $col) {
            $sheet->mergeCells($col . $startRow . ':' . $col . $endRow);
        }
    }

    // Aplicar estilos a las celdas de datos
    // Fondo gris (#F2F2F2) para columnas B, C, D, E, F
    $grayBgStyle = [
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F2F2F2']
        ]
    ];
    $sheet->getStyle('B' . $startRow . ':B' . $endRow)->applyFromArray($grayBgStyle);
    $sheet->getStyle('C' . $startRow . ':C' . $endRow)->applyFromArray($grayBgStyle);
    $sheet->getStyle('D' . $startRow . ':D' . $endRow)->applyFromArray($grayBgStyle);
    $sheet->getStyle('E' . $startRow . ':E' . $endRow)->applyFromArray($grayBgStyle);
    $sheet->getStyle('F' . $startRow . ':F' . $endRow)->applyFromArray($grayBgStyle);

    // Texto rojo y negrita para columnas C, D, E, F
    $redBoldStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FF0000'],
            'size' => 12
        ]
    ];
    $sheet->getStyle('C' . $startRow . ':F' . $endRow)->applyFromArray($redBoldStyle);

    // Texto rojo para columna G (trackings)
    $redStyle = [
        'font' => [
            'color' => ['rgb' => 'FF0000'],
            'size' => 11
        ]
    ];
    $sheet->getStyle('G' . $startRow . ':G' . $endRow)->applyFromArray($redStyle);

    // Texto rojo y negrita para columnas H, I, J
    $redBoldStyle2 = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FF0000'],
            'size' => 12
        ]
    ];
    $sheet->getStyle('H' . $startRow . ':J' . $endRow)->applyFromArray($redBoldStyle2);

    // Tamaño de fuente para columna B
    $sheet->getStyle('B' . $startRow . ':B' . $endRow)->getFont()->setSize(12);

    // Alineación vertical centrada para todas las celdas combinadas
    $centerVertical = [
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    $sheet->getStyle('B' . $startRow . ':J' . $endRow)->applyFromArray($centerVertical);

    $directorio = '../../uploads/embarques/';
    if (!file_exists($directorio)) mkdir($directorio, 0777, true);

    $nombre_archivo = 'Guia_' . $guia['nro_guia'] . '.xlsx';
    $ruta_archivo = $directorio . $nombre_archivo;

    $writer = new Xlsx($spreadsheet);
    $writer->save($ruta_archivo);

    $stmt_update = $conn->prepare("UPDATE guias_embarque SET ruta_excel = :ruta WHERE id_guia = :id");
    $stmt_update->bindParam(':ruta', $ruta_archivo);
    $stmt_update->bindParam(':id', $id_guia);
    $stmt_update->execute();

    if ($solo_guardar) {
        return $ruta_archivo;
    } else {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
        header('Content-Length: ' . filesize($ruta_archivo));
        readfile($ruta_archivo);
        exit();
    }
}
?>

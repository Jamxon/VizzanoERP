<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithEvents;

class PackingListExport implements FromArray, WithHeadings, WithColumnWidths, WithStyles, WithEvents
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            '№',
            'Модель',
            'Размер',
            'Имя',
            '№ упаковки',
            'кол-во мест',
            'кол-во в упаковке (шт)',
            'Вес нетто (кг)',
            'Вес брутто (кг)',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 30,
            'C' => 12,
            'D' => 20,
            'E' => 10,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 12,
        ];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();

                // 2 qatorli sarlavha
                $sheet->insertNewRowBefore(1, 2);
                $sheet->setCellValue('A1', '№');
                $sheet->setCellValue('B1', 'Модель');
                $sheet->setCellValue('C1', 'Размер');
                $sheet->setCellValue('D1', 'Имя');
                $sheet->setCellValue('E1', '№ упаковки');
                $sheet->setCellValue('F1', 'кол-во мест');
                $sheet->setCellValue('G1', 'кол-во в упаковке');
                $sheet->setCellValue('H1', 'Вес нетто');
                $sheet->setCellValue('I1', 'Вес брутто');

                // 2-qatordagi pastki sarlavhalar
                $sheet->setCellValue('C2', 'Рост');
                $sheet->setCellValue('D2', 'заказчик');
                $sheet->setCellValue('G2', '(шт)');
                $sheet->setCellValue('H2', '(кг)');
                $sheet->setCellValue('I2', '(кг)');

                // Center alignment
                $sheet->getStyle("A1:I2")->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A1:I2")->getAlignment()->setVertical('center');

                // Merge cells for upper headers
                $sheet->mergeCells('A1:A2');
                $sheet->mergeCells('B1:B2');
                $sheet->mergeCells('C1:C1'); // C2 ostida 'Рост' yoziladi
                $sheet->mergeCells('D1:D1'); // D2 ostida 'заказчик'
                $sheet->mergeCells('E1:E2');
                $sheet->mergeCells('F1:F2');
                $sheet->mergeCells('G1:G1'); // G2 ostida '(шт)'
                $sheet->mergeCells('H1:H1');
                $sheet->mergeCells('I1:I1');
            }
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 1; // +1 because headings are at row 1

        // Range from A1 to I[lastRow]
        $sheet->getStyle("A1:I$lastRow")->getAlignment()->setHorizontal('center');

        return [];
    }
}

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PackingListExport implements FromArray, WithColumnWidths, WithStyles, WithEvents
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


    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 30,
            'C' => 12,
            'D' => 20,
            'E' => 10,
            'F' => 12,
            'G' => 18,
            'H' => 12,
            'I' => 12,
        ];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Sarlavha yozish (2 qator)
                $sheet->setCellValue('A1', '№');
                $sheet->setCellValue('B1', 'Модель');
                $sheet->setCellValue('C1', 'Размер');
                $sheet->setCellValue('D1', 'Имя');
                $sheet->setCellValue('E1', '№ упаковки');
                $sheet->setCellValue('F1', 'кол-во мест');
                $sheet->setCellValue('G1', 'кол-во в упаковке');
                $sheet->setCellValue('H1', 'Вес нетто');
                $sheet->setCellValue('I1', 'Вес брутто');

                $sheet->setCellValue('C2', 'Рост');
                $sheet->setCellValue('D2', 'заказчик');
                $sheet->setCellValue('G2', '(шт)');
                $sheet->setCellValue('H2', '(кг)');
                $sheet->setCellValue('I2', '(кг)');

                // Merge cells
                $sheet->mergeCells('A1:A2');
                $sheet->mergeCells('B1:B2');
                $sheet->mergeCells('C1:C1');
                $sheet->mergeCells('D1:D1');
                $sheet->mergeCells('E1:E2');
                $sheet->mergeCells('F1:F2');
                $sheet->mergeCells('G1:G1');
                $sheet->mergeCells('H1:H1');
                $sheet->mergeCells('I1:I1');

                // Stil: markazlashtirish + border + bold
                $sheet->getStyle("A1:I2")->applyFromArray([
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical' => 'center',
                    ],
                    'font' => [
                        'bold' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => '000000'],
                        ],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFEFEFEF'],
                    ]
                ]);
            }
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $startRow = 3; // ma'lumotlar 3-qatordan boshlanadi
        $totalRows = count($this->data);
        $groupCount = (int)($totalRows / 3); // Har 3 qator 1 guruh (1 row)

        for ($i = 0; $i < $groupCount; $i++) {
            $rowStart = $startRow + ($i * 3);
            $rowEnd = $rowStart + 2;

            // Har 3 qatorga border va markazlashtirish
            $sheet->getStyle("A{$rowStart}:I{$rowEnd}")->applyFromArray([
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '000000'],
                    ],
                ],
            ]);

            // Faqat o‘rtadagi qatordagi D ustunini bold (Contragent)
            $sheet->getStyle("D" . ($rowStart + 1))->getFont()->setBold(true);
        }

        return [];
    }

}
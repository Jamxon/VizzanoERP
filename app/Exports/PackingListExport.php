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
        $startRow = 3;
        $totalRows = count($this->data);
        $groupCount = (int)($totalRows / 3);
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

        for ($i = 0; $i < $groupCount; $i++) {
            $row1 = $startRow + ($i * 3);
            $row2 = $row1 + 1;
            $row3 = $row1 + 2;

            foreach ($cols as $col) {
                // Yuqori chiziq (faqat birinchi qatorda)
                $sheet->getStyle("{$col}{$row1}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                // Pastki chiziq (faqat uchinchi qatorda)
                $sheet->getStyle("{$col}{$row3}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
            }

            // Har bir qatorning chap va o‘ng chiziqlari (A va I)
            for ($r = $row1; $r <= $row3; $r++) {
                $sheet->getStyle("A{$r}")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("I{$r}")->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN);
            }

            // D ustuni (contragent) ni ikkinchi qatorda bold qilish
            $sheet->getStyle("D{$row2}")->getFont()->setBold(true);

            // Rangga qarab B ustunini bo‘yash (ikkinchi qatordagi)
            $colorCell = $sheet->getCell("B{$row2}")->getValue();
            if (preg_match('/Цвет:\s*(.+)/u', $colorCell, $matches)) {
                $colorName = $matches[1];
                $colors = [
                    'Неви' => '000080',
                    'Синый' => '0000FF',
                    'Серый' => '808080',
                    'Кэмел чёрный' => '5C4033',
                    'Хаки черный' => '3B3C36',
                ];
                if (isset($colors[$colorName])) {
                    $sheet->getStyle("B{$row2}")->getFill()->applyFromArray([
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $colors[$colorName]],
                    ]);
                }
            }
        }

        return [];
    }

}
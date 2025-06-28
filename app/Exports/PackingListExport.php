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

                // Sarlavhalar (2 qator)
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

                // Sarlavhaga style
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

    public function styles(Worksheet $sheet): array
    {
        $startRow = 3;
        $totalRows = count($this->data);
        $groupCount = (int)($totalRows / 3);
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

        for ($i = 0; $i < $groupCount; $i++) {
            $row1 = $startRow + ($i * 3);
            $row2 = $row1 + 1;
            $row3 = $row1 + 2;

            // Yuqori va pastki border (1-row va 3-row)
            foreach ($cols as $col) {
                $sheet->getStyle("{$col}{$row1}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("{$col}{$row3}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
            }

            // Chap va o‘ng tarafga border va center align
            for ($r = $row1; $r <= $row3; $r++) {
                $sheet->getStyle("A{$r}")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("I{$r}")->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN);

                foreach ($cols as $col) {
                    // Markazga joylash
                    $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal('center');
                    $sheet->getStyle("{$col}{$r}")->getAlignment()->setVertical('center');
                }
            }

            // Contragent (D ustuni, 2-qator) bold
            $sheet->getStyle("D{$row2}")->getFont()->setBold(true);

            // Rang bo‘yicha B ustunini bo‘yash
            $colorCell = $sheet->getCell("B{$row2}")->getValue();
            if (preg_match('/Цвет:\s*(.+)/u', $colorCell, $matches)) {
                $colorName = $matches[1];
                $colors = [
                    'Неви' => '000080',             // navy
                    'Синий' => '0000FF',            // blue (to‘g‘ri yozilishi)
                    'Синый' => '0000FF',            // noto‘g‘ri yozilgan variant
                    'Светло-синий' => '87CEFA',     // light blue
                    'Темно-синий' => '00008B',      // dark blue
                    'Серый' => '808080',            // gray
                    'Светло-серый' => 'D3D3D3',     // light gray
                    'Темно-серый' => 'A9A9A9',      // dark gray
                    'Черный' => '000000',           // black
                    'Кэмел' => 'C19A6B',            // camel
                    'Кэмел чёрный' => '5C4033',     // camel + black (aralash)
                    'Хаки' => '78866B',             // khaki
                    'Хаки черный' => '3B3C36',      // khaki + black (aralash)
                    'Белый' => 'FFFFFF',            // white
                    'Бежевый' => 'F5F5DC',          // beige
                    'Красный' => 'FF0000',          // red
                    'Темно-красный' => '8B0000',    // dark red
                    'Розовый' => 'FFC0CB',          // pink
                    'Желтый' => 'FFFF00',           // yellow
                    'Оранжевый' => 'FFA500',        // orange
                    'Зеленый' => '008000',          // green
                    'Салатовый' => '7CFC00',        // light green
                    'Темно-зеленый' => '006400',    // dark green
                    'Фиолетовый' => '800080',       // purple
                    'Бордовый' => '800000',         // maroon
                    'Бирюзовый' => '40E0D0',        // turquoise
                    'Голубой' => 'ADD8E6',          // light blue
                    'Шоколадный' => '7B3F00',       // chocolate
                    'Кофейный' => '6F4E37',         // coffee
                    'Золотой' => 'FFD700',          // gold
                    'Серебряный' => 'C0C0C0',       // silver

                    // Default fallback color:
                    'default' => 'D3D3D3',          // light gray
                ];

                if (isset($colors[$colorName])) {
                    $sheet->getStyle("B{$row2}")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $colors[$colorName]],
                    ]);
                }
            }
        }

        return [];
    }
}

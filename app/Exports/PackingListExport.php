<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Events\BeforeSheet;

class PackingListExport implements WithMultipleSheets
{
    protected array $data;
    protected array $summary;

    public function __construct(array $data, array $summary)
    {
        $this->data = $data;
        $this->summary = $summary;
    }

    public function sheets(): array
    {
        return [
            'Packaging' => new class($this->data) implements FromArray, WithColumnWidths, WithStyles, WithEvents {
                protected array $data;
                public function __construct(array $data) { $this->data = $data; }

                public function array(): array { return $this->data; }

                public function columnWidths(): array {
                    return [
                        'A' => 10, 'B' => 30, 'C' => 12, 'D' => 20, 'E' => 10,
                        'F' => 12, 'G' => 18, 'H' => 12, 'I' => 12,
                    ];
                }

                public function registerEvents(): array {
                    return [
                        BeforeSheet::class => function (BeforeSheet $event) {
                            $sheet = $event->sheet->getDelegate();

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

                            $sheet->mergeCells('A1:A2');
                            $sheet->mergeCells('B1:B2');
                            $sheet->mergeCells('C1:C1');
                            $sheet->mergeCells('D1:D1');
                            $sheet->mergeCells('E1:E2');
                            $sheet->mergeCells('F1:F2');
                            $sheet->mergeCells('G1:G1');
                            $sheet->mergeCells('H1:H1');
                            $sheet->mergeCells('I1:I1');

                            $sheet->getStyle("A1:I2")->applyFromArray([
                                'alignment' => [ 'horizontal' => 'center', 'vertical' => 'center' ],
                                'font' => [ 'bold' => true ],
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

                public function styles(Worksheet $sheet): array {
                    $startRow = 3;
                    $totalRows = count($this->data);
                    $groupCount = (int)($totalRows / 3);
                    $cols = ['A','B','C','D','E','F','G','H','I'];

                    for ($i = 0; $i < $groupCount; $i++) {
                        $r1 = $startRow + ($i * 3);
                        $r2 = $r1 + 1;
                        $r3 = $r1 + 2;

                        foreach ($cols as $col) {
                            $sheet->getStyle("{$col}{$r1}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                            $sheet->getStyle("{$col}{$r3}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
                        }

                        for ($r = $r1; $r <= $r3; $r++) {
                            $sheet->getStyle("A{$r}")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN);
                            $sheet->getStyle("I{$r}")->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN);
                            foreach ($cols as $col) {
                                $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal('center');
                                $sheet->getStyle("{$col}{$r}")->getAlignment()->setVertical('center');
                            }
                        }

                        $sheet->getStyle("D{$r2}")->getFont()->setBold(true);

                        $colorCell = $sheet->getCell("B{$r2}")->getValue();
                        if (preg_match('/Цвет:\s*(.+)/u', $colorCell, $matches)) {
                            $colorName = $matches[1];
                            $colors = [
                                'Неви' => '000080', 'Синий' => '0000FF', 'Синый' => '0000FF', 'Светло-синий' => '87CEFA',
                                'Темно-синий' => '00008B', 'Серый' => '808080', 'Светло-серый' => 'D3D3D3', 'Темно-серый' => 'A9A9A9',
                                'Черный' => '000000', 'Кэмел' => 'C19A6B', 'Кэмел чёрный' => '5C4033', 'Хаки' => '78866B',
                                'Хаки черный' => '3B3C36', 'Белый' => 'FFFFFF', 'Бежевый' => 'F5F5DC', 'Красный' => 'FF0000',
                                'Темно-красный' => '8B0000', 'Розовый' => 'FFC0CB', 'Желтый' => 'FFFF00', 'Оранжевый' => 'FFA500',
                                'Зеленый' => '008000', 'Салатовый' => '7CFC00', 'Темно-зеленый' => '006400', 'Фиолетовый' => '800080',
                                'Бордовый' => '800000', 'Бирюзовый' => '40E0D0', 'Голубой' => 'ADD8E6', 'Шоколадный' => '7B3F00',
                                'Кофейный' => '6F4E37', 'Золотой' => 'FFD700', 'Серебряный' => 'C0C0C0', 'default' => 'D3D3D3',
                            ];
                            $color = $colors[$colorName] ?? $colors['default'];
                            $sheet->getStyle("B{$r2}")->getFill()->applyFromArray([
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $color],
                            ]);
                        }
                    }
                    return [];
                }
            },
            'Summary' => new class($this->summary) implements FromArray, WithStyles {
                protected array $summary;
                public function __construct(array $summary) { $this->summary = $summary; }
                public function array(): array { return $this->summary; }
                public function styles(Worksheet $sheet): array {
                    $lastRow = count($this->summary);
                    $sheet->getStyle("A1:G1")->getFont()->setBold(true);
                    $sheet->getStyle("A1:G{$lastRow}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => 'center',
                            'vertical' => 'center'
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => '000000']
                            ]
                        ]
                    ]);
                    return [];
                }
            }
        ];
    }
}
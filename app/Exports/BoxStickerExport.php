<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Events\AfterSheet;

class BoxStickerExport implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected array $stickers;
    protected ?string $imagePath;

    public function __construct(array $stickers, ?string $imagePath = null)
    {
        $this->stickers = $stickers;
        $this->imagePath = $imagePath;
    }

    public function array(): array
    {
        $result = [];

        foreach ($this->stickers as $index => $sticker) {
            // Har bir sticker orasiga bo'sh qator (birinchisidan tashqari)
            if ($index > 0) {
                $result[] = ['', ''];
                $result[] = ['', ''];
            }

            foreach ($sticker as $row) {
                $result[] = $row;
            }
        }

        return $result;
    }

    public function title(): string
    {
        return 'Box Stickers';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 20,
        ];
    }

    public function rowHeight(): array
    {
        $heights = [];
        $row = 1;

        foreach ($this->stickers as $index => $sticker) {
            if ($index > 0) {
                $heights[$row] = 15; // Bo'sh qator
                $row++;
                $heights[$row] = 15; // Bo'sh qator
                $row++;
            }

            // Har bir sticker qatori uchun balandlik
            foreach ($sticker as $stickerRowIndex => $stickerRow) {
                if ($stickerRowIndex == 0) {
                    $heights[$row] = 30; // Header row
                } else {
                    $heights[$row] = 20; // Oddiy qator
                }
                $row++;
            }
        }

        return $heights;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];
        $row = 1;

        foreach ($this->stickers as $index => $sticker) {
            $stickerStartRow = $row;

            // Bo'sh qatorlar (birinchisidan tashqari)
            if ($index > 0) {
                $row += 2;
                $stickerStartRow = $row;
            }

            foreach ($sticker as $rowIndex => $stickerRow) {
                if ($rowIndex == 0) {
                    // NIKASTYLE header
                    $styles["A{$row}:B{$row}"] = [
                        'font' => ['bold' => true, 'size' => 12, 'name' => 'Arial'],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THICK]
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE6E6FA']
                        ]
                    ];
                } elseif ($rowIndex == 1) {
                    // Костюм для девочки
                    $styles["A{$row}:B{$row}"] = [
                        'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial'],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]
                        ]
                    ];
                } elseif ($rowIndex == 2 || $rowIndex == 3) {
                    // Арт va Цвет qatorlari
                    $styles["A{$row}"] = [
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ];
                    $styles["B{$row}"] = [
                        'font' => ['size' => 10, 'name' => 'Arial'],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ];
                } elseif ($rowIndex == 4) {
                    // Size header (Размер, Количество)
                    $styles["A{$row}:B{$row}"] = [
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF0F0F0']
                        ]
                    ];
                } elseif (!empty($stickerRow[0]) && is_string($stickerRow[0]) && strpos($stickerRow[0], '-') !== false) {
                    // Size qatorlari (92-52, 98-52, ...)
                    $styles["A{$row}"] = [
                        'font' => ['size' => 10, 'name' => 'Arial'],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ];
                    $styles["B{$row}"] = [
                        'font' => ['size' => 10, 'name' => 'Arial'],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ];
                } elseif (isset($stickerRow[0]) && ($stickerRow[0] == 'Нетто(кг)' || strpos($stickerRow[0], 'Нетто') !== false)) {
                    // Weight header
                    $styles["A{$row}:B{$row}"] = [
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF0F0F0']
                        ]
                    ];
                } elseif (!empty($stickerRow[0]) && is_numeric($stickerRow[0])) {
                    // Weight values
                    $styles["A{$row}:B{$row}"] = [
                        'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial'],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THICK]
                        ]
                    ];
                }

                $row++;
            }

            // Har bir sticker uchun umumiy border
            $stickerEndRow = $row - 1;
            $styles["A{$stickerStartRow}:B{$stickerEndRow}"] = [
                'borders' => [
                    'outline' => ['borderStyle' => Border::BORDER_THICK]
                ]
            ];
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                if ($this->imagePath && file_exists($this->imagePath)) {
                    $row = 1;

                    foreach ($this->stickers as $index => $sticker) {
                        if ($index > 0) {
                            $row += 2; // Bo'sh qatorlar
                        }

                        // Rasm A1 katakchaga logo sifatida
                        $drawing = new Drawing();
                        $drawing->setName('NIKASTYLE_LOGO');
                        $drawing->setDescription('NIKASTYLE Logo');
                        $drawing->setPath($this->imagePath);
                        $drawing->setHeight(20);
                        $drawing->setWidth(20);
                        $drawing->setCoordinates('A' . $row);
                        $drawing->setOffsetX(5);
                        $drawing->setOffsetY(5);
                        $drawing->setWorksheet($sheet);

                        $row += count($sticker);
                    }
                }
            }
        ];
    }
}
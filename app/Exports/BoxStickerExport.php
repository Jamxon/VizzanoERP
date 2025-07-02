<?php

// App\Exports\BoxStickerExport.php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithRowHeight;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

class BoxStickerExport implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithRowHeight
{
    protected array $stickers;

    public function __construct(array $stickers)
    {
        $this->stickers = $stickers;
    }

    public function array(): array
    {
        $result = [];
        $currentRow = 1;

        foreach ($this->stickers as $stickerIndex => $sticker) {
            // Har bir sticker uchun bo'sh qator qo'shish (birinchisidan tashqari)
            if ($stickerIndex > 0) {
                $result[] = ['', ''];
                $currentRow++;
            }

            foreach ($sticker as $row) {
                $result[] = $row;
                $currentRow++;
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
            'A' => 20,
            'B' => 15,
        ];
    }

    public function rowHeight(): array
    {
        return [
            1 => 25, // Header row height
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];
        $currentRow = 1;

        foreach ($this->stickers as $stickerIndex => $sticker) {
            // Har bir sticker uchun bo'sh qator (birinchisidan tashqari)
            if ($stickerIndex > 0) {
                $currentRow++;
            }

            $stickerStartRow = $currentRow;

            foreach ($sticker as $rowIndex => $row) {
                if ($rowIndex == 0) {
                    // Header (Костюм для девочки)
                    $styles["A{$currentRow}:B{$currentRow}"] = [
                        'font' => ['bold' => true, 'size' => 14],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK]],
                    ];
                } elseif (in_array($rowIndex, [2, 3])) {
                    // Арт va Цвет qatorlari
                    $styles["A{$currentRow}:B{$currentRow}"] = [
                        'font' => ['bold' => true, 'size' => 12],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
                    ];
                } elseif ($rowIndex == 4) {
                    // Size header (Размер, Количество)
                    $styles["A{$currentRow}:B{$currentRow}"] = [
                        'font' => ['bold' => true, 'size' => 11],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE0E0E0']
                        ]
                    ];
                } elseif (!empty($row[0]) && !empty($row[1]) && is_numeric($row[1])) {
                    // Size data rows
                    $styles["A{$currentRow}:B{$currentRow}"] = [
                        'font' => ['size' => 10],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ];
                } elseif (isset($row[0]) && ($row[0] == 'Нетто(кг)' || strpos($row[0], 'Нетто') !== false)) {
                    // Weight header
                    $styles["A{$currentRow}:B{$currentRow}"] = [
                        'font' => ['bold' => true, 'size' => 10],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF0F0F0']
                        ]
                    ];
                } elseif (!empty($row[0]) && is_numeric($row[0])) {
                    // Weight values
                    $styles["A{$currentRow}:B{$currentRow}"] = [
                        'font' => ['bold' => true, 'size' => 12],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK]],
                    ];
                }

                $currentRow++;
            }

            // Har bir sticker uchun umumiy border
            $stickerEndRow = $currentRow - 1;
            $styles["A{$stickerStartRow}:B{$stickerEndRow}"] = [
                'borders' => [
                    'outline' => ['borderStyle' => Border::BORDER_THICK]
                ]
            ];
        }

        return $styles;
    }
}
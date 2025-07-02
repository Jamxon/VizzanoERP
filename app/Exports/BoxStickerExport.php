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
    protected ?string $submodel;

    public function __construct(array $stickers, ?string $imagePath = null, ?string $submodel = null)
    {
        $this->stickers = $stickers;
        $this->imagePath = $imagePath;
        $this->submodel = $submodel ?? '';
    }

    public function array(): array
    {
        $result = [];

        foreach ($this->stickers as $index => $sticker) {
            if ($index > 0) {
                $result[] = ['', ''];
                $result[] = ['', ''];
            }

            // 1-qator: Logo (A), Upakovka No (B)
            $result[] = ['', ($index + 1)];

            // 2-qator: Submodel
            $result[] = [$this->submodel];

            // 3+ qatorlar: sticker contents
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
            'B' => 30,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];
        $row = 1;

        foreach ($this->stickers as $index => $sticker) {
            if ($index > 0) {
                $row += 2;
            }

            // Upakovka No
            $styles["B{$row}"] = [
                'font' => ['bold' => true, 'size' => 13],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ];
            $row++;

            // Submodel
            $styles["B{$row}"] = [
                'font' => ['italic' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ];
            $row++;

            foreach ($sticker as $i => $stickerRow) {
                if ($i == 0) {
                    // Header (e.g. Костюм для девочки)
                    $styles["A{$row}:B{$row}"] = [
                        'font' => ['bold' => true, 'size' => 12],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
                    ];
                } elseif (in_array($i, [2, 3])) {
                    // Арт va Цвет
                    $styles["A{$row}"] = [
                        'font' => ['bold' => true],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ];
                    $styles["B{$row}"] = [
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ];
                } elseif ($i == 4) {
                    // Размер, Количество
                    $styles["A{$row}:B{$row}"] = [
                        'font' => ['bold' => true],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE0E0E0']
                        ],
                    ];
                } elseif (isset($stickerRow[0]) && strpos($stickerRow[0], '-') !== false) {
                    // Size rows
                    $styles["A{$row}:B{$row}"] = [
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ];
                } elseif (isset($stickerRow[0]) && str_contains($stickerRow[0], 'Нетто')) {
                    // Нетто/Брутто Header
                    $styles["A{$row}:B{$row}"] = [
                        'font' => ['bold' => true],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF0F0F0']
                        ],
                    ];
                } elseif (!empty($stickerRow[0]) && is_numeric($stickerRow[0])) {
                    // Weight values
                    $styles["A{$row}:B{$row}"] = [
                        'font' => ['bold' => true],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK]],
                    ];
                }

                $row++;
            }
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $row = 1;

                foreach ($this->stickers as $index => $sticker) {
                    if ($index > 0) {
                        $row += 2;
                    }

                    if ($this->imagePath && file_exists($this->imagePath)) {
                        $drawing = new Drawing();
                        $drawing->setName('Logo');
                        $drawing->setPath($this->imagePath);
                        $drawing->setHeight(50);
                        $drawing->setWidth(100);
                        $drawing->setCoordinates('A' . $row);
                        $drawing->setOffsetX(5);
                        $drawing->setOffsetY(2);
                        $drawing->setWorksheet($sheet);
                    }

                    $row += count($sticker) + 2; // Sticker rows + 2 header
                }
            }
        ];
    }
}

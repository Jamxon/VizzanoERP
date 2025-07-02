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
                $result[] = [''];
                $result[] = [''];
            }

            // 4 qator logo uchun bo‘sh
            $result[] = [''];
            $result[] = [''];
            $result[] = [''];
            $result[] = [''];

            // 2 qator submodel uchun
            $result[] = [$this->submodel];
            $result[] = [''];

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
            'A' => 13,
            'B' => 9,
            'C' => 9,
            'D' => 9,
            'E' => 9,
            'F' => 9,
            'G' => 9,
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

            // Logo
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->mergeCells("F{$row}:G{$row}");
            $styles["F{$row}"] = [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ];
            $row += 4;

            // Submodel
            $sheet->mergeCells("A{$row}:G{$row}");
            $styles["A{$row}"] = [
                'font' => ['italic' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ];
            $row += 2;

            foreach ($sticker as $i => $stickerRow) {
                if ($i == 0) {
                    $sheet->mergeCells("A{$row}:G{$row}");
                    $styles["A{$row}"] = [
                        'font' => ['bold' => true, 'size' => 12],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
                    ];
                } elseif (in_array($i, [2, 3])) {
                    $sheet->mergeCells("B{$row}:G{$row}");
                    $styles["A{$row}"] = ['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]];
                    $styles["B{$row}"] = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]];
                } elseif ($i == 4) {
                    $sheet->mergeCells("A{$row}:C{$row}");
                    $sheet->mergeCells("E{$row}:G{$row}");
                    $styles["A{$row}"] = $styles["E{$row}"] = [
                        'font' => ['bold' => true],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
                    ];
                } elseif (isset($stickerRow[0]) && strpos($stickerRow[0], '-') !== false) {
                    $styles["A{$row}:C{$row}"] = [
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ];
                } elseif (isset($stickerRow[0]) && str_contains($stickerRow[0], 'Нетто')) {
                    $sheet->mergeCells("E{$row}:G{$row}");
                    $styles["E{$row}"] = [
                        'font' => ['bold' => true],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F0F0']],
                    ];
                } elseif (!empty($stickerRow[0]) && is_numeric($stickerRow[0])) {
                    $styles["E{$row}:G{$row}"] = [
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

                    // Logo: 4 qator
                    for ($i = 0; $i < 4; $i++) {
                        $sheet->getRowDimension($row + $i)->setRowHeight(15);
                    }

                    if ($this->imagePath && file_exists($this->imagePath)) {
                        $drawing = new Drawing();
                        $drawing->setName('Logo');
                        $drawing->setPath($this->imagePath);
                        $drawing->setHeight(60);
                        $drawing->setWidth(320);
                        $drawing->setCoordinates('A' . $row);
                        $drawing->setOffsetX(5);
                        $drawing->setOffsetY(0);
                        $drawing->setWorksheet($sheet);
                    }

                    $row += 4;

                    // Submodel: 2 qator
                    for ($i = 0; $i < 2; $i++) {
                        $sheet->getRowDimension($row)->setRowHeight(15);
                        $row++;
                    }

                    // Maxsus qatorlar (Kostyum, art, rang, razmer)
                    $sheet->getRowDimension($row++)->setRowHeight(25); // Kostyum
                    $sheet->getRowDimension($row++)->setRowHeight(18); // Art
                    $sheet->getRowDimension($row++)->setRowHeight(18); // Rang
                    $sheet->getRowDimension($row++)->setRowHeight(20); // Razmer sarlavha

                    foreach ($sticker as $r) {
                        if (isset($r[0]) && strpos($r[0], '-') !== false) {
                            $sheet->getRowDimension($row++)->setRowHeight(20);
                        } elseif (isset($r[0]) && str_contains($r[0], 'Нетто')) {
                            $sheet->getRowDimension($row++)->setRowHeight(22);
                        } elseif (!empty($r[0]) && is_numeric($r[0])) {
                            $sheet->getRowDimension($row++)->setRowHeight(24);
                        }
                    }
                }
            }
        ];
    }
}

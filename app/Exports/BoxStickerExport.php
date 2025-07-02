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
                $result[] = [''];
                $result[] = [''];
            }

            // ⚠️ 1–4 qatorlar: LOGO uchun bo‘sh
            $result[] = [''];
            $result[] = [''];
            $result[] = [''];
            $result[] = [''];

            // ⚠️ 5–6 qatorlar: Submodel
            $result[] = [$this->submodel];
            $result[] = [''];

            // 7+ qatorlar: sticker ma’lumotlari
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
            if ($index > 0) $row += 2;

            // Row 1: logo (A-E), upakovka no (F-G)
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->mergeCells("F{$row}:G{$row}");

            $styles["F{$row}"] = [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ];
            $row++;

            // Row 2: submodel
            $sheet->mergeCells("A{$row}:G{$row}");
            $styles["A{$row}"] = [
                'font' => ['italic' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ];
            $row++;

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
                        $row += 2; // Oraliq bo‘sh qator
                    }

                    // 1. Logo uchun 4 qator
                    for ($i = 0; $i < 4; $i++) {
                        $sheet->getRowDimension($row + $i)->setRowHeight(15); // 15x4 = 60px
                    }

                    // Logo joylashuvi
                    if ($this->imagePath && file_exists($this->imagePath)) {
                        $drawing = new Drawing();
                        $drawing->setName('Logo');
                        $drawing->setPath($this->imagePath);
                        $drawing->setHeight(60);
                        $drawing->setWidth(320);
                        $drawing->setCoordinates('A' . $row);
                        $drawing->setOffsetX(5);
                        $drawing->setOffsetY(0); // 0 yoki 2 bo‘lishi mumkin
                        $drawing->setWorksheet($sheet);
                    }

                    $row += 4;

                    // 2. Submodel: 2 qator
                    for ($i = 0; $i < 2; $i++) {
                        $sheet->getRowDimension($row)->setRowHeight(15);
                        $row++;
                    }

                    // 3. Костюм (product name)
                    $sheet->getRowDimension($row)->setRowHeight(25);
                    $row++;

                    // 4. Art
                    $sheet->getRowDimension($row)->setRowHeight(18);
                    $row++;

                    // 5. Rang
                    $sheet->getRowDimension($row)->setRowHeight(18);
                    $row++;

                    // 6. Razmer header
                    $sheet->getRowDimension($row)->setRowHeight(20);
                    $row++;

                    // 7. Razmerlar (N qator)
                    foreach ($sticker as $r) {
                        if (isset($r[0]) && strpos($r[0], '-') !== false) {
                            $sheet->getRowDimension($row)->setRowHeight(20);
                            $row++;
                        } elseif (isset($r[0]) && str_contains($r[0], 'Нетто')) {
                            $sheet->getRowDimension($row)->setRowHeight(22);
                            $row++;
                        } elseif (!empty($r[0]) && is_numeric($r[0])) {
                            $sheet->getRowDimension($row)->setRowHeight(24);
                            $row++;
                        }
                    }
                }
            }
        ];
    }

}

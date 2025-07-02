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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

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
        $currentRow = 1;

        foreach ($this->stickers as $stickerIndex => $sticker) {
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

    public function styles(Worksheet $sheet): array
    {
        $styles = [];
        $currentRow = 1;

        foreach ($this->stickers as $stickerIndex => $sticker) {
            if ($stickerIndex > 0) {
                $currentRow++;
            }

            $stickerStartRow = $currentRow;

            foreach ($sticker as $rowIndex => $row) {
                if ($rowIndex == 0) {
                    $styles["A{$currentRow}:B{$currentRow}"] = [
                        'font' => ['bold' => true, 'size' => 14],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK]],
                    ];
                } elseif (in_array($rowIndex, [2, 3])) {
                    $styles["A{$currentRow}:B{$currentRow}"] = [
                        'font' => ['bold' => true, 'size' => 12],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
                    ];
                } elseif ($rowIndex == 4) {
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
                    $styles["A{$currentRow}:B{$currentRow}"] = [
                        'font' => ['size' => 10],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ];
                } elseif (isset($row[0]) && ($row[0] == 'Нетто(кг)' || strpos($row[0], 'Нетто') !== false)) {
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
                    $styles["A{$currentRow}:B{$currentRow}"] = [
                        'font' => ['bold' => true, 'size' => 12],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK]],
                    ];
                }

                $currentRow++;
            }

            $stickerEndRow = $currentRow - 1;
            $styles["A{$stickerStartRow}:B{$stickerEndRow}"]['borders']['outline'] = ['borderStyle' => Border::BORDER_THICK];
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowOffset = 0;

                foreach ($this->stickers as $index => $sticker) {
                    if ($index > 0) {
                        $rowOffset++;
                    }

                    if ($this->imagePath && file_exists($this->imagePath)) {
                        $drawing = new Drawing();
                        $drawing->setPath($this->imagePath);
                        $drawing->setHeight(40);
                        $drawing->setWidth(140);
                        $drawing->setCoordinates('A' . ($rowOffset + 1));
                        $drawing->setWorksheet($sheet);

                        $sheet->insertNewRowBefore($rowOffset + 1, 5);
                        $rowOffset += 5;
                    }

                    $rowOffset += count($sticker);
                }
            }
        ];
    }
}

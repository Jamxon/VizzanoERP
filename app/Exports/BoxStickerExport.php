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

            // ✅ A1:G4 birlashtirish
            $sheet->mergeCells("A{$row}:G" . ($row + 3));
            $styles["A{$row}"] = [
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
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
                // qolgan qatorlarga style
                // (sizda bor kodi o‘zgarishsiz qoladi)
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

                    // ✅ 4 qatorni birlashtirib, rasmga balandlik beramiz
                    for ($i = 0; $i < 4; $i++) {
                        $sheet->getRowDimension($row + $i)->setRowHeight(15);
                    }

                    if ($this->imagePath && file_exists($this->imagePath)) {
                        $drawing = new Drawing();
                        $drawing->setName('Logo');
                        $drawing->setPath($this->imagePath);
                        $drawing->setHeight(60); // 4 qator = 15x4
                        $drawing->setWidth(320);
                        $drawing->setCoordinates('A' . $row);
                        $drawing->setOffsetX(5);
                        $drawing->setOffsetY(0);
                        $drawing->setWorksheet($sheet);
                    }

                    $row += 4;

                    // Submodel qatorlari
                    for ($i = 0; $i < 2; $i++) {
                        $sheet->getRowDimension($row++)->setRowHeight(15);
                    }

                    // Maxsus qatorlar
                    $sheet->getRowDimension($row++)->setRowHeight(25); // Kostyum
                    $sheet->getRowDimension($row++)->setRowHeight(18); // Art
                    $sheet->getRowDimension($row++)->setRowHeight(18); // Rang
                    $sheet->getRowDimension($row++)->setRowHeight(20); // Razmer header

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

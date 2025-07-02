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
    protected ?string $submodel;
    protected ?string $upakovka;

    public function __construct(array $stickers, ?string $imagePath = null, ?string $submodel = null, ?string $upakovka = null)
    {
        $this->stickers = $stickers;
        $this->imagePath = $imagePath;
        $this->submodel = $submodel ?? '';
        $this->upakovka = $upakovka ?? '';
    }

    public function array(): array
    {
        $result = [];

        foreach ($this->stickers as $index => $sticker) {
            if ($index > 0) {
                $result[] = ['', '']; // oraliq bo‘sh qator
            }

            // Rasm va Upakovka bitta qatorda joylashadi (rasm A ustunda, matn B ustunda)
            $result[] = ['', 'Upakovka №: ' . ($index + 1)];
            $result[] = ['', 'Submodel: ' . $this->submodel];

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
            'A' => 20,
            'B' => 30,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];
        $row = 1;

        foreach ($this->stickers as $index => $sticker) {
            if ($index > 0) {
                $row++;
            }

            // Rasm + Upakovka
            $styles["B{$row}"] = [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ];
            $row++;

            // Submodel qatori
            $styles["B{$row}"] = [
                'font' => ['italic' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ];
            $row++;

            foreach ($sticker as $stickerRow) {
                $row++;
            }
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $row = 1;

                foreach ($this->stickers as $index => $sticker) {
                    if ($index > 0) {
                        $row++;
                    }

                    // Rasm A ustunida joylashadi
                    if ($this->imagePath && file_exists($this->imagePath)) {
                        $drawing = new Drawing();
                        $drawing->setPath($this->imagePath);
                        $drawing->setHeight(60);
                        $drawing->setWidth(135);
                        $drawing->setCoordinates('A' . $row);
                        $drawing->setOffsetX(10);
                        $drawing->setOffsetY(5);
                        $drawing->setWorksheet($sheet);
                    }

                    $row += 2; // Upakovka va Submodel
                    $row += count($sticker); // Sticker rows
                }
            }
        ];
    }
}

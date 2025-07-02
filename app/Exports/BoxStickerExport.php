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
                        $row += 2; // Har bir sticker orasida 2 ta bo‘sh qator
                    }

                    // 1️⃣ Logo uchun 4 qator (qator 1–4)
                    for ($i = 0; $i < 4; $i++) {
                        $sheet->getRowDimension($row + $i)->setRowHeight(15); // 4 × 15 = 60px
                    }

                    // Logo rasm
                    if ($this->imagePath && file_exists($this->imagePath)) {
                        $drawing = new Drawing();
                        $drawing->setName('Logo');
                        $drawing->setPath($this->imagePath);
                        $drawing->setHeight(60); // 4 qatorni egallaydi
                        $drawing->setWidth(320); // A–E ustunlar oralig‘i
                        $drawing->setCoordinates('A' . $row); // 1-qatordan joylashadi
                        $drawing->setOffsetX(5);
                        $drawing->setOffsetY(0);
                        $drawing->setWorksheet($sheet);
                    }

                    $row += 4;

                    // 2️⃣ Submodel uchun 2 qator (qator 5–6)
                    for ($i = 0; $i < 2; $i++) {
                        $sheet->getRowDimension($row)->setRowHeight(15);
                        $row++;
                    }

                    // 3️⃣ Костюм yoki mahsulot nomi (qator 7)
                    $sheet->getRowDimension($row)->setRowHeight(25);
                    $row++;

                    // 4️⃣ Art (qator 8)
                    $sheet->getRowDimension($row)->setRowHeight(18);
                    $row++;

                    // 5️⃣ Rang (qator 9)
                    $sheet->getRowDimension($row)->setRowHeight(18);
                    $row++;

                    // 6️⃣ Размер/Количество sarlavha (qator 10)
                    $sheet->getRowDimension($row)->setRowHeight(20);
                    $row++;

                    // 7️⃣ Razmer, Netto/Brutto, Qiymatlar (qator 11+)
                    foreach ($sticker as $r) {
                        if (isset($r[0]) && strpos($r[0], '-') !== false) {
                            // Razmerlar
                            $sheet->getRowDimension($row)->setRowHeight(20);
                            $row++;
                        } elseif (isset($r[0]) && str_contains($r[0], 'Нетто')) {
                            // Netto/Brutto Header
                            $sheet->getRowDimension($row)->setRowHeight(22);
                            $row++;
                        } elseif (!empty($r[0]) && is_numeric($r[0])) {
                            // Qiymatlar
                            $sheet->getRowDimension($row)->setRowHeight(24);
                            $row++;
                        }
                    }
                }
            }
        ];
    }

}

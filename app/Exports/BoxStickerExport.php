<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BoxStickerExport implements FromArray, WithTitle, WithStyles
{
    protected array $stickers;

    public function __construct(array $stickers)
    {
        $this->stickers = $stickers;
    }

    public function array(): array
    {
        return $this->stickers;
    }

    public function title(): string
    {
        return 'Yorliq';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Stilni bu yerga yozasiz (fontsize, align, bold, borders)
        ];
    }
}

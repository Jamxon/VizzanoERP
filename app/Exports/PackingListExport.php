<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PackingListExport implements FromArray, WithHeadings, WithColumnWidths, WithStyles
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            '№',
            'Модель',
            'Размер',
            'Имя',
            '№ упаковки',
            'кол-во мест',
            'кол-во в упаковке (шт)',
            'Вес нетто (кг)',
            'Вес брутто (кг)',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 30,
            'C' => 12,
            'D' => 20,
            'E' => 10,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 12,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 1; // +1 because headings are at row 1

        // Range from A1 to I[lastRow]
        $sheet->getStyle("A1:I$lastRow")->getAlignment()->setHorizontal('center');

        return [];
    }
}

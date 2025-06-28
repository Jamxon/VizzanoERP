<?php

// app/Exports/PackingListExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class PackingListExport implements FromArray, WithHeadings, WithColumnWidths
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

    public function columnWidths(): array
    {
        return [
            'A' => 10,   // №
            'B' => 30,  // Модель
            'C' => 12,  // Размер
            'D' => 20,  // Имя
            'E' => 10,  // № упаковки
            'F' => 12,  // кол-во мест
            'G' => 12,  // кол-во в упаковке
            'H' => 12,  // Вес нетто
            'I' => 12,  // Вес брутто
        ];
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
}

<?php

// app/Exports/PackingListExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PackingListExport implements FromArray, WithHeadings
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
            '№', 'Модель', 'Размер', 'Имя', '№ упаковки', 'кол-во мест', 'кол-во в упаковке (шт)', 'Вес нетто (кг)', 'Вес брутто (кг)'
        ];
    }
}

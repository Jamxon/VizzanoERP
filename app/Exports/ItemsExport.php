<?php

namespace App\Exports;

use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Events\AfterSheet;

class ItemsExport implements FromCollection, WithHeadings, WithDrawings, WithEvents
{
    public function collection()
    {
        return Item::join('units', 'items.unit_id', '=', 'units.id')
            ->join('colors', 'items.color_id', '=', 'colors.id')
            ->join('item_types', 'items.type_id', '=', 'item_types.id')
            ->select([
                'items.id as №',
                'items.name as Nomi',
                DB::raw("'' as Rasmi"), // Rasmlar uchun ustun bo‘sh qoldiriladi
                'items.code as Kodi',
                'units.name as Birligi',
                'items.price as Narxi',
                'colors.name as Rangi',
                'item_types.name as Turi',
            ])
            ->get();
    }

    public function headings(): array
    {
        return [
            '№',
            'Nomi',
            'Rasmi', // Rasmlar uchun bo‘sh ustun
            'Kodi',
            'Birligi',
            'Narxi ($)',
            'Rangi',
            'Turi',
        ];
    }

    public function drawings(): array
    {
        $drawings = [];
        $items = Item::all();

        foreach ($items as $index => $item) {

            if (!$item->image) continue;

            $imagePath = public_path('storage/' . $item->image_raw);

            if (file_exists($imagePath)) {
                $drawing = new Drawing();
                $drawing->setPath($imagePath);
                $drawing->setHeight(80); // Rasm balandligi
                $drawing->setWidth(80);  // Rasm kengligi
                $drawing->setCoordinates('C' . ($index + 2)); // Excel koordinatalari (C ustuni)
                $drawings[] = $drawing;
            }
        }

        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Ustun kengliklarini sozlash
                $sheet->getColumnDimension('A')->setWidth(10);  // Column A
                $sheet->getColumnDimension('B')->setWidth(25);  // Column B
                $sheet->getColumnDimension('C')->setWidth(20);  // Column C
                $sheet->getColumnDimension('D')->setWidth(20);  // Column D
                $sheet->getColumnDimension('E')->setWidth(15);  // Column E
                $sheet->getColumnDimension('F')->setWidth(15);  // Column F
                $sheet->getColumnDimension('G')->setWidth(15);  // Column G
                $sheet->getColumnDimension('H')->setWidth(15);  // Column H

                // Har bir qatorning balandligini sozlash
                $rowCount = Item::count() + 1; // Barcha qatorlar soni (header + ma'lumotlar)
                for ($row = 2; $row <= $rowCount; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(60); // Qator balandligi
                }

                // Matnni o‘rtaga joylashtirish
                $sheet->getStyle('A1:H' . $rowCount)->getAlignment()->applyFromArray([
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ]);

                // Headerlar uchun qo'shimcha dizayn
                $sheet->getStyle('A1:H1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                    ],
                ]);
            },
        ];
    }
}

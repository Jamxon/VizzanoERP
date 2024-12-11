<?php
namespace App\Exports;

use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ItemsExport implements FromCollection, WithHeadings, WithDrawings
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
            if (str_starts_with($item->image, 'images/')) {
                $imagePath = public_path('storage/' . $item->image);
            } elseif (str_starts_with($item->image, 'rasmlar/')) {
                $remoteImageUrl = 'http://192.168.0.117:2004/media/' . $item->image;
                $tempImagePath = storage_path('app/public/images/' . basename($item->image));

                // URL orqali rasmni yuklab olish
                if (!File::exists($tempImagePath)) {
                    // Agar fayl mavjud bo'lmasa, rasmni yuklab olish
                    file_put_contents($tempImagePath, file_get_contents($remoteImageUrl));
                }

                $imagePath = $tempImagePath;
            } else {
                continue;
            }

            // Fayl mavjudligini tekshirish
            if (file_exists($imagePath)) {
                $drawing = new Drawing();
                $drawing->setPath($imagePath); // To‘g‘ri rasm yo‘li
                $drawing->setHeight(30); // Rasm balandligi
                $drawing->setWidth(30); //
                $drawing->setCoordinates('C' . ($index + 2)); // Exceldagi koordinatalar (C ustuni)
                $drawings[] = $drawing;
            }
        }

        return $drawings;
    }

    public function sheet(Spreadsheet $spreadsheet)
    {
        // Access the active sheet
        $sheet = $spreadsheet->getActiveSheet();

        // Set column width
        $sheet->getColumnDimension('A')->setWidth(100);  // Column A
        $sheet->getColumnDimension('B')->setWidth(200);  // Column B
        $sheet->getColumnDimension('C')->setWidth(300);  // Column C
        $sheet->getColumnDimension('D')->setWidth(150);  // Column D
        $sheet->getColumnDimension('E')->setWidth(150);  // Column E
        $sheet->getColumnDimension('F')->setWidth(150);  // Column F
        $sheet->getColumnDimension('G')->setWidth(150);  // Column G
        $sheet->getColumnDimension('H')->setWidth(150);  // Column H

        // Set row height
        for ($row = 2; $row <= count($this->collection()) + 1; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(40);  // Set height of each row
        }
    }
}

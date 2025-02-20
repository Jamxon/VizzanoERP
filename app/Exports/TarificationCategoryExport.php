<?php

namespace App\Exports;

use App\Models\TarificationCategory;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TarificationCategoryExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        // TarificationCategory bilan bog'liq tarificationlarni yuklab olish
        return TarificationCategory::with('tarifications')->get()->map(function($category) {
            // Agar siz tarificationlarni bitta satrga qo'shmoqchi bo'lsangiz, ularni vergul bilan ajrating
            $tarifications = $category->tarifications->pluck('name')->implode(', ');
            return [
                'id' => $category->id,
                'name' => $category->name,
                'tarifications' => $tarifications,
            ];
        });
    }

    public function headings(): array
    {
        return ['ID', 'Category Name', 'Tarifications'];
    }
}

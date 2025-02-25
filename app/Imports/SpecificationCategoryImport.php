<?php

namespace App\Imports;

use App\Models\PartSpecification;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use App\Models\SpecificationCategory;

class SpecificationCategoryImport implements ToCollection
{
    /**
     * Excel fayldan olingan qatorlar ustida ishlash.
     *
     * Strukturasi:
     * - Qator: Faqat bitta notekis hujayra — kategoriya nomi.
     * - Qator: Header (code, name, quantity, comment) — o'tkazib yuboriladi.
     * - Qatorlar: Shu kategoriyaga tegishli specification maʼlumotlari.
     *
     * @param Collection $rows
     * @return void
     */
    public function collection(Collection $rows): void
    {
        $currentCategory = null;

        foreach ($rows as $rowIndex => $row) {
            // Qator bo'sh bo'lsa o'tkazib yuborish
            if ($row->filter()->isEmpty()) {
                continue;
            }

            // Agar qatorda faqat bitta notekis hujayra bo'lsa, bu kategoriya nomi
            if ($row->filter()->count() === 1) {
                $categoryName = trim($row[0]);
                // Kategoriya mavjudligini tekshiramiz yoki yangi yaratiladi
                $currentCategory = SpecificationCategory::updateOrCreate(
                    ['name' => $categoryName],
                    ['name' => $categoryName]
                );
                continue;
            }

            // Agar qator header bo'lsa (ya'ni birinchi ustun "code" bo'lsa), uni o'tkazib yuboramiz
            if (strtolower(trim($row[0])) === 'code') {
                continue;
            }

            // Endi bu qator specification maʼlumotlari sifatida qabul qilinadi
            if ($currentCategory) {
                PartSpecification::updateOrCreate(
                    [
                        'specification_category_id' => $currentCategory->id,
                        'code' => trim($row[0]),
                    ],
                    [
                        'name'     => trim($row[1]),
                        'quantity' => trim($row[2]),
                        'comment'  => trim($row[3]),
                    ]
                );
            }
        }
    }
}

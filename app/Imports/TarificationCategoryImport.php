<?php

namespace App\Imports;

use App\Models\TarificationCategory;
use App\Models\Tarification;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class TarificationCategoryImport implements ToCollection
{
    protected $orderSubModelId;

    /**
     * Import uchun orderSubModel ID sini qabul qiladi.
     *
     * @param mixed $orderSubModelId
     */
    public function __construct($orderSubModelId)
    {
        $this->orderSubModelId = $orderSubModelId;
    }

    /**
     * Excel faylning barcha qatorlarini collection() orqali qaytaradi.
     *
     * Fayl strukturasiga mos:
     * - Birinchi qator: kategoriya nomi (merged hujayra, faqat A ustunda qiymat bo'ladi).
     * - Ikkinchi qator: ustun nomlari (code, employee, name, razryad, typewriter, second, summa).
     * - Keyingi qatorlar: tarification yozuvlari.
     *
     * Yangi kategoriya paydo bo'lganda, yangi TarificationCategory yaratiladi.
     *
     * @param Collection $rows
     * @return void
     */
    public function collection(Collection $rows): void
    {
        $currentCategoryId = null;
        $skipHeader = false; // Har bir kategoriya blokidagi ustun nomlari qatorini o'tkazish uchun

        foreach ($rows as $row) {
            // Agar qator bo'sh bo'lsa, o'tkazamiz
            if (empty(array_filter($row, function($value) {
                return !is_null($value) && trim($value) !== '';
            }))) {
                continue;
            }

            // Agar qator faqat bitta not-empty hujayrani o'z ichiga olsa (merged header - kategoriya nomi)
            $nonEmptyCount = count(array_filter($row, function ($value) {
                return !is_null($value) && trim($value) !== '';
            }));
            if ($nonEmptyCount === 1 && !empty($row[0])) {
                $categoryName = trim($row[0]);
                $category = TarificationCategory::create([
                    'order_sub_model_id' => $this->orderSubModelId,
                    'name' => $categoryName,
                ]);
                $currentCategoryId = $category->id;
                $skipHeader = true; // Keyingi qator – ustun nomlari, o'tkazib yuboramiz
                continue;
            }

            // Agar skipHeader flagi faollashtirilgan bo'lsa, bu ustun nomlari qatori bo'lib, o'tkazamiz
            if ($skipHeader) {
                $skipHeader = false;
                continue;
            }

            // Endi bu qator tarification yozuvi hisoblanadi.
            // Taxminiy ustun tartibi:
            // 0: code, 1: employee, 2: name, 3: razryad, 4: typewriter, 5: second, 6: summa
            if ($currentCategoryId) {
                Tarification::create([
                    'tarification_category_id' => $currentCategoryId,
                    'code'    => $row[0] ?? null,
                    'employee'=> $row[1] ?? null,
                    'name'    => $row[2] ?? null,
                    'razryad' => $row[3] ?? null,
                    'typewriter' => $row[4] ?? null,
                    'second'  => $row[5] ?? null,
                    'summa'   => $row[6] ?? null,
                ]);
            }
        }
    }
}

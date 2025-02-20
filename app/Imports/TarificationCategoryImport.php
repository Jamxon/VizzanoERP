<?php

namespace App\Imports;

use App\Models\Razryad;
use App\Models\TarificationCategory;
use App\Models\Tarification;
use App\Models\TypeWriter;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class TarificationCategoryImport implements ToCollection
{
    protected $orderSubModelId;

    public function __construct($orderSubModelId)
    {
        $this->orderSubModelId = $orderSubModelId;
    }

    public function collection(Collection $rows): void
    {
        $currentCategoryId = null;
        $skipHeader = false; // Har bir kategoriya blokidagi ustun nomlari qatorini o'tkazish uchun

        foreach ($rows as $row) {
            if (empty(array_filter($row->toArray(), function($value) {
                return !is_null($value) && trim($value) !== '';
            }))) {
                continue;
            }

            $nonEmptyCount = count(array_filter($row->toArray(), function ($value) {
                return !is_null($value) && trim($value) !== '';
            }));

            if ($nonEmptyCount === 1 && !empty($row[0])) {
                $categoryName = trim($row[0]);
                $category = TarificationCategory::create([
                    'submodel_id' => $this->orderSubModelId,
                    'name' => $categoryName,
                ]);
                $currentCategoryId = $category->id;
                $skipHeader = true; // Keyingi qator – ustun nomlari bo'ladi
                continue;
            }

            // Ustun nomlari qatorini o'tkazamiz
            if ($skipHeader) {
                $skipHeader = false;
                continue;
            }

            $razryad = Razryad::where('name', $row[4])->first();

            $typewriter = TypeWriter::where('name', $row[5])->first();

            // Endi qator tarification yozuvi hisoblanadi.
            // Yangi tartib:
            // 0: code, 1: employee_id, 2: employee (nomi), 3: name, 4: razryad, 5: typewriter, 6: second, 7: summa
            if ($currentCategoryId) {
                Tarification::create([
                    'tarification_category_id' => $currentCategoryId,
                    'code'         => $row[0] ?? null,
                    'user_id'  => $row[1] ?? null, // eksport qilingan employee id
                    'name'         => $row[3] ?? null,
                    'razryad_id'      => $razryad->id ?? 0,
                    'typewriter_id'   => $typewriter->id ?? 0,
                    'second'       => $row[6] ?? null,
                    'summa'        => $row[7] ?? null,
                ]);
            }
        }
    }
}

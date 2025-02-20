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
            // Bo'sh qatorlarni o'tkazamiz
            if (empty(array_filter($row->toArray(), function($value) {
                return !is_null($value) && trim($value) !== '';
            }))) {
                continue;
            }

            // Agar qator faqat bitta qiymatdan iborat bo'lsa – bu kategoriya nomi (merged hujayra)
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

            // Excel fayldagi ustun tartibi:
            // 0: (eski code - endi e'tiborga olinmaydi),
            // 1: employee_id,
            // 2: employee (nomi),
            // 3: name,
            // 4: razryad (nomi),
            // 5: typewriter (nomi),
            // 6: second,
            // 7: summa

            // Razryad va typewriter ma'lumotlarini id ga o'tkazamiz
            $razryad = Razryad::where('name', $row[4])->first();
            $typewriter = TypeWriter::where('name', $row[5])->first();

            if ($currentCategoryId) {
                Tarification::create([
                    'tarification_category_id' => $currentCategoryId,
                    // Avtomatik generatsiya qilinadigan code
                    'code'         => $this->generateSequentialCode(),
                    'user_id'      => $row[1] ?? null, // eksport qilingan employee id
                    'name'         => $row[3] ?? null,
                    'razryad_id'   => $razryad->id ?? 0,
                    'typewriter_id'=> $typewriter->id ?? 0,
                    'second'       => $row[6] ?? null,
                    'summa'        => $row[7] ?? null,
                ]);
            }
        }
    }

    /**
     * Yangi tarification uchun sequential code ni generate qiladi.
     *
     * @return string
     */
    private function generateSequentialCode(): string
    {
        $lastTarification = Tarification::latest('id')->first();

        if (!$lastTarification) {
            return 'A1';
        }

        $lastCode = $lastTarification->code;
        preg_match('/([A-Z]+)(\d+)/', $lastCode, $matches);

        $letter = $matches[1] ?? 'A';
        $number = (int)($matches[2] ?? 0);
        $number++;

        if ($number > 99) {
            $number = 1;
            $letter = $this->incrementLetter($letter);
        }

        return $letter . $number;
    }

    /**
     * Harf ketma-ketligini oshiradi.
     *
     * @param string $letter
     * @return string
     */
    private function incrementLetter(string $letter): string
    {
        $length = strlen($letter);
        $incremented = false;

        for ($i = $length - 1; $i >= 0; $i--) {
            if ($letter[$i] !== 'Z') {
                $letter[$i] = chr(ord($letter[$i]) + 1);
                $incremented = true;
                break;
            }
            $letter[$i] = 'A';
        }

        if (!$incremented) {
            $letter = 'A' . $letter;
        }

        return $letter;
    }
}

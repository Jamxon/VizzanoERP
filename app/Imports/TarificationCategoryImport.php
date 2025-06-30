<?php

namespace App\Imports;

use App\Models\Razryad;
use App\Models\SubmodelSpend;
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
        $skipHeader = false;

        foreach ($rows as $row) {
            if (empty(array_filter($row->toArray(), function ($value) {
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
                $skipHeader = true;
                continue;
            }

            if ($skipHeader) {
                $skipHeader = false;
                continue;
            }

            $razryad = Razryad::where('name', $row[4])->first();
            $typewriter = TypeWriter::where('name', $row[5])->first();

            if ($currentCategoryId) {
                Tarification::create([
                    'tarification_category_id' => $currentCategoryId,
                    'code'         => $this->generateSequentialCode(),
                    'user_id'      => null,
                    'name'         => $row[3] ?? null,
                    'razryad_id'   => $razryad->id ?? 0,
                    'typewriter_id'=> $typewriter->id ?? 0,
                    'second'       => $row[6] ?? 0,
                    'summa'        => $row[7] ?? 0,
                ]);
            }
        }

        $submodelSpends = TarificationCategory::where('submodel_id', $this->orderSubModelId)
            ->with('tarifications')
            ->get();

        $totalSecond = 0;
        $totalSumma = 0;

        foreach ($submodelSpends as $category) {
            foreach ($category->tarifications as $tarification) {
                $totalSecond += $tarification->second;
                $totalSumma += $tarification->summa;
            }
        }

        SubmodelSpend::where('submodel_id', $this->orderSubModelId)->update([
            'seconds' => $totalSecond,
            'summa' => $totalSumma,
        ]);
    }

    /**
     * Yangi tarification uchun sequential code ni generate qiladi.
     *
     * @return string
     */
    private function generateSequentialCode(): string
    {
        $lastTarification = Tarification::orderByRaw("LENGTH(code) DESC, code DESC")->first();

        if (!$lastTarification) {
            return 'A1';
        }

        $lastCode = $lastTarification->code;

        preg_match('/([A-Z]+)(\d+)/', $lastCode, $matches);

        $letter = $matches[1] ?? 'A';
        $number = (int)($matches[2] ?? 0);

        $number++;

        if ($number > 999) {
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
        $i = $length - 1;

        while ($i >= 0) {
            if ($letter[$i] !== 'Z') {
                $letter[$i] = chr(ord($letter[$i]) + 1);
                return $letter;
            }

            $letter[$i] = 'A';
            $i--;
        }

        return 'A' . $letter;
    }
}

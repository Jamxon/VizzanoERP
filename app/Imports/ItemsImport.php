<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Unit;
use App\Models\Color;
use App\Models\ItemType;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ItemsImport implements ToModel, WithStartRow
{
    // 1-qatorda sarlavhalar bo‘lsa, ma'lumot 2-qatoridan boshlanadi
    public function startRow(): int
    {
        return 2;
    }

    public function model(array $row)
    {
        // Excel ustun mapping
        $name  = $row[0] ?? null; // A
        $color = $row[1] ?? null; // B
        $type  = $row[2] ?? null; // C
        // $row[3]   // D → keraksiz
        $unit  = $row[5] ?? null; // F

        if (!$name) {
            return null; // name bo‘lmasa skip
        }

        // 🔵 Unit create or get
        if ($unit) {
            $unitModel = Unit::firstOrCreate(['name' => $unit]);
        }

        // 🔵 Color create or get
        if ($color) {
            $colorModel = Color::firstOrCreate(['name' => $color]);
        }

        // 🔵 Type create or get
        if ($type) {
            $typeModel = ItemType::firstOrCreate(['name' => $type]);
        }

        return new Item([
            'name'         => $name,
            'price'        => 0,
            'unit_id'      => $unitModel->id ?? null,
            'color_id'     => $colorModel->id ?? null,
            'type_id'      => $typeModel->id ?? null,
            'code'         => Str::uuid(),
            'min_quantity' => 0,
            'branch_id'    => auth()->user()->employee->branch_id,
        ]);
    }
}
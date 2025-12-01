<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Unit;
use App\Models\Color;
use App\Models\ItemType;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (!isset($row['name']) || empty($row['name'])) {
            return null;
        }

        $unit = Unit::where('name', $row['unit'] ?? '')->first();
        if (!$unit && !empty($row['unit'])) {
            $unit = Unit::create(['name' => $row['unit']]);
        }
        $color = Color::where('name', $row['color'] ?? '')->first();
        if (!$color && !empty($row['color'])) {
            $color = Color::create(['name' => $row['color']]);
        }
        $type  = ItemType::where('name', $row['type'] ?? '')->first();
        if (!$type && !empty($row['type'])) {
            $type = ItemType::create(['name' => $row['type']]);
        }

        return new Item([
            'name'        => $row['name'],
            'price'       => 0,
            'unit_id'     => $unit?->id,
            'color_id'    => $color?->id,
            'type_id'     => $type?->id,
            'code'        => Str::uuid(),
            'min_quantity'=> 0,
            'branch_id'   => auth()->user()->employee->branch_id,
        ]);
    }
}

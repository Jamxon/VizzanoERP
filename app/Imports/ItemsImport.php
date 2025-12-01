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
    protected $userId;
    protected $branchId;

    public function __construct($userId, $branchId)
    {
        $this->userId = $userId;
        $this->branchId = $branchId;
    }

    public function startRow(): int
    {
        return 2;
    }

    public function model(array $row)
    {
        $name  = $row[0] ?? null;
        $color = $row[1] ?? null;
        $type  = $row[2] ?? null;
        $unit  = $row[5] ?? null;

        if (!$name) {
            return null;
        }

        if ($unit) {
            $unitModel = Unit::firstOrCreate(['name' => $unit]);
        }

        if ($color) {
            $colorModel = Color::firstOrCreate(['name' => $color]);
        }

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
            'branch_id'    => $this->branchId,
        ]);
    }
}
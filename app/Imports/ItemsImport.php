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
        info("ROW:", $row);

        $name  = $row[1] ?? null;  // B ustun
        $color = $row[2] ?? null;  // C ustun
        $type  = $row[3] ?? null;  // D ustun
        $unit  = $row[5] ?? null;  // F ustun (E keraksiz)

        if (!$name) {
            return null;
        }

        $unitModel  = $unit  ? Unit::firstOrCreate(['name' => $unit])  : null;
        $colorModel = $color ? Color::firstOrCreate(['name' => $color]) : null;
        $typeModel  = $type  ? ItemType::firstOrCreate(['name' => $type]) : null;

        return Item::create([
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
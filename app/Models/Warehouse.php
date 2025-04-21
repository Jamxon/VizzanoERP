<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static find($warehouseId)
 * @method static create(array $array)
 * @method static where(string $string, $branch_id)
 */
class Warehouse extends Model
{
    use HasFactory;

    protected $table = 'warehouses';

    protected $fillable = [
        'name',
        'location',
        'branch_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'branch_id',
    ];

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function stockBalances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockEntry::class);
    }


}

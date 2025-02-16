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

    public function stoks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Stok::class, 'warehouse_id');
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'warehouses_related_users', 'warehouse_id', 'user_id');
    }

}

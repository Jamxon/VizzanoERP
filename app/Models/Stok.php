<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, mixed $warehouse_id)
 */
class Stok extends Model
{
    use HasFactory;

    protected $table = 'stock';

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'quantity',
        'last_updated',
        'min_quantity'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'warehouse_id',
        'product_id'
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class,'warehouse_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class,'product_id');
    }
}

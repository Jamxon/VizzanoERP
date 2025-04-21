<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 */
class StockEntry extends Model
{
    use HasFactory;

    protected $table = 'stock_entries';

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'type',
        'source',
        'destination',
        'quantity',
        'comment',
        'created_by'
    ];

    public function item(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

}

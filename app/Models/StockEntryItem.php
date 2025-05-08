<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockEntryItem extends Model
{
    use HasFactory;

    protected $table = 'stock_entry_items';

    protected $fillable = [
        'stock_entry_id',
        'item_id',
        'quantity',
        'price',
        'currency_id'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'item_id',
        'stock_entry_id',
        'currency_id',
    ];

    public function stockEntry(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }

    public function item(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function currency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}

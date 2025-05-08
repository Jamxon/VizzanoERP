<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierOrderItem extends Model
{
    use HasFactory;

    protected $table = 'supplier_order_items';

    protected $fillable = [
        'supplier_order_id',
        'item_id',
        'quantity',
        'price',
        'currency_id',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at',
        'supplier_order_id',
        'item_id',
        'currency_id',
    ];

    public function supplierOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class);
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentItemDetail extends Model
{
    protected $fillable = ['shipment_item_id', 'order_id', 'submodel_id', 'quantity', 'comment'];

    public function shipmentItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ShipmentItem::class);
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function submodel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SubModel::class);
    }
}

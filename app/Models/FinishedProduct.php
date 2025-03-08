<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinishedProduct extends Model
{
    use HasFactory;

    protected $table = 'finished_products';

    protected $fillable = [
        'order_id',
        'quantity',
        'received_at',
        'shipped_at',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'order_id',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

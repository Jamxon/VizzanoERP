<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutcomeItemModelDistrubition extends Model
{
    use HasFactory;

    protected $table = 'outcome_item_model_distribution';

    protected $fillable = [
        'outcome_item_id',
        'model_id',
        'quantity',
        'notes'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'model_id',
        'outcome_item_id'
    ];

    public function orderModel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'model_id');
    }

    public function outcomeItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OutcomeItem::class, 'outcome_item_id');
    }
}

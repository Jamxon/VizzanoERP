<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $data)
 */
class CashboxTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashbox_id',
        'currency_id',
        'type',
        'amount',
        'date',
        'source',
        'destination',
        'via',
        'purpose',
        'comment',
        'branch_id',
    ];

    public function cashbox(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function currency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}

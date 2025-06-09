<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 */
class MonthlyExpense extends Model
{
    protected $fillable = [
        'type', 'amount', 'month',
    ];

    protected $casts = [
        'month' => 'date:Y-m',
        'amount' => 'float',
    ];
}
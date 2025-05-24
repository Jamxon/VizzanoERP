<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeVia extends Model
{
    use HasFactory;

    protected $table = 'income_via';

    protected  $fillable = [
        'name'
    ];

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CashboxTransaction::class, 'via_id');
    }
}

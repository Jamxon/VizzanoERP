<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cashbox extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function balances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CashboxBalance::class);
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CashboxTransaction::class);
    }
}

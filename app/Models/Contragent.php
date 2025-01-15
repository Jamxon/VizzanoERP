<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contragent extends Model
{
    protected $table = "contragents";

    protected $fillable = [
        'name',
        'description',
        'is_market'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'is_market'
    ];
}
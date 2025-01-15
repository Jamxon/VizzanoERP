<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contragent extends Model
{
    protected $table = "contragent";

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
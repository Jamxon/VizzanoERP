<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Normalization extends Model
{
    use HasFactory;


    protected $fillable = [
        'model_id',
        'material_name',
        'quantity',
        'unit_id',
    ];

    public function model()
    {
        return $this->belongsTo(Models::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}

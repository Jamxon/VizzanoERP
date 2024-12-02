<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Detal extends Model
{
    use HasFactory;

    protected $fillable = ['model_id', 'name'];

    public function model()
    {
        return $this->belongsTo(Models::class, 'model_id');
    }
}

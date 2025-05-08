<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelKarta extends Model
{
    use HasFactory;

    protected $fillable = ['model_id', 'material_name', 'image', 'comment'];

    public function model()
    {
        return $this->belongsTo(Models::class, 'model_id');
    }
}

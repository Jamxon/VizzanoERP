<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use mysql_xdevapi\Table;

class ModelImages extends Model
{
    use HasFactory;

    protected $table = 'model_images';

    protected $fillable = ['model_id', 'image'];

    public function model()
    {
        return $this->belongsTo(Models::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use mysql_xdevapi\Table;

/**
 * @method static create(array $array)
 */
class ModelImages extends Model
{
    use HasFactory;

    protected $table = 'model_images';

    protected $fillable = ['model_id', 'image'];

    protected $hidden = ['created_at', 'updated_at', 'model_id'];

    public function model()
    {
        return $this->belongsTo(Models::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Models extends Model
{
    use HasFactory;

    protected $table = "models";

    protected $fillable = ['name'];

    protected $with = ['submodels'];

    protected $hidden = ['created_at', 'updated_at'];

    public function submodels()
    {
        return $this->hasMany(SubModel::class, 'model_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    use HasFactory;

    protected $table = "sizes";

    protected $fillable = ['name', 'submodel_id'];

    protected $with = ['recipes'];

    protected $hidden = ['created_at', 'updated_at', 'submodel_id'];

    public function submodel()
    {
        return $this->belongsTo(Submodel::class, 'submodel_id');
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class, 'size_id');
    }
}

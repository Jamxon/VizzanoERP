<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    use HasFactory;

    protected $table = "sizes";

    protected $fillable = ['name', 'submodel_id'];

    protected $hidden = ['created_at', 'updated_at'];

    public function submodel()
    {
        return $this->belongsTo(Submodel::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }
}

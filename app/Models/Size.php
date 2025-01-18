<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    use HasFactory;

    protected $table = "sizes";

    protected $fillable = ['name', 'model_id'];


    protected $hidden = ['created_at', 'updated_at', 'model_id'];


    public function model(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Models::class, 'model_id');
    }

    public function recipes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Recipe::class, 'size_id');
    }

    public function orderSizes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderSize::class, 'size_id');
    }
}

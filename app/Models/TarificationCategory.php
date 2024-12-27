<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarificationCategory extends Model
{
    use HasFactory;

    protected $table = 'tarification_categories';

    protected $fillable = [
        'name',
        'submodel_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'submodel_id',
    ];

    public function submodel()
    {
        return $this->belongsTo(SubModel::class);
    }

    public function tarifications()
    {
        return $this->hasMany(Tarification::class);
    }
}

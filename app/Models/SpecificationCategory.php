<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpecificationCategory extends Model
{
    use HasFactory;

    protected $table = 'specification_categories';

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
        return $this->belongsTo(SubModel::class, 'submodel_id');
    }

    public function parts()
    {
        return $this->hasMany(PartSpecification::class, 'specification_category_id');
    }
}

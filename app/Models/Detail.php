<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Detail extends Model
{
    use HasFactory;

    protected $table = 'details';

    protected $fillable = [
        'submodel_id',
        'detail_category_id',
        'name',
        'razryad_id',
        'machine',
        'second',
        'summa'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'submodel_id',
        'detail_category_id',
        'razryad_id'
    ];

    public function submodel()
    {
        return $this->belongsTo(SubModel::class);
    }

    public function detailCategory()
    {
        return $this->belongsTo(DetailCategory::class);
    }

    public function razryad()
    {
        return $this->belongsTo(Razryad::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubmodelSpend extends Model
{
    use HasFactory;

    protected $table = 'submodel_spends';

    protected $fillable = [
        'submodel_id',
        'seconds',
        'summa'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'submodel_id'
    ];

    public function submodel()
    {
        return $this->belongsTo(Submodel::class);
    }
}

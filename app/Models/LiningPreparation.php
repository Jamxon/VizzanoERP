<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiningPreparation extends Model
{
    use HasFactory;

    protected $table = 'lining_preparations';

    protected $fillable = [
        'name',
        'application_id',
        'submodel_id',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $with = [ 'liningApplications'];

    public function application()
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function submodel()
    {
        return $this->belongsTo(SubModel::class, 'submodel_id');
    }

    public function liningApplications()
    {
        return $this->hasMany(LiningApplication::class, 'lining_preparation_id');
    }
}

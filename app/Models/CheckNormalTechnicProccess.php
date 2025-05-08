<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckNormalTechnicProccess extends Model
{
    use HasFactory;

    protected $table = 'check_normal_technic_proccess';

    protected $fillable = [
        'proccess_id',
        'model_id',
        'detal_id',
        'Sekund',
        'razryad_id',
        'Summa',
    ];
}

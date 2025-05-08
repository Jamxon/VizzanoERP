<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NormalTechnicProccess extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_id',
        'detal_id',
        'Sekund',
        'razryad_id',
        'Summa',
    ];
}

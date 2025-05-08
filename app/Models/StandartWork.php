<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StandartWork extends Model
{
    use HasFactory;

    protected $table = 'standart_works';

    protected $fillable = ['work_time'];
}

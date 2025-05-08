<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiningApplication extends Model
{
    use HasFactory;

    protected $table = 'lining_applications';

    protected $fillable = [
        'name',
        'lining_preparation_id',
        'razryad_id',
        'machine',
        'second',
        'summa',
        'user_id'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $with = ['razryad'];

    public function liningPreparation()
    {
        return $this->belongsTo(LiningPreparation::class,'lining_preparation_id');
    }

    public function razryad()
    {
        return $this->belongsTo(Razryad::class,'razryad_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
}

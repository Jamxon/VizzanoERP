<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tarification extends Model
{
    use HasFactory;

    protected $table = 'tarifications';

    protected $fillable = [
        'tarification_category_id',
        'user_id',
        'name',
        'razryad_id',
        'typewriter_id',
        'second',
        'summa',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'tarification_category_id',
        'user_id',
        'razryad_id',
        'typewriter_id',
    ];

    protected $with = ['tarificationCategory', 'user', 'razryad', 'typewriter'];

    public function tarificationCategory()
    {
        return $this->belongsTo(TarificationCategory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function razryad()
    {
        return $this->belongsTo(Razryad::class);
    }

    public function typewriter()
    {
        return $this->belongsTo(Typewriter::class);
    }
}

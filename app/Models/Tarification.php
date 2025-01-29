<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static latest(string $string)
 * @method static where(string $string, $id)
 * @method static find($id)
 * @method static whereIn(string $string, \Illuminate\Support\Collection $userIds)
 */
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
        'code',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'tarification_category_id',
        'user_id',
        'razryad_id',
        'typewriter_id',
    ];

    protected $with = ['employee', 'razryad', 'typewriter'];

    public function tarificationCategory()
    {
        return $this->belongsTo(TarificationCategory::class,'tarification_category_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class,'user_id');
    }

    public function razryad()
    {
        return $this->belongsTo(Razryad::class,'razryad_id');
    }

    public function typewriter()
    {
        return $this->belongsTo(TypeWriter::class,'typewriter_id');
    }
}

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
 * @method static updateOrCreate(null[] $array, array $array1)
 * @method static findOrFail(mixed $tarificationId)
 * @method static whereHas(string $string, \Closure $param)
 * @method static select(string $string, string $string1)
 * @method static without(string $string, string $string1, string $string2)
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

    public function tarificationCategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TarificationCategory::class,'tarification_category_id');
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class,'user_id');
    }

    public function razryad(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Razryad::class,'razryad_id');
    }

    public function typewriter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TypeWriter::class,'typewriter_id');
    }

    // Tarification.php
    public function tarificationLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeTarificationLog::class);
    }

}

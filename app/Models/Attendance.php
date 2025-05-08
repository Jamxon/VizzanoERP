<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static whereDate(string $string, mixed $today)
 * @method static firstOrCreate(array $array, array $array1)
 * @method static create(array $array)
 * @method static updateOrCreate(array $array, array $array1)
 */
class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'check_in_image',
        'check_out_image',
        'status',
    ];

    protected $appends = [
        'check_in_image',
        'check_out_image',
    ];

    public function getCheckInImageAttribute()
    {
        $image = $this->attributes['check_in_image'] ?? null;

        if (!$image) {
            return null;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        return url('storage/' . $image);
    }

    public function getCheckOutImageAttribute()
    {
        $image = $this->attributes['check_out_image'] ?? null;

        if (!$image) {
            return null;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        return url('storage/' . $image);
    }


    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

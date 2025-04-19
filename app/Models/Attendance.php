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
    
    public function getCheckInIMageAttribute($value): \Illuminate\Foundation\Application|string|\Illuminate\Contracts\Routing\UrlGenerator|\Illuminate\Contracts\Foundation\Application|null
    {
        if (empty($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return url('storage/' . $value);
    }

    public function getCheckOutImageAttribute($value): \Illuminate\Foundation\Application|string|\Illuminate\Contracts\Routing\UrlGenerator|\Illuminate\Contracts\Foundation\Application|null
    {
        if (empty($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return url('storage/' . $value);
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

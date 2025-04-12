<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $all)
 * @method static findOrFail($id)
 * @method static where(string $string, mixed $transport_id)
 */
class TransportAttendance extends Model
{
    use HasFactory;

    protected $table = 'transport_attendance';

    protected $fillable = [
        'transport_id',
        'date',
        'attendance_type',
        'salary',
        'fuel_bonus',
        'method',
    ];

    protected $casts = [
        'date' => 'date',
        'attendance_type' => 'double',
        'salary' => 'float',
        'fuel_bonus' => 'float',
        'method' => 'string',
    ];

    public function transport(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Transport::class);
    }
}

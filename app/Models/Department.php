<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $id)
 * @method static findOrFail($id)
 * @method static create(array $array)
 */
class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'responsible_user_id',
        'branch_id',
        'start_time',
        'end_time',
        'break_time',
    ];

    protected $hidden = ['created_at', 'updated_at','branch_id','responsible_user_id'];

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function groups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function responsibleUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function setStartTimeAttribute($value): void
    {
        $this->attributes['start_time'] = $value ? date("H:i", strtotime($value)) : null;
    }

    public function setEndTimeAttribute($value): void
    {
        $this->attributes['end_time'] = $value ? date("H:i", strtotime($value)) : null;
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}

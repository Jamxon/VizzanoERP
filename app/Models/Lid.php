<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $branch_id)
 */
class Lid extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'status',
        'comment',
        'birth_day',
        'image',
        'branch_id'
    ];

    protected $hidden = [
        'branch_id',
        'created_at',
        'updated_at',
    ];

    public function getImageAttribute($value): \Illuminate\Foundation\Application|string|\Illuminate\Contracts\Routing\UrlGenerator|\Illuminate\Contracts\Foundation\Application|null
    {
        if (empty($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return url('storage/' . $value);
    }
}

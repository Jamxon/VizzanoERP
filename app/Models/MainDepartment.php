<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $branch_id)
 * @method static create(array $only)
 */
class MainDepartment extends Model
{
    use HasFactory;

    protected $table = 'main_department';

    protected $fillable = [
        'name',
        'branch_id',
    ];

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function departments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Department::class, 'main_department_id');
    }
}

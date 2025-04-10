<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Permission;

/**
 * @method static create(array $only)
 * @method static findOrFail($id)
 */
class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description','task'];

    protected $hidden = ['created_at', 'updated_at'];

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}

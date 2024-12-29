<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'responsible_user', 'branch_id'];

    // Filialga aloqani o'rnatamiz
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }
}

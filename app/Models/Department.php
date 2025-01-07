<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'responsible_user', 'branch_id'];

    protected $hidden = ['created_at', 'updated_at','branch_id','responsible_user_id'];
    // Filialga aloqani o'rnatamiz
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}

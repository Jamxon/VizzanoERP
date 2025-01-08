<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $table = 'warehouses';

    protected $fillable = [
        'name',
        'location',
        'branch_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function stoks()
    {
        return $this->hasMany(Stok::class, 'warehouse_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'warehouses_related_users', 'warehouse_id', 'user_id');
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $table = ['warehouses'];

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
        return $this->belongsTo(Branch::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'warehouse_related_users');
    }
}

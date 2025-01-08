<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseRelatedUser extends Model
{
    use HasFactory;

    protected $table = 'warehouses_related_users';

    protected $fillable = [
        'user_id',
        'warehouse_id',
    ];
}

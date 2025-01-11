<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderGroup extends Model
{
    use HasFactory;

    protected $table = 'order_groups';

    protected $fillable = [
        'order_id',
        'submodel_id',
        'group_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'order_id',
        'submodel_id',
        'group_id',
    ];

    protected $with = ['group'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function submodel()
    {
        return $this->belongsTo(SubModel::class, 'submodel_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}

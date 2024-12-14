<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'quantity', 'status','start_date','end_date'];

    protected $hidden = ['created_at', 'updated_at'];

    protected $with = ['orderModels'];

    public function orderModels()
    {
        return $this->hasMany(OrderModel::class, 'order_id');
    }

}

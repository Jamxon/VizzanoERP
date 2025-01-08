<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'img', 'company_id'];

    // Kompaniyaga aloqani o'rnatamiz
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Bo'limlarga aloqani o'rnatamiz
    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    // Buyurtmalarga aloqani o'rnatamiz

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Omborlar bilan aloqani o'rnatamiz

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class, 'branch_id');
    }
}

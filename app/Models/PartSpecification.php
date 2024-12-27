<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartSpecification extends Model
{
    use HasFactory;

    protected $table = 'part_specifications';

    protected $fillable = [
        'specification_category_id',
        'code',
        'name',
        'quantity',
        'comment',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'specification_category_id',
    ];

    public function specification()
    {
        return $this->belongsTo(SpecificationCategory::class);
    }
}

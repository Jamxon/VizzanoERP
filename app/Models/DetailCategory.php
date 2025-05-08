<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailCategory extends Model
{
    use HasFactory;

    protected $table = 'detail_categories';

    protected $fillable = [
        'name'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function details()
    {
        return $this->hasMany(Detail::class);
    }
}

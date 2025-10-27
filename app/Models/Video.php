<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'link',
        'branch_id'
    ];

    // Tarifications bilan 1:N
    public function tarifications()
    {
        return $this->hasMany(Tarification::class, 'video_id');
    }
}

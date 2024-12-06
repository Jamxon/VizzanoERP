<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
      'size_id',
      'item_id',
      'color_id',
      'quantity',
    ];

    protected $hidden = [
      'created_at',
      'updated_at',
    ];

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutcomeItem extends Model
{
    use HasFactory;

    protected $table = 'outcome_items';

    protected $fillable = [
        'outcome_id',
        'product_id',
        'quantity',
        'price',
        'notes',
    ];

    public function outcome()
    {
        return $this->belongsTo(Outcome::class);
    }

    public function product()
    {
        return $this->belongsTo(Item::class);
    }
}

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

    public function outcome(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Outcome::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function distributions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OutcomeItemModelDistrubition::class);
    }
}

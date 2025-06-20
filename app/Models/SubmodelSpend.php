<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static updateOrCreate(array $array, array $array1)
 * @method static where(string $string, $submodel_id)
 * @method static firstOrNew(array $array)
 */
class SubmodelSpend extends Model
{
    use HasFactory;

    protected $table = 'submodel_spends';

    protected $fillable = [
        'submodel_id',
        'seconds',
        'summa',
        'region',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'submodel_id'
    ];

    public function submodel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderSubmodel::class, 'submodel_id');
    }
}

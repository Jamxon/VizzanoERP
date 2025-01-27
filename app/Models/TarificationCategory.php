<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $submodelId)
 */
class TarificationCategory extends Model
{
    use HasFactory;

    protected $table = 'tarification_categories';

    protected $fillable = [
        'name',
        'submodel_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'submodel_id',
    ];

    public function submodel()
    {
        return $this->belongsTo(OrderSubModel::class, 'submodel_id');
    }

    public function tarifications()
    {
        return $this->hasMany(Tarification::class);
    }
}

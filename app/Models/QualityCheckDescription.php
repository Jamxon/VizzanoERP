<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 */
class QualityCheckDescription extends Model
{
    use HasFactory;

    protected $table = 'quality_checks_descriptions';

    protected $fillable = [
        'quality_check_id',
        'quality_description_id',
    ];

    public function qualityCheck(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(QualityCheck::class, 'quality_check_id');
    }

    public function tarification(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tarification::class, 'quality_description_id');
    }
}
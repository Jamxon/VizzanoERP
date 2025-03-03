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

    protected $table = 'quality_checks_description';

    protected $fillable = [
        'quality_check_id',
        'quality_check_description_id',
    ];

    public function quality_check(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(QualityCheck::class);
    }

    public function quality_description(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(QualityDescription::class);
    }
}

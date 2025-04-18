<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function qualityDescription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(QualityDescription::class, 'quality_description_id');
    }
}
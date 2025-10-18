<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use mysql_xdevapi\Table;

/**
 * @method static create(array $array)
 */
class ModelImages extends Model
{
    use HasFactory;

    protected $table = 'model_images';

    protected $fillable = ['model_id', 'image'];

    protected $hidden = ['created_at', 'updated_at', 'model_id'];

    public function getImageAttribute($value): \Illuminate\Contracts\Routing\UrlGenerator|string
    {
        if (empty($value)) {
            return null;
        }

        // Agar bu to‘liq URL bo‘lsa (S3 yoki boshqa tashqi manba)
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        // Aks holda, storage papkasidagi faylni qaytar
        return url('storage/' . ltrim($value, '/'));
    }


    public function model(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Models::class);
    }
}

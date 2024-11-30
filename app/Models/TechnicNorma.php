<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnicNorma extends Model
{
    use HasFactory;

    protected $table = 'technic_normas';

    protected $fillable = ['model_id', 'sekund', 'Details_count'];

    public function model()
    {
        return $this->belongsTo(Models::class);
    }
}

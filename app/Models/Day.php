<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Day extends Model
{
    use HasFactory;

    protected $table = 'daily_groups';

    protected $fillable = ['worker_count', 'total_work_time', 'which_model', 'expected_model', 'group_id', 'real_model', 'diff_model'];

    protected $with = ['group', 'model'];

    protected $hidden = ['created_at', 'updated_at', 'group_id', 'which_model'];
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function model()
    {
        return $this->belongsTo(Models::class, 'which_model');
    }

}

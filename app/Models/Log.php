<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @method static create(array $array)
 */
class Log extends Model
{
    use HasFactory;

    protected $table = 'log';

    protected $fillable = ['user_id', 'action', 'old_data', 'new_data', 'created_at'];

    protected $with = [
        'user',
    ];

    public static function add($userId, $action, $oldData = null, $newData = null): void
    {
        DB::table('log')->insert([
            'user_id' => $userId,
            'action' => $action,
            'old_data' => $oldData ? json_encode($oldData) : null,
            'new_data' => $newData ? json_encode($newData) : null,
            'created_at' => now(),
        ]);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

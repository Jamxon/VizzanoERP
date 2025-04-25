<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * @method static create(array $array)
 * @method static orderBy(string $string, string $string1)
 */
class Log extends Model
{
    use HasFactory;

    protected $table = 'log';

    protected $fillable = [
        'user_id',
        'action',
        'type',
        'old_data',
        'new_data',
        'ip_address',
        'user_agent',
        'created_at',
        'program',
        'branch_id',
    ];

    protected $with = ['user'];

    public static function add($userId, $action, $type, $oldData = null, $newData = null): void
    {
        DB::table('log')->insert([
            'user_id' => $userId,
            'action' => $action,
            'old_data' => $oldData ? json_encode($oldData) : null,
            'new_data' => $newData ? json_encode($newData) : null,
            'type' => $type,
            'ip_address' => Request::getClientIp(),
            'user_agent' => Request::header('User-Agent'),
            'program' => auth()->user()->role ?? null,
            'created_at' => now(),
            'branch_id' => auth()->user()->branch_id ?? null,
        ]);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

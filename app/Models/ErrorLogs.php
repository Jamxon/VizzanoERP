<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLogs extends Model
{
    protected $table = 'error_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'ip',
        'user_agent',
        'url',
        'method',
        'request_data',
        'error_message',
        'error_file',
        'error_line',
        'error_trace',
    ];
}
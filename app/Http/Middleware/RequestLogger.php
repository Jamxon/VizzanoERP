<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestLogger
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $start) * 1000, 2); // ms

        Log::channel('requests')->info(json_encode([
            'user_id' => optional(auth()->user())->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->status(),
            'ip' => $request->ip(),
            'agent' => $request->userAgent(),
            'time' => now()->toDateTimeString(),
            'duration_ms' => $duration,
        ]));

        return $response;
    }
}
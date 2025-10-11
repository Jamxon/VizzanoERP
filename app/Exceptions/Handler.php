<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
             $user = Auth::user();
            $userId = $user->id ?? null;
            $userName = $user->name ?? 'Guest';

            $ip = Request::ip();
            $userAgent = Request::header('User-Agent');
            $url = Request::fullUrl();
            $method = Request::method();
            $requestData = json_encode(Request::all(), JSON_UNESCAPED_UNICODE);

            $errorMessage = $e->getMessage();
            $errorFile = $e->getFile();
            $errorLine = $e->getLine();
            $errorTrace = substr($e->getTraceAsString(), 0, 1000);

            // ✅ Bazaga yozish
            DB::table('error_logs')->insert([
                'user_id' => $userId,
                'user_name' => $userName,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'url' => $url,
                'method' => $method,
                'request_data' => $requestData,
                'error_message' => $errorMessage,
                'error_file' => $errorFile,
                'error_line' => $errorLine,
                'error_trace' => $errorTrace,
                'status_code' => $statusCode,
                'created_at' => now(),
            ]);
        });
    }
}

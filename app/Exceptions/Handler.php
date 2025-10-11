<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // fallback — asosiy xatolik logi
            $this->logError($e);
        });
    }

    public function render($request, Throwable $e): Response
    {
        // ValidationException uchun alohida qayta ishlash
        if ($e instanceof ValidationException) {
            $this->logError($e, 422);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }

        // Unique yoki boshqa SQL constraint xatolari
        if ($e instanceof QueryException && str_contains($e->getMessage(), '23505')) {
            $this->logError($e, 409); // Conflict
            return response()->json([
                'status' => 'error',
                'message' => 'Unique constraint violation.',
            ], 409);
        }

        // Boshqa barcha xatoliklar (500, 404, 403 va h.k.)
        $response = parent::render($request, $e);

        if (!in_array($response->getStatusCode(), [200, 201])) {
            $this->logError($e, $response->getStatusCode());
        }

        return $response;
    }

    private function logError(Throwable $e, $statusCode = 500): void
    {
        try {
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

            // ✅ Telegramga yuborish
            $telegramToken = "8446855967:AAHp0rSXhJml8G1qnqNU7eo_MqstBk5GVf4";
            $telegramChatId = -1003130385940;

            if ($telegramToken && $telegramChatId) {
                $text = "🚨 *Error Detected*\n"
                    . "👤 User: {$userName} (ID: {$userId})\n"
                    . "🌐 IP: `{$ip}`\n"
                    . "💻 Device: `{$userAgent}`\n"
                    . "🔗 URL: `{$url}`\n"
                    . "🧭 Method: `{$method}`\n"
                    . "📦 Request: `{$requestData}`\n\n"
                    . "❗ *Error:* `{$errorMessage}`\n"
                    . "📁 File: `{$errorFile}`\n"
                    . "📍 Line: {$errorLine}\n"
                    . "📡 Status: {$statusCode}";

                Http::post("https://api.telegram.org/bot{$telegramToken}/sendMessage", [
                    'chat_id' => $telegramChatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]);
            }

        } catch (\Exception $ex) {
            // Agar o‘zi ham xato bersa — fallback log
            try {
                Http::post("https://api.telegram.org/bot" . env('ERROR_HANDLER_TELEGRAM_BOT') . "/sendMessage", [
                    'chat_id' => env('ERROR_HANDLER_CHAT_ID'),
                    'text' => "⚠️ Error handler ichida xato:\n\n" . $ex->getMessage(),
                    'parse_mode' => 'Markdown',
                ]);
            } catch (\Throwable $t) {
                // fallback jim
            }
        }
    }
}

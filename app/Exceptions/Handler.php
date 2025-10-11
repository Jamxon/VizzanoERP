<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;
use Throwable;

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
            try {
                // Foydalanuvchi ma'lumotlari
                $user = Auth::user();
                $userId = $user->id ?? null;
                $userName = $user->name ?? 'Guest';

                // IP va qurilma ma'lumotlari
                $ip = Request::ip();
                $userAgent = Request::header('User-Agent');
                $url = Request::fullUrl();
                $method = Request::method();
                $requestData = json_encode(Request::all(), JSON_UNESCAPED_UNICODE);

                // Xatolik tafsilotlari
                $errorMessage = $e->getMessage();
                $errorFile = $e->getFile();
                $errorLine = $e->getLine();
                $errorTrace = substr($e->getTraceAsString(), 0, 1000); // juda uzun bo‘lmasligi uchun

                // 1️⃣ Logni bazaga yozish
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
                    'created_at' => now(),
                ]);

                // 2️⃣ Telegramga yuborish
                $telegramToken = env('ERROR_HANDLER_TELEGRAM_BOT');
                $telegramChatId = env('ERROR_HANDLER_CHAT_ID');

                if ($telegramToken && $telegramChatId) {
                    $text = "🚨 *Laravel Error Report*\n"
                        . "👤 User: {$userName} (ID: {$userId})\n"
                        . "🌐 IP: `{$ip}`\n"
                        . "💻 Device: `{$userAgent}`\n"
                        . "🔗 URL: `{$url}`\n"
                        . "🧭 Method: `{$method}`\n"
                        . "📦 Request: `{$requestData}`\n\n"
                        . "❗ *Error:* `{$errorMessage}`\n"
                        . "📁 File: `{$errorFile}`\n"
                        . "📍 Line: {$errorLine}";

                    Http::post("https://api.telegram.org/bot{$telegramToken}/sendMessage", [
                        'chat_id' => $telegramChatId,
                        'text' => $text,
                        'parse_mode' => 'Markdown',
                    ]);
                }
            } catch (\Exception $ex) {
                // Agar loggingning o‘zi xatolik bersa, uni e’tiborsiz qoldiramiz
            }
        });
    }
}

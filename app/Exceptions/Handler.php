<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Laravel'da xatolikni qayta ishlash
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $this->logError($e);
        });
    }

    /**
     * Foydalanuvchiga javob (response) yuborishdan oldin ishlaydi
     */
    public function render($request, Throwable $e): Response
    {
        $response = parent::render($request, $e);

        // faqat 200 yoki 201 bo‘lmagan holatlarda log yozish
        if (!in_array($response->getStatusCode(), [200, 201])) {
            $this->logError($e, $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Barcha xatoliklarni log va Telegramga yuborish funksiyasi
     */
    private function logError(Throwable $e, $statusCode = null): void
    {
        try {
            // 1️⃣ Foydalanuvchi ma'lumotlari
            $user = Auth::user();
            $userId = $user->id ?? null;
            $userName = $user->name ?? 'Guest';

            // 2️⃣ So‘rov tafsilotlari
            $ip = Request::ip();
            $userAgent = Request::header('User-Agent');
            $url = Request::fullUrl();
            $method = Request::method();
            $requestData = json_encode(Request::all(), JSON_UNESCAPED_UNICODE);

            // 3️⃣ Xatolik tafsilotlari
            $errorMessage = $e->getMessage();
            $errorFile = $e->getFile();
            $errorLine = $e->getLine();
            $errorTrace = substr($e->getTraceAsString(), 0, 1000);

            // 4️⃣ Bazaga yozish
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
                'status_code' => $statusCode ?? 500,
                'created_at' => now(),
            ]);

            // 5️⃣ Telegramga yuborish
            $telegramToken = env('ERROR_HANDLER_TELEGRAM_BOT');
            $telegramChatId = env('ERROR_HANDLER_CHAT_ID');

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
            Http::post("https://api.telegram.org/bot{$telegramToken}/sendMessage", [
                    'chat_id' => $telegramChatId,
                    'text' => "kalla blat",
                    'parse_mode' => 'Markdown',
                ]);
        }
    }
}

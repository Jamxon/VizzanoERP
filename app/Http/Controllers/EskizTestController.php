<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EskizTestController extends Controller
{
    public function sendSmsAndCheckStatus(Request $request)
{
    $token = $this->getEskizToken();

    // SMS yuborish
    $sendResponse = Http::withToken($token)->asForm()->post('https://notify.eskiz.uz/api/message/sms/send', [
        'mobile_phone' => $request->phone, // format: 998901234567
        'message' => $request->message,
        'from' => '4546',
        'callback_url' => '',
    ]);

    if (!$sendResponse->successful()) {
        return response()->json(['error' => 'SMS yuborishda xatolik'], 500);
    }

    $smsId = $sendResponse->json('data.id');

    // 10 martagacha statusni tekshirishga harakat qilamiz (har 2 sek.)
    for ($i = 0; $i < 10; $i++) {
        sleep(2); // 2 sekund kutish

        $statusResponse = Http::withToken($token)
            ->get("https://notify.eskiz.uz/api/message/sms/status_by_id/{$smsId}");

        if (!$statusResponse->successful()) {
            continue;
        }

        $status = $statusResponse->json('data.status');

        if (in_array($status, ['delivered', 'undelivered', 'failed'])) {
            return response()->json([
                'sms_id' => $smsId,
                'final_status' => $status,
                'message' => $statusResponse->json('data.message') ?? null
            ]);
        }
    }

    return response()->json([
        'sms_id' => $smsId,
        'final_status' => 'waiting',
        'message' => 'SMS holati hali aniqlanmadi'
    ]);
}



    public function reportByRange()
    {
        $token = $this->getEskizToken();
        if (!$token) {
            return response()->json(['error' => 'Token olishda xatolik'], 500);
        }

        $response = Http::withToken($token)
            ->asForm()
            ->post('https://notify.eskiz.uz/api/report/total-by-range?status=null', [
                'start_date' => '2023-11-01 00:00',
                'end_date'=> '2025-11-01 00:00',
                'to_date' => '2023-11-02 23:59',
                'is_ad' => '',
            ]);

        return response()->json([
            'status' => $response->status(),
            'data' => $response->json(),
        ]);
    }

    private function getEskizToken()
    {
        $email = 'aliyevjamkhan499@gmail.com';
        $password = 'dLd1F1cKjF47z40pYYFmu0lqH7bsL35xIUc8g0oY'; // ❗ Bu yerga parolni yozing

        $response = Http::asForm()->post('https://notify.eskiz.uz/api/auth/login', [
            'email' => $email,
            'password' => $password
        ]);

        if ($response->successful() && isset($response['data']['token'])) {
            return $response['data']['token'];
        }

        throw new \Exception('Eskizdan token olishda xatolik: ' . $response->body());
    }

}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EskizTestController extends Controller
{
    public function sendSms(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $token = $this->getEskizToken();
        if (!$token) {
            return response()->json(['error' => 'Token olishda xatolik'], 500);
        }

        $response = Http::withToken($token)
            ->asForm()
            ->post('https://notify.eskiz.uz/api/message/sms/send', [
                'mobile_phone' => $request->phone, // masalan: 998901234567
                'message' => $request->message,
                'from' => '4546', // Eskizda ulangan sender ID (odatda 4546 bo'ladi)
                'callback_url' => '', // ixtiyoriy: sms status qaytish url
            ]);

        return response()->json([
            'status' => $response->status(),
            'data' => $response->json(),
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

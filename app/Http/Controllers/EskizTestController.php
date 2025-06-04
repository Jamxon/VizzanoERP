<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EskizTestController extends Controller
{
    public function sendSMS(Request $request): \Illuminate\Http\JsonResponse
    {
        $response = Http::withHeaders([
            'Authorization' => 'App YOUR_API_KEY_HERE',
            'Content-Type' => 'application/json',
        ])->post('https://qd2pg3.api.infobip.com/sms/3/messages', [
            'messages' => [
                [
                    'from' => 'InfoSMS', // Sender ID (Infobipda sozlangan bo'lishi kerak)
                    'destinations' => [
                        ['to' => '+998901234567'], // Qabul qiluvchi raqam (xalqaro formatda)
                    ],
                    'text' => 'Salom! Laraveldan yuborilgan test SMS.'
                ]
            ]
        ]);

        return response()->json($response->json());
    }

}

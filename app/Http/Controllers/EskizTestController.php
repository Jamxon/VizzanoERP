<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EskizTestController extends Controller
{
    public function sendSMS(Request $request): \Illuminate\Http\JsonResponse
    {
        $response = Http::withHeaders([
            'Authorization' => 'App bdf355b341f5c5a3ac3c02ff6b43b429-7e260cc3-16cd-41c4-ab65-d74ad5c71e8a',
            'Content-Type' => 'application/json',
        ])->post('https://qd2pg3.api.infobip.com/sms/3/messages', [
            'messages' => [
                [
                    'from' => 'InfoSMS', // Sender ID (Infobipda sozlangan bo'lishi kerak)
                    'destinations' => [
                        ['to' => '+998500079955'], // Qabul qiluvchi raqam (xalqaro formatda)
                    ],
                    "content" => [
                        "text" => "Salom! Laravel orqali yuborilgan SMS."
                    ]
                ]
            ]
        ]);

        return response()->json($response->json());
    }

}

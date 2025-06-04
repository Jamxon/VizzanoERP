<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EskizTestController extends Controller
{
    public function sendSMS(Request $request): \Illuminate\Http\JsonResponse
    {
        $response = Http::withHeaders([
            'Authorization' => 'App 5e1968d5cc8dd4a6a94756d5ae10319f-530f7fbb-a64d-450d-8448-e79bc5e4a727',
            'Content-Type' => 'application/json',
        ])->post('https://qd2pg3.api.infobip.com/sms/3/messages', [
            'messages' => [
                [
                    'from' => 'Vizzano', // Sender ID (Infobipda sozlangan bo'lishi kerak)
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

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EskizTestController extends Controller
{
    public function sendSMS(Request $request): \Illuminate\Http\JsonResponse
    {
        $response = Http::withHeaders([
            'Authorization' => 'App bf2595046738a9c99476bd5671cccceb-dea40100-bc70-4741-89bf-166dad6ea250',
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

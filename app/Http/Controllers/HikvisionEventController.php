<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HikvisionEventController extends Controller
{
    public function getEvents()
    {
        // ISAPI endpoint URL
        $url = 'http://192.168.118.156/ISAPI/AccessControl/AcsEvent?format=json';

        // ISAPI serverga HTTP so'rov yuborish
        $response = Http::get($url);

        // Agar so'rov muvaffaqiyatli bo'lsa, javobni qayta ishlash
        if ($response->successful()) {
            $events = $response->json();  // JSON formatdagi javobni olish

            // Har bir hodisani qayta ishlash
            foreach ($events as $event) {
                // Event ma'lumotlarini qayta ishlash (masalan, ma'lumotlar bazasiga saqlash)
                // Masalan, eventni saqlash
                \App\Models\Log::add(
                    null,
                    "Receive Event",
                    'attempt',
                    null,
                    $event,
                );

            return response()->json(['message' => 'Events processed successfully!']);
            }
        }else {
            return response()->json(['message' => 'Failed to fetch events.'], 500);
        }
    }
}

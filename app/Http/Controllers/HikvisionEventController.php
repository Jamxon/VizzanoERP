<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HikvisionEventController extends Controller
{
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        // Event ma'lumotlarini olish
        $eventData = $request->all();

        // Event ma'lumotlarini saqlash (agar kerak bo'lsa)
        // HikvisionEvent::create($eventData);

        // Log yoki ma'lumotni saqlash
        \Log::info('Received Hikvision event: ', $eventData);

        return response()->json(['message' => 'Event received successfully']);
    }
}

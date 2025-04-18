<?php

namespace App\Http\Controllers;

use App\Models\Log;
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
        Log::add(
            null,
            'hikvision_event',
            'hikvision_event',
            null,
            $eventData,
        );

        return response()->json(['message' => 'Event received successfully']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;

class HikvisionEventController extends Controller
{
    public function handleEvent(Request $request): \Illuminate\Http\JsonResponse
    {
        $contentType = $request->header('Content-Type');

        // Multipart/form-data holatida
        if (str_contains($contentType, 'multipart/form-data')) {
            $eventLogRaw = $request->input('event_log');

            // Rasm fayl bo'lishi mumkin
            $image = $request->file('Picture');

            Log::add(null, 'Hikvision Event', 'Form-data received', [
                'content_type' => $contentType,
                'event_log_raw' => $eventLogRaw,
                'has_picture' => $image ? true : false,
            ]);

            $eventData = json_decode($eventLogRaw, true);
            $accessData = $eventData['AccessControllerEvent'] ?? [];

            // Agar rasm bo‘lsa – uni saqlaymiz
            $imagePath = null;
            if ($image && $image->isValid()) {
                $filename = uniqid() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('public/hikvision_images', $filename);
                $imagePath = str_replace('public/', 'storage/', $path);
            }

            // Log yozish
            Log::add(null, 'Hikvision Event', 'Parsed event', [
                'event_type' => $eventData['eventType'] ?? null,
                'employee_no' => $accessData['employeeNoString'] ?? null,
                'device_name' => $accessData['deviceName'] ?? null,
                'verify_mode' => $accessData['currentVerifyMode'] ?? null,
                'image_path' => $imagePath,
            ]);
        } else {
            // Fallback – JSON yoki XML bo‘lishi mumkin
            $rawData = $request->getContent();
            Log::add(null, 'Hikvision Event', 'Unknown format', [
                'content_type' => $contentType,
                'raw_data' => $rawData,
            ]);
        }

        return response()->json(['status' => 'received']);
    }

}

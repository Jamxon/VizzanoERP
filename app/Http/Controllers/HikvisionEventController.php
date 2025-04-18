<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;

class HikvisionEventController extends Controller
{
    public function handleEvent(Request $request): \Illuminate\Http\JsonResponse
    {
        $contentType = $request->header('Content-Type');
        $rawData = $request->getContent();

        // 1. Dastlabki log
        Log::add(null,
            'Hikvision Event',
            'Hikvision event received',
            [
                'content_type' => $contentType,
                'raw_data' => $rawData,
            ],
        );

        $data = [];

        // 2. multipart/form-data bo‘lsa → faqat log qilamiz hozircha
        if (str_contains($contentType, 'multipart/form-data')) {
            // multipart ichidagi JSON'ni qo‘l bilan ajratish kerak bo'lishi mumkin (agar mavjud bo‘lsa)
            // Hozircha faqat log qilyapmiz, agar aniq tuzilmani bilsak, bu yerda parsing yoziladi
            Log::add(null,
                'Hikvision Event',
                'Multipart event kelgan, JSON topilmadi',
                [
                    'note' => 'Multipart/form-data format. Parsing qo‘shilishi kerak agar JSON bo‘lsa.',
                ]
            );
        }
        // 3. XML bo‘lsa
        elseif (str_contains($contentType, 'xml')) {
            $xml = simplexml_load_string($rawData, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            $data = json_decode($json, true);
        }
        // 4. JSON bo‘lsa
        else {
            $data = json_decode($rawData, true);
        }

        // 5. Agar data mavjud bo‘lsa, asosiy maydonlarni log qilamiz
        if (!empty($data)) {
            Log::add(
                null,
                'Hikvision Event',
                'Parsed event data',
                [
                    'event_type' => $data['eventType'] ?? null,
                    'card_no' => $data['cardNo'] ?? $data['employeeNoString'] ?? null,
                    'device_name' => $data['deviceName'] ?? null,
                ]
            );
        }

        return response()->json(['status' => 'received']);
    }
}

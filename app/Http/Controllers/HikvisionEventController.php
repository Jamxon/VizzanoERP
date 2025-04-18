<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HikvisionEventController extends Controller
{
    public function handleEvent(Request $request): \Illuminate\Http\JsonResponse
    {
        $contentType = $request->header('Content-Type');
        $rawData = $request->getContent();

        // Logging uchun: XML yoki JSON bo'lishi mumkin
        Log::add(null,
            'Hikvision Event',
            'Hikvision event received',
            [
                'content_type' => $contentType,
                'raw_data' => $rawData,
            ],
        );

        // Agar XML bo‘lsa, XML to Array parse qilamiz
        if (str_contains($contentType, 'xml')) {
            $xml = simplexml_load_string($rawData, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            $data = json_decode($json, true);
        } else {
            // JSON bo‘lsa, oddiy json_decode
            $data = json_decode($rawData, true);
        }

        // Shu yerda event ma’lumotlarini ishlov beramiz
        // Masalan, event_type, card_no, device_name va hokazo
        Log::add(
            null,
            'Hikvision Event',
            'Parsed event data',
            [
                'event_type' => $data['eventType'] ?? null,
                'card_no' => $data['cardNo'] ?? null,
                'device_name' => $data['deviceName'] ?? null,
                // Qo'shimcha ma'lumotlar
            ],
        );

        return response()->json(['status' => 'received']);
    }
}

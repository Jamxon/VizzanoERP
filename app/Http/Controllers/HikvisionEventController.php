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

        Log::add(null,
            'Hikvision Event',
            'Hikvision event received',
            [
                'content_type' => $contentType,
                'raw_data' => $rawData,
            ],
        );

        $data = [];

        if (str_contains($contentType, 'multipart/form-data')) {
            $formData = $request->all(); // ⚠️ bu yer multipart inputlar uchun
            Log::add(null,
                'Hikvision Event',
                'Multipart form-data received',
                [
                    'form_data' => $formData,
                ]
            );

            $data = $formData;
        }
        elseif (str_contains($contentType, 'xml')) {
            $xml = simplexml_load_string($rawData, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            $data = json_decode($json, true);
        }
        else {
            $data = json_decode($rawData, true);
        }

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

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;

class OrderImportController extends Controller
{
    /**
     * Excel faylni yuklash va JSON obyektga o'tkazish.
     */
    public function import(Request $request): \Illuminate\Http\JsonResponse
    {
        // Fayl yuklanganligini tekshiramiz
        if (!$request->hasFile('file')) {
            return response()->json(['success' => false, 'message' => 'Fayl yuklanmagan!'], 400);
        }

        $file = $request->file('file');

        // Faqat Excel fayllariga ruxsat beramiz
        if (!in_array($file->getClientOriginalExtension(), ['xls', 'xlsx'])) {
            return response()->json(['success' => false, 'message' => 'Faqat .xls yoki .xlsx fayllar yuklanishi mumkin!'], 400);
        }

        // Faylni vaqtinchalik papkaga saqlaymiz
        $filePath = $file->storeAs('uploads', $file->getClientOriginalName());

        // Excel faylini ochamiz
        $spreadsheet = IOFactory::load(storage_path("app/" . $filePath));
        $worksheet = $spreadsheet->getActiveSheet();

        $data = []; // JSON obyektni yig'ish uchun massiv

        // 2-qatordan boshlab iteratsiya qilamiz
        foreach ($worksheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false); // Bo'sh hujayralarni ham o'qish

            $orderData = [];
            foreach ($cellIterator as $cell) {
                $orderData[] = $cell->getValue();
            }

            // Agar E ustuni bo'sh bo'lsa, siklni to'xtatamiz
            if (empty($orderData[4])) {
                break;
            }

            // JSON obyekt sifatida saqlash
            $data[] = [
                'A' => $orderData[0] ?? null,
                'B' => $orderData[1] ?? null,
                'C' => $orderData[2] ?? null,
                'D' => $orderData[3] ?? null,
                'E' => $orderData[4] ?? null,
                'F' => $orderData[5] ?? null,
                'G' => $orderData[6] ?? null,
                'H' => $orderData[7] ?? null,
                'I' => $orderData[8] ?? null,
                'J' => $orderData[9] ?? null,
                'K' => $orderData[10] ?? null,
                'L' => $orderData[11] ?? null,
                'M' => $orderData[12] ?? null,
            ];
        }

        // Faylni o'chiramiz
        Storage::delete($filePath);

        // JSON formatida qaytaramiz
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;

class OrderImportController extends Controller
{
    public function import(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:102400|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        if (!$file) {
            return response()->json(['error' => 'Fayl yuklanmadi!'], 400);
        }

        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        $filePath = $file->storeAs('temp', $fileName);

        if (!Storage::exists("temp/$fileName")) {
            return response()->json(['error' => 'Fayl saqlanmadi yoki yo‘q!'], 500);
        }

        $fullPath = storage_path("app/temp/$fileName");

        if (!file_exists($fullPath)) {
            return response()->json(['error' => "Fayl mavjud emas: $fullPath"], 500);
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Excel faylni ochishda xatolik: ' . $e->getMessage()], 500);
        }

        $drawings = $worksheet->getDrawingCollection();
        $images = [];

        foreach ($drawings as $drawing) {
            if ($drawing instanceof Drawing) {
                $cell = $drawing->getCoordinates();
                $imagePath = $this->saveImage($drawing);
                $images[$cell] = $imagePath;
            }
        }

        $orders = [];
        foreach ($worksheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $orderData = [];
            foreach ($cellIterator as $cell) {
                $orderData[] = $cell->getValue();
            }

            $rowIndex = $row->getRowIndex();
            $imagePath = $images["C$rowIndex"] ?? $images["D$rowIndex"] ?? null;

            $order = Order::create([
                'name' => $orderData[0] ?? 'No Name',
                'quantity' => $orderData[1] ?? 0,
                'image' => $imagePath,
            ]);

            $orders[] = $order;
        }

        return response()->json([
            'message' => count($orders) . ' ta order yaratildi',
            'orders' => $orders,
        ]);
    }

    /**
     * Rasmni saqlash
     */
    private function saveImage(Drawing $drawing): string
    {
        $imageName = uniqid() . '.' . $drawing->getExtension();
        $path = storage_path('app/public/orders/' . $imageName);

        if (!file_exists(storage_path('app/public/orders'))) {
            mkdir(storage_path('app/public/orders'), 0775, true);
        }

        $drawing->getImageResource()->writeImage($path);

        return 'storage/orders/' . $imageName;
    }
}

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
            return response()->json(['error' => 'Fayl topilmadi'], 500);
        }

        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        $filePath = $file->storeAs('public', $fileName);

        if (!$filePath) {
            return response()->json(['error' => 'Fayl saqlanmadi!'], 500);
        }

        $fullPath = storage_path("app/public/public/$fileName");

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

            // **E ustuni bo'sh bo'lsa, siklni to'xtatish**
            if (empty($orderData[4])) { // E ustuni indeks bo'yicha 4
                break;
            }

            $rowIndex = $row->getRowIndex();
            $imagePath = $images["C$rowIndex"] ?? $images["D$rowIndex"] ?? null;

            $order = Order::create([
                'name' => $orderData[4] ?? 'No Name',
                'quantity' => $orderData[6] ?? 0,
                'status' => $orderData[2] ?? 'inactive',
                'start_date' => null,
                'end_date' => null,
                'rasxod' => 0,
                'branch_id' => auth()->user()->employee->branch_id,
                'contragent_id' => null,
                'comment' => null,
            ]);

            $orders[] = $order;
        }


        return response()->json([
            'message' => count($orders) . ' ta order yaratildi',
            'orders' => $orders,
        ]);
    }

    /**
     * Excel ichidan rasmni saqlash
     */
    private function saveImage(Drawing $drawing): string
    {
        $imageName = uniqid() . '.' . $drawing->getExtension();

        // Rasmni `storage/app/public/orders/` ichiga saqlash
        Storage::disk('public')->put('orders/' . $imageName, file_get_contents($drawing->getPath()));

        // URL orqali ochish uchun to‘g‘ri yo‘lni qaytarish
        return 'storage/orders/' . $imageName;
    }
}

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
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');
        $path = $file->store('temp');

        $spreadsheet = IOFactory::load(storage_path("app/$path"));
        $worksheet = $spreadsheet->getActiveSheet();

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

    private function saveImage(Drawing $drawing): string
    {
        $imageName = uniqid() . '.' . $drawing->getExtension();
        $path = storage_path('app/public/orders/' . $imageName);
        $drawing->getImageResource()->writeImage($path);

        return 'storage/orders/' . $imageName;
    }
}


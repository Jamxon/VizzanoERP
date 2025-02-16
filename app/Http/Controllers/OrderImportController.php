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
        if (!$request->hasFile('file')) {
            return response()->json(['success' => false, 'message' => 'Fayl yuklanmadi!'], 400);
        }

        $file = $request->file('file');

        $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9.]/', '_', $file->getClientOriginalName());
        $filePath = $file->storeAs('public', $fileName);

        dd($filePath);
        
        if (!Storage::exists($filePath)) {
            return response()->json(['success' => false, 'message' => 'Fayl saqlanmadi!'], 400);
        }

        $spreadsheet = IOFactory::load(storage_path("app/" . $filePath));
        $worksheet = $spreadsheet->getActiveSheet();

        $data = [];

        foreach ($worksheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $orderData = [];
            foreach ($cellIterator as $cell) {
                $orderData[] = $cell->getValue();
            }

            if (empty($orderData[4])) {
                break;
            }

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

        Storage::delete($filePath);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}

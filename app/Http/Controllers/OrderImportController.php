<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OrderImportController extends Controller
{
    public function import(Request $request)
    {
        $file = $request->file('file');

        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => "Fayl noto'g'ri yuklangan!"], 400);
        }

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => "Faylni o'qishda xatolik: " . $e->getMessage()], 500);
        }

        $highestRow = $sheet->getHighestRow();
        $data = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $model = trim((string)$sheet->getCell("E$row")->getValue());
            $submodel = trim((string)$sheet->getCell("D$row")->getValue());
            $price = (float)$sheet->getCell("F$row")->getValue();
            $quantity = (float)$sheet->getCell("G$row")->getValue();
            $minut = (float)$sheet->getCell("I$row")->getValue();
            $model_summa = (float)$sheet->getCell("M$row")->getValue();

            if (!empty($model) && ($price > 0 || $quantity > 0)) {
                $data[] = [
                    'model' => $model,
                    'submodel' => $submodel,
                    'price' => $price,
                    'quantity' => $quantity,
                    'minut' => $minut,
                    'model_summa' => $model_summa
                ];
            }
        }

        return response()->json($data);
    }
}

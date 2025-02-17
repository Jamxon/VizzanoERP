<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $modelImages = [];

        // Rasmlarni olish
        foreach ($sheet->getDrawingCollection() as $drawing) {
            if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\Drawing) {
                $coordinates = $drawing->getCoordinates();
                $imageExtension = $drawing->getExtension();
                $imageName = Str::uuid() . '.' . $imageExtension;
                $imagePath = "models/$imageName";

                Storage::disk('public')->put($imagePath, file_get_contents($drawing->getPath()));

                preg_match('/\d+/', $coordinates, $matches);
                $rowNumber = $matches[0] ?? null;

                if ($rowNumber) {
                    $modelImages[$rowNumber][] = url('storage/' . $imagePath);
                }
            }
        }

        for ($row = 2; $row <= $highestRow; $row++) {
            $eValue = trim((string)$sheet->getCell("E$row")->getValue());
            $dValue = trim((string)$sheet->getCell("D$row")->getValue());

            // Qiymatlar to'g'ri olinayotganiga ishonch hosil qilish uchun
            $fValue = (float) $sheet->getCell("F$row")->getCalculatedValue();
            $gValue = (float) $sheet->getCell("G$row")->getCalculatedValue();
            $mValue = (float) $sheet->getCell("M$row")->getCalculatedValue();

            $fValue = is_numeric($fValue) ? $fValue : 0;
            $gValue = is_numeric($gValue) ? $gValue : 0;
            $mValue = is_numeric($mValue) ? $mValue : 0;

            if ($eValue) {
                $data[] = [
                    'model' => $eValue,
                    'submodel' => $dValue,
                    'quantity' => $gValue,
                    'model_price' => $fValue,
                    'model_summa' => $mValue,
                    'images' => $modelImages[$row] ?? []
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}

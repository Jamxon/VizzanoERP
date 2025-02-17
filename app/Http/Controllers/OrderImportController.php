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
        // Faylni olish
        $file = $request->file('file');

        // Fayl tekshiriladi
        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => "Fayl noto'g'ri yuklangan!"], 400);
        }

        try {
            // Excel faylini yuklash
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => "Faylni o'qishda xatolik: " . $e->getMessage()], 500);
        }

        $highestRow = $sheet->getHighestRow(); // Oxirgi qator
        $data = [];
        $modelImages = [];
        $currentGroup = null;
        $currentSubModel = null;
        $currentBlock = [];
        $currentSizes = [];

        // Rasmlarni olish va saqlash
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

        // Excel ma'lumotlarini o'qish
        for ($row = 2; $row <= $highestRow; $row++) {
            $aValue = trim((string)$sheet->getCell("A$row")->getValue());
            $dValue = trim((string)$sheet->getCell("D$row")->getValue());
            $eValue = trim((string)$sheet->getCell("E$row")->getValue());
            $fValue = (float)$sheet->getCell("F$row")->getValue();
            $gValue = (float)$sheet->getCell("G$row")->getValue();
            $hValue = (float)$sheet->getCell("H$row")->getValue();
            $iValue = (float)$sheet->getCell("I$row")->getValue();
            $jValue = (float)$sheet->getCell("J$row")->getValue();
            $mValue = (float)$sheet->getCell("M$row")->getCalculatedValue();

            // Yangi model guruhi (E ustuni bo‘yicha)
            if ($eValue && $eValue !== $currentGroup) {
                if (!empty($currentBlock)) {
                    $nonZeroItem = collect($currentBlock)->firstWhere(fn($item) => $item['quantity'] > 0);

                    $data[] = [
                        'model' => $currentGroup,
                        'submodel' => $currentSubModel,
                        'price' => $nonZeroItem['price'] ?? 0,
                        'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                        'sizes' => array_values(array_unique($currentSizes)),
                        'model_summa' => array_sum(array_column($currentBlock, 'model_summa')),
                        'images' => $modelImages[$row] ?? [],
                    ];
                }

                $currentGroup = $eValue;
                $currentSubModel = $dValue;
                $currentBlock = [];
                $currentSizes = [];
            }

            // O'lchamni aniqlash
            if ($currentGroup && (
                    preg_match('/^\d{2,3}(?:\/\d{2,3})?$/', $aValue) ||
                    preg_match('/^\d{2,3}-\d{2,3}$/', $aValue)
                ) && $aValue !== '') {
                $currentSizes[] = $aValue;
            }

            // Model ma'lumotlarini yig'ish
            if ($fValue > 0 && $gValue > 0) {
                $currentBlock[] = [
                    'size' => $aValue,
                    'price' => $fValue,
                    'quantity' => $gValue,
                    'model_summa' => $mValue
                ];
            }
        }

        // Oxirgi modelni ham qo'shish
        if (!empty($currentBlock)) {
            $nonZeroItem = collect($currentBlock)->firstWhere(fn($item) => $item['quantity'] > 0);

            $data[] = [
                'model' => $currentGroup,
                'submodel' => $currentSubModel,
                'price' => $nonZeroItem['price'] ?? 0,
                'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                'sizes' => array_values(array_unique($currentSizes)),
                'model_summa' => array_sum(array_column($currentBlock, 'model_summa')),
                'images' => $modelImages[$row] ?? [],
            ];
        }

        // JSON qaytarish
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}

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
        $currentGroup = null;
        $currentSubModel = null;
        $currentSizes = [];

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
            $aValue = trim((string)$sheet->getCell("A$row")->getValue());
            $dValue = trim((string)$sheet->getCell("D$row")->getValue());
            $eValue = trim((string)$sheet->getCell("E$row")->getValue());
            $fValue = (float)$sheet->getCell("F$row")->getValue();
            $gValue = (float)$sheet->getCell("G$row")->getValue();
            $mValue = (float)$sheet->getCell("M$row")->getValue();

            // O'lchamlarni yig'ish
            if ((preg_match('/^\d{2,3}(?:\/\d{2,3})?$/', $aValue) ||
                    preg_match('/^\d{2,3}-\d{2,3}$/', $aValue)) && $aValue !== '') {
                $currentSizes[] = $aValue;
            }
            // Yangi model boshlanishini tekshirish
            if ($eValue && $eValue !== $currentGroup) {
                if (!empty($currentBlock)) {
                    $nonZeroItem = collect($currentBlock)->firstWhere(function ($item) {
                        return $item['price'] > 0 || $item['quantity'] > 0 || $item['total'] > 0;
                    });
                    $data[] = [
                        'model' => $currentGroup,
                        'submodel' => $currentSubModel,
                        'items' => $currentBlock,
                        'total' => [
                            'price' => $nonZeroItem['price'] ?? 0,
                            'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                            'total' => array_sum(array_column($currentBlock, 'total')),
                            'minut' => $nonZeroItem['minut'] ?? 0,
                            'total_minut' => $nonZeroItem['total_minut'] ?? 0,
                            'model_summa' => array_sum(array_column($currentBlock, 'model_summa')) // model_summa to'plami
                        ],
                        'sizes' => array_values(array_unique($currentSizes))
                    ];
                }
                $currentGroup = $eValue;
                $currentSubModel = $dValue;
                $currentBlock = [];
                $currentSizes = [];
                if ((preg_match('/^\d{2,3}(?:\/\d{2,3})?$/', $aValue) ||
                        preg_match('/^\d{2,3}-\d{2,3}$/', $aValue)) && $aValue !== '') {
                    $currentSizes[] = $aValue;
                }
            }


            if ($eValue) {
                $data[] = [
                    'model' => $eValue,
                    'submodel' => $dValue,
                    'model_price' => $fValue,
                    'model_summa' => $mValue,
                    'images' => $modelImages[$row] ?? [],
                    'size' => $aValue,
                    'price' => $fValue,
                    'quantity' => $gValue,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}

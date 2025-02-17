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
        $currentGroup = null;
        $currentBlock = [];
        $currentSizes = [];
        $currentSubModel = null;
        $modelImages = [];

        // **1. RASM FAYLLARNI TEZ YUKLASH VA SAQLASH**
        foreach ($sheet->getDrawingCollection() as $drawing) {
            if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\Drawing) {
                $coordinates = $drawing->getCoordinates();
                $imageExtension = $drawing->getExtension();
                $imageName = Str::uuid() . '.' . $imageExtension;
                $imagePath = "models/$imageName";

                Storage::disk('public')->put($imagePath, file_get_contents($drawing->getPath()));

                if (str_starts_with($coordinates, 'C') || str_starts_with($coordinates, 'D')) {
                    $modelImages[$coordinates] = "http://176.124.208.61:2005" . '/storage/' . $imagePath;
                }
            }
        }

        // **2. MA’LUMOTLARNI TEZ O‘QISH**
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

            // **O'lchamlarni ajratish**
            if (preg_match('/^\d{2,3}(?:\/\d{2,3})?$/', $aValue) || preg_match('/^\d{2,3}-\d{2,3}$/', $aValue)) {
                $currentSizes[] = $aValue;
            }

            // **Yangi model boshlanishini aniqlash**
            if ($eValue && $eValue !== $currentGroup) {
                if (!empty($currentBlock)) {
                    $data[] = [
                        'model' => $currentGroup,
                        'submodel' => $currentSubModel,
                        'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                        'model_price' => array_sum(array_column($currentBlock, 'price')),
                        'model_summa' => array_sum(array_column($currentBlock, 'model_summa')),
                        'sizes' => array_values(array_unique($currentSizes)),
                        'images' => $modelImages["C$row"] ?? $modelImages["D$row"] ?? []
                    ];
                }

                $currentGroup = $eValue;
                $currentSubModel = $dValue;
                $currentBlock = [];
                $currentSizes = [];
            }

            // **Ahamiyatli qatorlarni qo‘shish**
            if ($fValue > 0 || $gValue > 0 || $hValue > 0) {
                $currentBlock[] = [
                    'size' => $aValue,
                    'price' => $fValue,
                    'quantity' => $gValue,
                    'total' => $hValue,
                    'minut' => $iValue,
                    'total_minut' => $jValue,
                    'model_summa' => $mValue
                ];
            }
        }

        // **Oxirgi modelni qo‘shish**
        if (!empty($currentBlock)) {
            $data[] = [
                'model' => $currentGroup,
                'submodel' => $currentSubModel,
                'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                'model_price' => array_sum(array_column($currentBlock, 'price')),
                'model_summa' => array_sum(array_column($currentBlock, 'model_summa')),
                'sizes' => array_values(array_unique($currentSizes)),
                'images' => $modelImages["C$row"] ?? $modelImages["D$row"] ?? []
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}

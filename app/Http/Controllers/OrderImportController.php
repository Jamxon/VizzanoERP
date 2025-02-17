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

        for ($row = 2; $row <= $highestRow; $row++) {
            $aValue = trim((string)$sheet->getCell("A$row")->getValue());
            $dValue = trim((string)$sheet->getCell("D$row")->getValue());
            $eValue = trim((string)$sheet->getCell("E$row")->getValue());
            $fValue = (float) trim($sheet->getCell("F$row")->getValue()); // F ustunidagi narx
            $gValue = (float) $sheet->getCell("G$row")->getValue();
            $hValue = (float) $sheet->getCell("H$row")->getValue();
            $iValue = (float) $sheet->getCell("I$row")->getValue();
            $jValue = (float) $sheet->getCell("J$row")->getValue();
            $mValue = (float) $sheet->getCell("M$row")->getCalculatedValue(); // M ustunidagi formula natijasi

            // Model uchun unique ID
            $modelUniqueId = md5($eValue); // Model nomidan unique ID yaratish

            // Rasmlarni saqlash va modelga bog‘lash
            $imageFile = $request->file("image_$row"); // Fayl input nomi image_2, image_3 kabi kelishi kerak
            if ($imageFile) {
                $imagePath = $imageFile->storeAs("models/$modelUniqueId", $imageFile->getClientOriginalName(), 'public');
                $modelImages[$eValue][] = Storage::url($imagePath);
            }

            // O'lchamlarni yig'ish
            if ((preg_match('/^\d{2,3}(?:\/\d{2,3})?$/', $aValue) ||
                    preg_match('/^\d{2,3}-\d{2,3}$/', $aValue)) && $aValue !== '') {
                $currentSizes[] = $aValue;
            }

            // Yangi model boshlanishini tekshirish
            if ($eValue && $eValue !== $currentGroup) {
                if (!empty($currentBlock)) {
                    $data[] = [
                        'model' => $currentGroup,
                        'submodel' => $currentSubModel,
                        'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                        'model_price' => array_sum(array_column($currentBlock, 'price')), // F ustunidan umumiy narx
                        'model_summa' => array_sum(array_column($currentBlock, 'model_summa')), // M ustunidagi umumiy summa
                        'sizes' => array_values(array_unique($currentSizes)),
                        'images' => $modelImages[$currentGroup] ?? []
                    ];
                }

                $currentGroup = $eValue;
                $currentSubModel = $dValue;
                $currentBlock = [];
                $currentSizes = [];
            }

            // Ahamiyatli qatorlarni qo'shish
            if ($fValue > 0 || $gValue > 0 || $hValue > 0) {
                $currentBlock[] = [
                    'size' => $aValue,
                    'price' => $fValue,  // F ustunidagi narx
                    'quantity' => $gValue,
                    'total' => $hValue,
                    'minut' => $iValue,
                    'total_minut' => $jValue,
                    'model_summa' => $mValue // M ustunidagi qiymat
                ];
            }
        }

        // Oxirgi blokni qo'shish
        if (!empty($currentBlock)) {
            $data[] = [
                'model' => $currentGroup,
                'submodel' => $currentSubModel,
                'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                'model_price' => array_sum(array_column($currentBlock, 'price')), // F ustunidan umumiy narx
                'model_summa' => array_sum(array_column($currentBlock, 'model_summa')), // M ustunidagi umumiy summa
                'sizes' => array_values(array_unique($currentSizes)),
                'images' => $modelImages[$currentGroup] ?? []
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}

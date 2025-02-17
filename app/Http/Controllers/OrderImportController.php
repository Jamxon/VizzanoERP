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
        $sizes = [];
        $currentGroup = null;
        $currentBlock = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $aValue = trim((string)$sheet->getCell("A$row")->getValue());
            $eValue = trim((string)$sheet->getCell("E$row")->getValue());
            $fValue = trim((string)$sheet->getCell("F$row")->getValue());
            $gValue = trim((string)$sheet->getCell("G$row")->getValue());
            $hValue = trim((string)$sheet->getCell("H$row")->getCalculatedValue());

            // O'lchamlarni yig'ish
            if (preg_match('/^\d{2,3}(?:\/\d{2,3})?$/', $aValue)) {
                $sizes[] = $aValue;
            }

            // Yangi guruh boshlanishini tekshirish
            if ($eValue && $eValue !== $currentGroup) {
                if (!empty($currentBlock)) {
                    $data[] = [
                        'article' => $currentGroup,
                        'items' => $currentBlock,
                        'total' => [
                            'price' => $currentBlock[0]['price'] ?? 0,
                            'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                            'total' => array_sum(array_column($currentBlock, 'total')),
                            'minut' => $currentBlock[0]['minut'] ?? 0,
                            'umumiy_daqiqa' => array_sum(array_column($currentBlock, 'umumiy_daqiqa')),
                            'model_summa' => $currentBlock[0]['model_summa'] ?? 0
                        ]
                    ];
                }
                $currentGroup = $eValue;
                $currentBlock = [];
            }

            // Faqat ahamiyatli qatorlarni qo'shish
            if ($fValue || $gValue || $hValue) {
                $currentBlock[] = [
                    'size' => $aValue,
                    'price' => (float)$fValue,
                    'quantity' => (float)$gValue,
                    'total' => (float)$hValue,
                    'minut' => (float)$sheet->getCell("I$row")->getValue(),
                    'umumiy_daqiqa' => (float)$sheet->getCell("J$row")->getValue(),
                    'model_summa' => (float)$sheet->getCell("M$row")->getValue()
                ];
            }
        }

            // Oxirgi blokni qo'shish
            if (!empty($currentBlock)) {
                $data[] = [
                    'article' => $currentGroup,
                    'items' => $currentBlock,
                    'total' => [
                        'price' => $currentBlock[0]['price'] ?? 0,
                        'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                        'total' => array_sum(array_column($currentBlock, 'total')),
                        'minut' => $currentBlock[0]['minut'] ?? 0,
                        'umumiy_daqiqa' => array_sum(array_column($currentBlock, 'umumiy_daqiqa')),
                        'model_summa' => $currentBlock[0]['model_summa'] ?? 0
                    ]
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'sizes' => array_values(array_unique($sizes))
            ]);
    }
}

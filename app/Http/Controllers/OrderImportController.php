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
    $currentGroup = null;
    $currentBlock = [];
    $currentSizes = [];
    $currentSubModel = null;

    // Birinchi bo'sh element
    $data[] = [
        'model' => null,
        'submodel' => null,
        'items' => [[
            'size' => '',
            'price' => 0,
            'quantity' => 0,
            'total' => 0,
            'minut' => 0,
            'total_minut' => 0,
            'model_summa' => 0
        ]],
        'total' => [
            'price' => 0,
            'quantity' => 0,
            'total' => 0,
            'minut' => 0,
            'total_minut' => 0,
            'model_summa' => 0
        ],
        'sizes' => []
    ];

        for ($row = 2; $row <= $highestRow; $row++) {
            $aValue = trim((string)$sheet->getCell("A$row")->getValue());
            $dValue = trim((string)$sheet->getCell("D$row")->getValue());
            $eValue = trim((string)$sheet->getCell("E$row")->getValue());
            $fValue = (float)$sheet->getCell("F$row")->getValue();
            $gValue = (float)$sheet->getCell("G$row")->getValue();
            $hValue = (float)$sheet->getCell("H$row")->getValue();
            $iValue = (float)$sheet->getCell("I$row")->getValue();
            $jValue = (float)$sheet->getCell("J$row")->getValue();
            $mValue = (float)$sheet->getCell("M$row")->getValue();

            // O'lchamlarni yig'ish (E guruhi uchun)
            if ($eValue && $eValue !== $currentGroup) {
                if (!empty($currentBlock)) {
                    $nonZeroItem = collect($currentBlock)->firstWhere(function ($item) {
                        return $item['quantity'] > 0;
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
                            'model_summa' => $nonZeroItem['model_summa'] ?? 0
                        ],
                        'sizes' => array_values(array_unique($currentSizes))
                    ];
                }

                $currentGroup = $eValue;
                $currentSubModel = $dValue;
                $currentBlock = [];
                $currentSizes = [];
            }

            // Size qatorlarini yig'ish - bu yerda E ustuni qiymati bo'yicha
            if ($currentGroup && (
                    preg_match('/^\d{2,3}(?:\/\d{2,3})?$/', $aValue) ||
                    preg_match('/^\d{2,3}-\d{2,3}$/', $aValue)
                ) && $aValue !== '') {
                $currentSizes[] = $aValue;
            }

            // Qatorlarni yig'ish
            if ($fValue > 0 && $gValue > 0) {
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

    // Oxirgi blokni qo'shish
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
                'model_summa' => $nonZeroItem['model_summa'] ?? 0
            ],
            'sizes' => array_values(array_unique($currentSizes))
        ];
    }

    return response()->json([
        'success' => true,
        'data' => $data
    ]);
}
}

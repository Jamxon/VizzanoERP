<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OrderImportController extends Controller
{
    public function import(Request $request)
    {
        $file = $request->file("file");

        if (!$file || !$file->isValid()) {
            return response()->json(["success" => false, "message" => "Fayl noto'g'ri yuklangan!"], 400);
    }

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            return response()->json(["success" => false, "message" => "Faylni o'qishda xatolik: " . $e->getMessage()], 500);
    }

    $highestRow = $sheet->getHighestRow();
    $data = [];
    $currentGroup = null;
    $currentBlock = [];
    $currentSizes = []; // Joriy E ustuni uchun o"lchamlar

    // Birinchi itemni qo"shish
    $data[] = [
        "article" => null,
        "items" => [[
            "size" => "",
            "price" => 0,
            "quantity" => 0,
            "total" => 0,
            "minut" => 0,
            "umumiy_daqiqa" => 0,
            "model_summa" => 0
        ]],
        "total" => [
            "price" => 0,
            "quantity" => 0,
            "total" => 0,
            "minut" => 0,
            "umumiy_daqiqa" => 0,
            "model_summa" => 0
        ],
        "sizes" => [] // Birinchi element uchun bo"sh sizes
    ];

    for ($row = 2; $row <= $highestRow; $row++) {
        $aValue = trim((string)$sheet->getCell("A$row")->getValue());
        $eValue = trim((string)$sheet->getCell("E$row")->getValue());
        $fValue = (float)$sheet->getCell("F$row")->getValue();
        $gValue = (float)$sheet->getCell("G$row")->getValue();
        $hValue = (float)$sheet->getCell("H$row")->getValue();
        $iValue = (float)$sheet->getCell("I$row")->getValue();
        $jValue = (float)$sheet->getCell("J$row")->getValue();
        $mValue = (float)$sheet->getCell("M$row")->getValue();

        // O"lchamlarni yig"ish (joriy E ustuni uchun)
        if ((preg_match("/^\d{2,3}(?:\/\d{2,3})?$/", $aValue) ||
                preg_match("/^\d{2,3}-\d{2,3}$/", $aValue)) && $aValue !== "") {
            $currentSizes[] = $aValue;
        }

        // Yangi guruh boshlanishini tekshirish
        if ($eValue && $eValue !== $currentGroup) {
            if (!empty($currentBlock)) {
                $data[] = [
                    "article" => $currentGroup,
                    "items" => $currentBlock,
                    "total" => [
                        "price" => $currentBlock[0]["price"] ?? 0,
                        "quantity" => array_sum(array_column($currentBlock, "quantity")),
                        "total" => array_sum(array_column($currentBlock, "total")),
                        "minut" => $currentBlock[0]["minut"] ?? 0,
                        "umumiy_daqiqa" => array_sum(array_column($currentBlock, "umumiy_daqiqa")),
                        "model_summa" => $currentBlock[0]["model_summa"] ?? 0
                    ],
                    "sizes" => array_values(array_unique($currentSizes)) // Joriy E ustuni uchun yig"ilgan o"lchamlar
                ];
            }
            $currentGroup = $eValue;
            $currentBlock = [];
            $currentSizes = []; // Yangi E ustuni uchun o"lchamlar ro"yxatini tozalash

            // Yangi E ustuni uchun dastlabki o"lchamni qo"shish
            if ((preg_match("/^\d{2,3}(?:\/\d{2,3})?$/", $aValue) ||
                    preg_match("/^\d{2,3}-\d{2,3}$/", $aValue)) && $aValue !== "") {
                $currentSizes[] = $aValue;
            }
        }

        // Faqat ahamiyatli qatorlarni qo"shish
        if ($fValue > 0 || $gValue > 0 || $hValue > 0) {
            $currentBlock[] = [
                "size" => $aValue,
                "price" => $fValue,
                "quantity" => $gValue,
                "total" => $hValue,
                "minut" => $iValue,
                "umumiy_daqiqa" => $jValue,
                "model_summa" => $mValue
            ];
        }
    }

    // Oxirgi blokni qo"shish
    if (!empty($currentBlock)) {
        $data[] = [
            "article" => $currentGroup,
            "items" => $currentBlock,
            "total" => [
                "price" => $currentBlock[0]["price"] ?? 0,
                "quantity" => array_sum(array_column($currentBlock, "quantity")),
                "total" => array_sum(array_column($currentBlock, "total")),
                "minut" => $currentBlock[0]["minut"] ?? 0,
                "umumiy_daqiqa" => array_sum(array_column($currentBlock, "umumiy_daqiqa")),
                "model_summa" => $currentBlock[0]["model_summa"] ?? 0
            ],
            "sizes" => array_values(array_unique($currentSizes))
        ];
    }

    return response()->json([
        "success" => true,
        "data" => $data
    ]);
}
}

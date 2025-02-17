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
            return response()->json(['success' => false, 'message' => 'Fayl noto‘g‘ri yuklangan!'], 400);
        }

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Faylni o‘qishda xatolik: ' . $e->getMessage()], 500);
        }

        $highestRow = $sheet->getHighestRow();
        if ($highestRow < 2) {
            return response()->json(['success' => false, 'message' => 'Fayl ichida yaroqli ma’lumot topilmadi!'], 400);
        }

        $data = [];
        $sizes = [];
        $currentBlock = [];
        $hSum = 0; // H ustunidagi barcha qiymatlarning yig‘indisi
        $lastEValue = null;

        for ($row = 2; $row <= $highestRow; $row++) {
            $eColumn = trim((string)$sheet->getCell("E$row")->getValue());
            $aColumn = trim((string)$sheet->getCell("A$row")->getValue());
            $hColumn = (float) trim((string)$sheet->getCell("H$row")->getValue()); // Float formatga o‘tkazamiz

            if ($eColumn === "") {
                continue;
            }

            // A ustunidagi o'lchamlarni yig'ish
            if (preg_match('/^\d{2,3}-\d{2,3}$/', $aColumn)) {
                $sizes[] = $aColumn;
            }

            // Agar `E` ustuni avvalgidan farqli bo‘lsa, blokni saqlab, yangisini boshlaymiz
            if ($lastEValue !== null && $lastEValue !== $eColumn) {
                $data[] = [
                    'block' => $currentBlock,
                    'h_sum' => $hSum // H ustunining yig‘indisini qo‘shamiz
                ];
                $currentBlock = [];
                $hSum = 0;
            }

            // Hozirgi qatorni blokga qo‘shamiz
            $currentBlock[] = [
                'a' => $aColumn,
                'b' => (string)$sheet->getCell("B$row")->getValue(),
                'c' => (string)$sheet->getCell("C$row")->getValue(),
                'd' => (string)$sheet->getCell("D$row")->getValue(),
                'e' => (string)$eColumn,
                'f' => (string)$sheet->getCell("F$row")->getValue(),
                'g' => (string)$sheet->getCell("G$row")->getValue(),
                'h' => $hColumn,
                'i' => (string)$sheet->getCell("I$row")->getValue(),
                'j' => (string)$sheet->getCell("J$row")->getValue(),
                'k' => (string)$sheet->getCell("K$row")->getValue(),
                'l' => (string)$sheet->getCell("L$row")->getValue(),
                'm' => (string)$sheet->getCell("M$row")->getValue(),
            ];

            // `H` ustuni qiymatini qo‘shamiz
            $hSum += $hColumn;

            // `E` ustunining oxirgi qiymatini yangilaymiz
            $lastEValue = $eColumn;
        }

        // Oxirgi blokni ham saqlash kerak
        if (!empty($currentBlock)) {
            $data[] = [
                'block' => $currentBlock,
                'h_sum' => $hSum
            ];
        }

        if (empty($data)) {
            return response()->json(['success' => false, 'message' => 'Hech qanday yaroqli ma’lumot topilmadi!'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'sizes' => array_values(array_unique($sizes))
        ]);
    }
}

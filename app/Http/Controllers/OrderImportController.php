<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

class OrderImportController extends Controller
{
    public function import(Request $request)
    {
        // Faylni yuklash
        $file = $request->file('file');

        // Agar fayl noto‘g‘ri yuklangan bo‘lsa, xatolik qaytarish
        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => 'Fayl noto‘g‘ri yuklangan!'], 400);
        }

        try {
            // Excel faylini o‘qish
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();

            // Oxirgi satr va ustunni olish
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();

            Log::info("Oxirgi satr: $highestRow, Oxirgi ustun: $highestColumn");

            // Agar faylda yetarli ma’lumot bo‘lmasa, xatolik qaytarish
            if ($highestRow < 2) {
                return response()->json(['success' => false, 'message' => 'Fayl ichida maʼlumot yo‘q!'], 400);
            }

            $data = [];
            $row = 2; // 1-satr sarlavha bo‘lishi mumkin, shuning uchun 2-dan boshlab o‘qiymiz

            // Ma’lumotlarni o‘qish
            while ($row <= $highestRow) {
                $eColumn = $sheet->getCell("E$row")->getValue();

                // Agar "E" ustunidagi qiymat bo‘sh bo‘lsa, siklni to‘xtatamiz
                if (is_null($eColumn) || trim((string)$eColumn) === "") {
                    break;
                }

                try {
                    // Har bir satr ma’lumotlarini massivga saqlaymiz
                    $data[] = [
                        'a' => (string)$sheet->getCell("A$row")->getValue(),
                        'b' => (string)$sheet->getCell("B$row")->getValue(),
                        'c' => (string)$sheet->getCell("C$row")->getValue(),
                        'd' => (string)$sheet->getCell("D$row")->getValue(),
                        'e' => (string)$eColumn,
                        'f' => (string)$sheet->getCell("F$row")->getValue(),
                        'g' => (string)$sheet->getCell("G$row")->getValue(),
                        'h' => (string)$sheet->getCell("H$row")->getValue(),
                        'i' => (string)$sheet->getCell("I$row")->getValue(),
                        'j' => (string)$sheet->getCell("J$row")->getValue(),
                        'k' => (string)$sheet->getCell("K$row")->getValue(),
                        'l' => (string)$sheet->getCell("L$row")->getValue(),
                        'm' => (string)$sheet->getCell("M$row")->getValue(),
                    ];
                } catch (\Exception $ex) {
                    Log::error("Xatolik: " . $ex->getMessage() . " | Row: $row");
                }

                $row++;
            }

            // Agar hech qanday ma’lumot olinmagan bo‘lsa, xatolik qaytarish
            if (empty($data)) {
                return response()->json(['success' => false, 'message' => 'Fayl ichida yaroqli ma’lumot topilmadi!'], 400);
            }

            // Ma’lumotlarni JSON formatida qaytarish
            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error("Faylni o‘qishda xatolik: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Faylni o‘qishda xatolik yuz berdi!'], 500);
        }
    }
}

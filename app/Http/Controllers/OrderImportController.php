<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OrderImportController extends Controller
{
    public function import(Request $request)
    {
        // Faylni olish
        $file = $request->file('file');

        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => 'Fayl noto‘g‘ri yuklangan!'], 400);
        }

        // Excel faylni yuklash
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Faylni o‘qishda xatolik: ' . $e->getMessage()], 500);
        }

        // Oxirgi qator va ustunni aniqlash
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        if ($highestRow < 3) {
            return response()->json(['success' => false, 'message' => 'Fayl ichida yaroqli ma’lumot topilmadi!'], 400);
        }

        $data = [];
        for ($row = 3; $row <= $highestRow; $row++) {
            $eColumn = $sheet->getCell("F$row")->getValue();

            if (is_null($eColumn) || trim((string)$eColumn) === "") {
                continue;
            }

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
        }

        if (empty($data)) {
            return response()->json(['success' => false, 'message' => 'Hech qanday yaroqli ma’lumot topilmadi!'], 400);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OrderImportController extends Controller
{
    public function import(Request $request)
    {
        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            return response()->json(['success' => false, 'message' => 'Fayl yuklanmadi yoki noto‘g‘ri!'], 400);
        }

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        $data = [];
        $startRow = 2; // Agar 1-qator sarlavha bo‘lsa, 2-qatordan boshlaymiz
        $maxRow = $sheet->getHighestRow();

        for ($row = $startRow; $row <= $maxRow; $row++) {
            $eColumn = trim((string)$sheet->getCell("E$row")->getValue());

            // Agar butun qator bo‘sh bo‘lsa, davom etamiz
            if (empty($eColumn)) {
                continue;
            }

            $data[] = [
                'a' => trim((string)$sheet->getCell("A$row")->getValue()),
                'b' => trim((string)$sheet->getCell("B$row")->getValue()),
                'c' => trim((string)$sheet->getCell("C$row")->getValue()),
                'd' => trim((string)$sheet->getCell("D$row")->getValue()),
                'e' => $eColumn,
                'f' => trim((string)$sheet->getCell("F$row")->getValue()),
                'g' => trim((string)$sheet->getCell("G$row")->getValue()),
                'h' => trim((string)$sheet->getCell("H$row")->getValue()),
                'i' => trim((string)$sheet->getCell("I$row")->getValue()),
                'j' => trim((string)$sheet->getCell("J$row")->getValue()),
                'k' => trim((string)$sheet->getCell("K$row")->getValue()),
                'l' => trim((string)$sheet->getCell("L$row")->getValue()),
                'm' => trim((string)$sheet->getCell("M$row")->getValue()),
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
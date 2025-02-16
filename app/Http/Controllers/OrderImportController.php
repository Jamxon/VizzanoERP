<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OrderImportController extends Controller
{
    public function import(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json(['success' => false, 'message' => 'Fayl yuklanmadi!'], 400);
        }

        $file = $request->file('file');

        // Excel faylni yuklamasdan o'qish
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        $data = [];
        $row = 1; // Barcha qatorlarni tekshiramiz

        while (true) {
            $eColumn = $sheet->getCell("E$row")->getValue();

            // E ustuni butunlay bo‘sh yoki null bo‘lsa, tsiklni to‘xtatamiz
            if (is_null($eColumn) || trim((string)$eColumn) === "") {
                break;
            }

            // Faqat non-empty satrlarni olamiz
            $data[] = [
                'a' => $sheet->getCell("A$row")->getValue(),
                'b' => $sheet->getCell("B$row")->getValue(),
                'c' => $sheet->getCell("C$row")->getValue(),
                'd' => $sheet->getCell("D$row")->getValue(),
                'e' => $eColumn,
                'f' => $sheet->getCell("F$row")->getValue(),
                'g' => $sheet->getCell("G$row")->getValue(),
                'h' => $sheet->getCell("H$row")->getValue(),
                'i' => $sheet->getCell("I$row")->getValue(),
                'j' => $sheet->getCell("J$row")->getValue(),
                'k' => $sheet->getCell("K$row")->getValue(),
                'l' => $sheet->getCell("L$row")->getValue(),
                'm' => $sheet->getCell("M$row")->getValue(),
            ];

            $row++;
        }

        // JSON qaytarish
        return response()->json(['success' => true, 'data' => $data]);
    }
}

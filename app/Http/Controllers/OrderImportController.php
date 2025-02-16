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

            // Formulalarni hisoblash uchun getCalculatedValue() ishlatamiz
            $data[] = [
                'a' => $sheet->getCell("A$row")->getCalculatedValue(),
                'b' => $sheet->getCell("B$row")->getCalculatedValue(),
                'c' => $sheet->getCell("C$row")->getCalculatedValue(),
                'd' => $sheet->getCell("D$row")->getCalculatedValue(),
                'e' => $eColumn, // Bu allaqachon getValue() bilan olinmoqda
                'f' => $sheet->getCell("F$row")->getCalculatedValue(),
                'g' => $sheet->getCell("G$row")->getCalculatedValue(),
                'h' => $sheet->getCell("H$row")->getCalculatedValue(),
                'i' => $sheet->getCell("I$row")->getCalculatedValue(),
                'j' => $sheet->getCell("J$row")->getCalculatedValue(),
                'k' => $sheet->getCell("K$row")->getCalculatedValue(),
                'l' => $sheet->getCell("L$row")->getCalculatedValue(),
                'm' => $sheet->getCell("M$row")->getCalculatedValue(),
            ];

            $row++;
        }

        // JSON qaytarish
        return response()->json(['success' => true, 'data' => $data]);
    }
}

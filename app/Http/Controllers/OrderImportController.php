<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

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
            Log::info("Row: $row | E ustun qiymati: " . json_encode($eColumn));

            // E ustuni butunlay bo‘sh yoki null bo‘lsa, tsiklni to‘xtatamiz
            if (is_null($eColumn) || trim((string)$eColumn) === "") {
                break;
            }

            try {
                // Formulalar va oddiy qiymatlarni o'qish
                $data[] = [
                    'a' => $sheet->getCell("A$row")->isFormula() ? $sheet->getCell("A$row")->getCalculatedValue() : $sheet->getCell("A$row")->getValue(),
                    'b' => $sheet->getCell("B$row")->isFormula() ? $sheet->getCell("B$row")->getCalculatedValue() : $sheet->getCell("B$row")->getValue(),
                    'c' => $sheet->getCell("C$row")->isFormula() ? $sheet->getCell("C$row")->getCalculatedValue() : $sheet->getCell("C$row")->getValue(),
                    'd' => $sheet->getCell("D$row")->isFormula() ? $sheet->getCell("D$row")->getCalculatedValue() : $sheet->getCell("D$row")->getValue(),
                    'e' => $eColumn,
                    'f' => $sheet->getCell("F$row")->isFormula() ? $sheet->getCell("F$row")->getCalculatedValue() : $sheet->getCell("F$row")->getValue(),
                    'g' => $sheet->getCell("G$row")->isFormula() ? $sheet->getCell("G$row")->getCalculatedValue() : $sheet->getCell("G$row")->getValue(),
                    'h' => $sheet->getCell("H$row")->isFormula() ? $sheet->getCell("H$row")->getCalculatedValue() : $sheet->getCell("H$row")->getValue(),
                    'i' => $sheet->getCell("I$row")->isFormula() ? $sheet->getCell("I$row")->getCalculatedValue() : $sheet->getCell("I$row")->getValue(),
                    'j' => $sheet->getCell("J$row")->isFormula() ? $sheet->getCell("J$row")->getCalculatedValue() : $sheet->getCell("J$row")->getValue(),
                    'k' => $sheet->getCell("K$row")->isFormula() ? $sheet->getCell("K$row")->getCalculatedValue() : $sheet->getCell("K$row")->getValue(),
                    'l' => $sheet->getCell("L$row")->isFormula() ? $sheet->getCell("L$row")->getCalculatedValue() : $sheet->getCell("L$row")->getValue(),
                    'm' => $sheet->getCell("M$row")->isFormula() ? $sheet->getCell("M$row")->getCalculatedValue() : $sheet->getCell("M$row")->getValue(),
                ];
            } catch (\Exception $ex) {
                Log::error("Xatolik: " . $ex->getMessage() . " | Row: $row");
            }

            $row++;
        }

        Log::info("Final Data: " . json_encode($data));

        // JSON qaytarish
        return response()->json(['success' => true, 'data' => $data]);
    }
}

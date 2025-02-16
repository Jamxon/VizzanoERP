<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

class OrderImportController extends Controller
{
    public function import(Request $request)
    {
        $file = $request->file('file');

        if (!$file->isValid()) {
            return response()->json(['success' => false, 'message' => 'Fayl noto‘g‘ri yuklangan!'], 400);
        }

        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow(); // Oxirgi satrni olamiz
        $highestColumn = $sheet->getHighestColumn(); // Oxirgi ustunni olamiz

        Log::info("Oxirgi satr: $highestRow, Oxirgi ustun: $highestColumn");

        if ($highestRow < 2) {
            return response()->json(['success' => false, 'message' => 'Fayl ichida maʼlumot yo‘q!'], 400);
        }
        $data = [];
        $row = 1;

        while (true) {
            $eColumn = $sheet->getCell("E$row")->getValue();
            if (is_null($eColumn) || trim((string)$eColumn) === "") {
                break;
            }

            try {
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

        return response()->json(['success' => true, 'data' => $data]);
    }
}

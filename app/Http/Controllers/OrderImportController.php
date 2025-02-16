<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OrderImportController extends Controller
{
    public function import(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!$request->hasFile('file')) {
            return response()->json(['success' => false, 'message' => 'Fayl yuklanmadi!'], 400);
        }

        $file = $request->file('file');

        // Excel faylni bevosita o‘qish
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        $data = [];
        $row = 2; // 1-qator sarlavha bo‘lsa, 2-qatordan boshlaymiz

        while (true) {
            $eColumn = trim($sheet->getCell("E$row")->getValue());

            if ($eColumn === "") {
                break; // Agar E ustuni bo‘sh bo‘lsa, tsiklni to‘xtatamiz
            }

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

        return response()->json(['success' => true, 'data' => $data]);
    }
}

<?php

namespace App\Jobs;

use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Order;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessExcelImport implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $file;

    /**
     * Create a new job instance.
     *
     * @param $file
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Execute the job.
     *
     * @return JsonResponse
     */
    public function handle(): JsonResponse
    {
        // Excel faylini o'qish
        try {
            $spreadsheet = IOFactory::load($this->file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            // Xatolikni qaytarish
            return response()->json(['success' => false, 'message' => "Faylni o'qishda xatolik: " . $e->getMessage()], 500);
        }

        $highestRow = $sheet->getHighestRow();
        $data = [];
        $currentGroup = null;
        $currentBlock = [];
        $currentSizes = [];
        $currentSubModel = null;
        $modelImages = [];

        // Rasmlarni saqlash
        foreach ($sheet->getDrawingCollection() as $drawing) {
            if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\Drawing) {
                $coordinates = $drawing->getCoordinates();
                $imageExtension = $drawing->getExtension();
                $imageName = Str::uuid() . '.' . $imageExtension;
                $imagePath = "models/$imageName";

                Storage::disk('public')->put($imagePath, file_get_contents($drawing->getPath()));

                if (str_starts_with($coordinates, 'C') || str_starts_with($coordinates, 'D')) {
                    $modelImages[$coordinates][] = Storage::url($imagePath);
                }
            }
        }

        // Excel ma'lumotlarini yig'ish
        for ($row = 2; $row <= $highestRow; $row++) {
            $aValue = trim((string)$sheet->getCell("A$row")->getValue());
            $dValue = trim((string)$sheet->getCell("D$row")->getValue());
            $eValue = trim((string)$sheet->getCell("E$row")->getValue());
            $fValue = (float) trim($sheet->getCell("F$row")->getValue());
            $gValue = (float) $sheet->getCell("G$row")->getValue();
            $hValue = (float) $sheet->getCell("H$row")->getValue();
            $iValue = (float) $sheet->getCell("I$row")->getValue();
            $jValue = (float) $sheet->getCell("J$row")->getValue();
            $mValue = (float) $sheet->getCell("M$row")->getCalculatedValue();

            $modelUniqueId = md5($eValue);

            if ((preg_match('/^\d{2,3}(?:\/\d{2,3})?$/', $aValue) || preg_match('/^\d{2,3}-\d{2,3}$/', $aValue)) && $aValue !== '') {
                $currentSizes[] = $aValue;
            }

            if ($eValue && $eValue !== $currentGroup) {
                if (!empty($currentBlock)) {
                    $data[] = [
                        'model' => $currentGroup,
                        'submodel' => $currentSubModel,
                        'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                        'model_price' => array_sum(array_column($currentBlock, 'price')),
                        'model_summa' => array_sum(array_column($currentBlock, 'model_summa')),
                        'sizes' => array_values(array_unique($currentSizes)),
                        'images' => $modelImages["C$row"] ?? $modelImages["D$row"] ?? []
                    ];
                }

                $currentGroup = $eValue;
                $currentSubModel = $dValue;
                $currentBlock = [];
                $currentSizes = [];
            }

            if ($fValue > 0 || $gValue > 0 || $hValue > 0) {
                $currentBlock[] = [
                    'size' => $aValue,
                    'price' => $fValue,
                    'quantity' => $gValue,
                    'total' => $hValue,
                    'minut' => $iValue,
                    'total_minut' => $jValue,
                    'model_summa' => $mValue
                ];
            }
        }

        if (!empty($currentBlock)) {
            $data[] = [
                'model' => $currentGroup,
                'submodel' => $currentSubModel,
                'quantity' => array_sum(array_column($currentBlock, 'quantity')),
                'model_price' => array_sum(array_column($currentBlock, 'price')),
                'model_summa' => array_sum(array_column($currentBlock, 'model_summa')),
                'sizes' => array_values(array_unique($currentSizes)),
                'images' => $modelImages["C$row"] ?? $modelImages["D$row"] ?? []
            ];
        }

        // Ma'lumotlarni qaytarish
        // Bu yerda ma'lumotlar bazasiga yozishni amalga oshiring
        return response()->json(['success' => true, 'data' => $data]);
    }
}

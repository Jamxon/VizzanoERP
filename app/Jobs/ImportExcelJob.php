<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;

    /**
     * Job uchun fayl yo'li qabul qilinadi.
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Jobni bajarish.
     */
    public function handle()
    {
        try {
            $spreadsheet = IOFactory::load($this->filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $highestRow = $sheet->getHighestRow();
            $data = [];
            $modelImages = [];

            // **EXCELDAGI RASMLARNI SAQLASH**
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

            // **EXCELDAGI MA'LUMOTLARNI JSONGA YIG'ISH**
            for ($row = 2; $row <= $highestRow; $row++) {
                $eValue = trim((string)$sheet->getCell("E$row")->getValue());
                $fValue = (float) $sheet->getCell("F$row")->getValue();
                $gValue = (float) $sheet->getCell("G$row")->getValue();
                $hValue = (float) $sheet->getCell("H$row")->getValue();
                $mValue = (float) $sheet->getCell("M$row")->getCalculatedValue();

                if ($eValue) {
                    $data[] = [
                        'model' => $eValue,
                        'price' => $fValue,
                        'quantity' => $gValue,
                        'total' => $hValue,
                        'sum' => $mValue,
                        'images' => $modelImages["C$row"] ?? $modelImages["D$row"] ?? []
                    ];
                }
            }

            // JSON natijani saqlaymiz
            Storage::disk('local')->put('import_results.json', json_encode($data));

        } catch (\Exception $e) {
            \Log::error("Importda xatolik: " . $e->getMessage());
        }
    }
}

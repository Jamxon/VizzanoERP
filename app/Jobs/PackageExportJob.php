<?php

namespace App\Jobs;

use App\Exports\BoxStickerExport;
use App\Exports\PackingListExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;
use Illuminate\Support\Facades\Storage;

class PackageExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;
    protected array $summaryList;
    protected array $stickers;
    protected string $fileName;
    protected string $imagePath;
    protected string $submodelName;
    protected string $modelName;

    public function __construct(array $data, array $summaryList, array $stickers, string $fileName, string $imagePath, string $submodelName, string $modelName)
    {
        $this->data = $data;
        $this->summaryList = $summaryList;
        $this->stickers = $stickers;
        $this->fileName = $fileName;
        $this->imagePath = $imagePath;
        $this->submodelName = $submodelName;
        $this->modelName = $modelName;
    }

    public function handle()
    {
        // Temp papka yaratish (ixtiyoriy, lekin ishlatilsin)
        $tempDirName = 'temp_' . now()->timestamp . '_' . uniqid();
        $tempDir = storage_path('app/exports/' . $tempDirName);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Excel fayllarni shu temp papkaga saqlaymiz
        $packingFileRel = $tempDirName . '/packing_list.xlsx'; // nisbiy yo'l
        $boxFileRel = $tempDirName . '/box_stickers.xlsx';

        Excel::store(new PackingListExport($this->data, $this->summaryList), $packingFileRel);
        $boxExport = new BoxStickerExport($this->stickers, $this->imagePath, $this->submodelName, $this->modelName);
        Excel::store($boxExport, $boxFileRel);

        // To'liq yo'llar:
        $packingFile = storage_path('app/exports/' . $packingFileRel);
        $boxFile = storage_path('app/exports/' . $boxFileRel);
        $zipPath = storage_path('app/exports/' . $this->fileName);

        // Fayl mavjudligini tekshirish
        if (!file_exists($packingFile)) {
            return;
        }

        if (!file_exists($boxFile)) {
            return;
        }

        // Zip yaratish
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($packingFile, 'packing_list.xlsx');
            $zip->addFile($boxFile, 'box_stickers.xlsx');
            $zip->close();
        }

        // Temp fayllarni o'chirish
        if (file_exists($packingFile)) unlink($packingFile);
        if (file_exists($boxFile)) unlink($boxFile);

        // Temp papkani o'chirish (faqat bo'sh bo'lsa)
        if (is_dir($tempDir)) rmdir($tempDir);
    }

}

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
        $tempDir = storage_path('app/exports/temp_' . now()->timestamp . '_' . uniqid());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $boxExport = new BoxStickerExport($this->stickers, $this->imagePath, $this->submodelName, $this->modelName);
        Excel::store(new PackingListExport($this->data, $this->summaryList), 'exports/packing_list.xlsx');
        Excel::store($boxExport, 'exports/box_stickers.xlsx');

        $packingFile = storage_path('app/exports/packing_list.xlsx');
        $boxFile = storage_path('app/exports/box_stickers.xlsx');
        Excel::store($boxExport, $boxFile);

        // 3. Zip fayl yaratish
        $zipPath = storage_path('app/exports/' . $this->fileName);
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($packingFile, 'packing_list.xlsx');
            $zip->addFile($boxFile, 'box_stickers.xlsx');
            $zip->close();
        }

        // 4. Temp fayllarni o'chirish
        unlink($packingFile);
        unlink($boxFile);
        rmdir($tempDir);
    }
}

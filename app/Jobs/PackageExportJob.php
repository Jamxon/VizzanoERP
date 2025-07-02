<?php

namespace App\Jobs;

use App\Exports\PackingListExport;
use App\Exports\BoxStickerExport;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;
use Illuminate\Support\Str;

class PackageExportJob implements ShouldQueue
{

    use InteractsWithQueue, Queueable, SerializesModels;
    protected array $data;
    protected array $summary;

    protected array $stickers;
    protected string $fileName;
    protected string $absolutePath;
    protected string $submodel;
    protected string $model;

    public function __construct(array $data, array $summary, array $stickers, string $fileName, string $absolutePath,  string $submodel, string $model)
    {
        $this->data = $data;
        $this->summary = $summary;
        $this->stickers = $stickers;
        $this->fileName = $fileName;
        $this->absolutePath = $absolutePath;
        $this->submodel = $submodel;
        $this->model = $model;
    }

// App\Jobs\PackageExportJob.php ichida

    public function handle(): void
    {
        $folder = 'exports/temp_' . now()->timestamp . '_' . Str::random(6);
        Storage::disk('public')->makeDirectory($folder);

        $packingPath = "$folder/packing_list.xlsx";
        $stickerPath = "$folder/box_sticker.xlsx";

        // Excel fayllarni saqlash (public diskda)
        Excel::store(new PackingListExport($this->data, $this->summary), $packingPath, 'public');
        Excel::store(new BoxStickerExport($this->stickers, $this->absolutePath, $this->submodel, $this->model), $stickerPath, 'public');

        // ZIP faylni yaratamiz
        $zipFileName = "exports/{$this->fileName}";
        $zipFullPath = storage_path("app/public/{$zipFileName}");

        $zip = new ZipArchive;
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFile(storage_path("app/public/{$packingPath}"), 'packing_list.xlsx');
            $zip->addFile(storage_path("app/public/{$stickerPath}"), 'box_sticker.xlsx');
            $zip->close();
        } else {
            Log::error("Zip fayl ochilmadi: $zipFullPath");
        }
    }


}

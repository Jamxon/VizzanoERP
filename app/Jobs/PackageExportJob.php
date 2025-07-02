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

    public function __construct(array $data, array $summary, array $stickers, string $fileName, string $absolutePath)
    {
        $this->data = $data;
        $this->summary = $summary;
        $this->stickers = $stickers;
        $this->fileName = $fileName;
        $this->absolutePath = $absolutePath;
    }

// App\Jobs\PackageExportJob.php ichida

    public function handle(): void
    {
        $folder = 'exports/temp_' . now()->timestamp . '_' . Str::random(6);
        Storage::makeDirectory($folder);

        $packingPath = "$folder/packing_list.xlsx";
        $stickerPath = "$folder/box_sticker.xlsx";
        $zipPath = "$folder/packing_result.zip";
        $absolutePath = $this->absolutePath ? 'public' : 'local';

        Excel::store(new PackingListExport($this->data, $this->summary), $packingPath);
        Excel::store(new BoxStickerExport($this->stickers), $stickerPath, $absolutePath);

        $zip = new \ZipArchive;
        $zipPath = "exports/{$this->fileName}";
        $zipFullPath = storage_path("app/{$zipPath}");

        if ($zip->open($zipFullPath, \ZipArchive::CREATE) === TRUE) {
            $zip->addFile(storage_path("app/{$packingPath}"), 'packing_list.xlsx');
            $zip->addFile(storage_path("app/{$stickerPath}"), 'box_sticker.xlsx');
            $zip->close();
        }

        // ZIP faylni public ga ko‘chir
        $publicPath = "public/exports/".basename($zipPath);
        Storage::copy($zipPath, "public/exports/{$this->fileName}");
    }

}

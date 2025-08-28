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
use Illuminate\Support\Facades\File;

class PackageExportJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    // Queue optimizatsiyasi
    public $timeout = 300; // 5 daqiqa timeout
    public $tries = 3; // 3 marta urinish
    public $maxExceptions = 3;

    protected array $data;
    protected array $summary;
    protected array $stickers;
    protected string $fileName;
    protected ?string $absolutePath;
    protected string $submodel;
    protected string $model;

    public function __construct(array $data, array $summary, array $stickers, string $fileName, ?string $absolutePath, string $submodel, string $model)
    {
        $this->data = $data;
        $this->summary = $summary;
        $this->stickers = $stickers;
        $this->fileName = $fileName;
        $this->absolutePath = $absolutePath;
        $this->submodel = $submodel;
        $this->model = $model;

        // Memory optimizatsiyasi
        $this->onQueue('high'); // Yuqori prioritet queue
    }

    public function handle(): void
    {
        try {
            // Memory limit oshirish
            ini_set('memory_limit', '2G');
            set_time_limit(0); // Unlimited for background job

            $timestamp = now()->timestamp;
            $random = Str::random(6);
            $folder = "exports/temp_{$timestamp}_{$random}";

            // Papka yaratishdan oldin tekshirish
            $fullFolder = storage_path("app/public/{$folder}");
            if (!File::exists($fullFolder)) {
                File::makeDirectory($fullFolder, 0755, true);
            }

            $packingPath = "$folder/packing_list.xlsx";
            $stickerPdfPath = "$folder/box_sticker.pdf";

            // Parallel processing uchun fayllarni alohida yaratish
            $this->createExcelFileOptimized($packingPath);
            $this->createPdfFileOptimized($stickerPdfPath);

            // ZIP fayl yaratish (optimizatsiyalangan)
            $this->createZipFileOptimized($folder, $packingPath, $stickerPdfPath);

            // Vaqtinchalik fayllarni tozalash
            $this->cleanupTempFiles($folder);

        } catch (\Exception $e) {
            Log::error('PackageExportJob xatolik: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function createExcelFileOptimized(string $packingPath): void
    {
        // Excel yaratishni optimizatsiya qilish
        $export = new PackingListExport($this->data, $this->summary);

        // Chunk processing bilan katta ma'lumotlarni qayta ishlash
        Excel::store($export, $packingPath, 'public', null, [
            'memory_limit' => '256M',
            'temp_dir' => storage_path('app/temp'),
        ]);
    }

    private function createPdfFileOptimized(string $stickerPdfPath): void
    {
        // PDF yaratishni optimizatsiya qilish
        $stickerExport = new BoxStickerExport(
            $this->stickers,
            $this->absolutePath,
            $this->submodel,
            $this->model
        );

        $stickerExport->store($stickerPdfPath);
    }

    private function createZipFileOptimized(string $folder, string $packingPath, string $stickerPdfPath): void
    {
        $zipFileName = "exports/{$this->fileName}";
        $zipFullPath = storage_path("app/public/{$zipFileName}");

        // ZIP papkasini yaratish
        $zipDir = dirname($zipFullPath);
        if (!File::exists($zipDir)) {
            File::makeDirectory($zipDir, 0755, true);
        }

        $zip = new ZipArchive;
        $result = $zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result === TRUE) {
            $excelFile = storage_path("app/public/{$packingPath}");
            $pdfFile = storage_path("app/public/{$stickerPdfPath}");

            // Fayllar mavjudligini tekshirish
            if (File::exists($excelFile)) {
                $zip->addFile($excelFile, 'packing_list.xlsx');
            }

            if (File::exists($pdfFile)) {
                $zip->addFile($pdfFile, 'box_sticker.pdf');
            }

            $zip->close();

            Log::info("ZIP fayl muvaffaqiyatli yaratildi: {$zipFullPath}");
        } else {
            throw new \Exception("ZIP fayl ochilmadi. Xato kodi: {$result}. Fayl: {$zipFullPath}");
        }
    }

    private function cleanupTempFiles(string $folder): void
    {
        // Vaqtinchalik fayllarni tozalash
        try {
            $fullPath = storage_path("app/public/{$folder}");
            if (File::exists($fullPath)) {
                File::deleteDirectory($fullPath);
                Log::info("Vaqtinchalik fayllar tozalandi: {$fullPath}");
            }
        } catch (\Exception $e) {
            Log::warning("Vaqtinchalik fayllarni tozalashda xatolik: " . $e->getMessage());
            // Bu xatolik asosiy jarayonni to'xtatmasin
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PackageExportJob muvaffaqiyatsiz tugadi', [
            'fileName' => $this->fileName,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    // Memory optimizatsiyasi uchun
    public function __destruct()
    {
        // Memory'ni tozalash
        $this->data = [];
        $this->summary = [];
        $this->stickers = [];
    }
}
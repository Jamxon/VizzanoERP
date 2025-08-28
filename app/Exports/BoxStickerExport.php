<?php

namespace App\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;

class BoxStickerExport
{
    protected array $stickers;
    protected ?string $imagePath;
    protected string $submodel;
    protected string $model;

    public function __construct(array $stickers, ?string $imagePath, string $submodel, string $model)
    {
        $this->stickers = $stickers;
        $this->imagePath = $imagePath;
        $this->submodel = $submodel;
        $this->model = $model;
    }

    public function store(string $path): void
    {
        // View'ni optimizatsiya bilan yaratish
        $viewData = $this->prepareViewData();

        // PDF konfiguratsiyasi
        $pdf = Pdf::loadView('exports.box_sticker', $viewData)
            ->setPaper('a4')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 150,
                'defaultPaperSize' => 'a4',
                'chroot' => public_path(),
                'logOutputFile' => storage_path('logs/dompdf.log'),
                'tempDir' => storage_path('app/temp'),
                // Memory optimizatsiyasi
                'isRemoteEnabled' => false,
                'debugKeepTemp' => false,
            ]);

        // PDF'ni yaratish va saqlash
        $output = $pdf->output();
        Storage::disk('public')->put($path, $output);

        // Memory'ni tozalash
        unset($pdf, $output, $viewData);
    }

    private function prepareViewData(): array
    {
        // Rasm yo'lini optimizatsiya qilish
        $optimizedImagePath = $this->optimizeImagePath();

        // Sticker ma'lumotlarini optimizatsiya qilish
        $optimizedStickers = $this->optimizeStickers();

        return [
            'stickers' => $optimizedStickers,
            'imagePath' => $optimizedImagePath,
            'submodel' => $this->submodel,
            'model' => $this->model,
        ];
    }

    private function optimizeImagePath(): ?string
    {
        if (!$this->imagePath || !file_exists($this->imagePath)) {
            return null;
        }

        // Rasm hajmini tekshirish va kichikroq versiya yaratish
        $fileSize = filesize($this->imagePath);

        // Agar fayl 1MB dan katta bo'lsa, cache'dan kichik versiya olish
        if ($fileSize > 1024 * 1024) {
            return $this->createOptimizedImage();
        }

        return $this->imagePath;
    }

    private function createOptimizedImage(): ?string
    {
        try {
            $cacheKey = 'optimized_image_' . md5($this->imagePath . filemtime($this->imagePath));

            return Cache::remember($cacheKey, 3600, function () {
                $imageInfo = getimagesize($this->imagePath);
                if (!$imageInfo) {
                    return $this->imagePath;
                }

                // Faqat katta rasmlarni optimallashtirish
                [$width, $height] = $imageInfo;
                if ($width <= 800 && $height <= 600) {
                    return $this->imagePath;
                }

                // Optimallashtirilgan rasm yaratish
                $optimizedPath = $this->resizeImage($this->imagePath, 400, 300);
                return $optimizedPath ?: $this->imagePath;
            });
        } catch (\Exception $e) {
            \Log::warning('Rasm optimizatsiyasida xatolik: ' . $e->getMessage());
            return $this->imagePath;
        }
    }

    private function resizeImage(string $sourcePath, int $maxWidth, int $maxHeight): ?string
    {
        try {
            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) {
                return null;
            }

            [$originalWidth, $originalHeight, $imageType] = $imageInfo;

            // Proporsiyani saqlash
            $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            $newWidth = (int)($originalWidth * $ratio);
            $newHeight = (int)($originalHeight * $ratio);

            // Yangi rasm yaratish
            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            // PNG uchun shaffoflikni saqlash
            if ($imageType === IMAGETYPE_PNG) {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
            }

            $sourceImage = $this->createImageFromFile($sourcePath, $imageType);

            if (!$sourceImage) {
                return null;
            }

            // Rasmni o'lchamini o'zgartirish
            imagecopyresampled(
                $newImage, $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $originalWidth, $originalHeight
            );

            // Optimallashtirilgan fayl yo'li
            $optimizedPath = storage_path('app/temp/optimized_' . basename($sourcePath));

            // Temp papkasini yaratish
            $tempDir = dirname($optimizedPath);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Faylni saqlash
            $saved = match ($imageType) {
                IMAGETYPE_JPEG => imagejpeg($newImage, $optimizedPath, 85),
                IMAGETYPE_PNG => imagepng($newImage, $optimizedPath, 8),
                IMAGETYPE_GIF => imagegif($newImage, $optimizedPath),
                default => false
            };

            // Memory'ni tozalash
            imagedestroy($newImage);
            imagedestroy($sourceImage);

            return $saved ? $optimizedPath : null;

        } catch (\Exception $e) {
            \Log::warning('Rasm o\'lchamini o\'zgartirishda xatolik: ' . $e->getMessage());
            return null;
        }
    }

    private function createImageFromFile(string $path, int $imageType)
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            default => null
        };
    }

    private function optimizeStickers(): array
    {
        // Sticker ma'lumotlarini optimizatsiya qilish
        $optimizedStickers = [];

        foreach ($this->stickers as $index => $sticker) {
            $optimizedSticker = $this->optimizeSingleSticker($sticker, $index);
            if ($optimizedSticker) {
                $optimizedStickers[] = $optimizedSticker;
            }
        }

        return $optimizedStickers;
    }

    private function optimizeSingleSticker(array $sticker, int $index): ?array
    {
        try {
            // Asosiy ma'lumotlarni tekshirish
            if (!isset($sticker['color'], $sticker['model'])) {
                \Log::warning("Sticker #{$index} da asosiy ma'lumotlar yo'q");
                return null;
            }

            // Ma'lumotlarni tozalash va formatlash
            $optimized = [
                'color' => $this->cleanString($sticker['color']),
                'model' => $this->cleanString($sticker['model']),
                'orderSizes' => $sticker['orderSizes'] ?? [],
                'sizes' => [],
                'totals' => ['netto' => 0, 'brutto' => 0]
            ];

            // Sizes ma'lumotlarini qayta ishlash
            foreach ($sticker as $key => $value) {
                if (is_numeric($key) && is_array($value) && count($value) >= 2) {
                    // Agar bu sizes ma'lumoti bo'lsa
                    if (is_string($value[0]) && is_numeric($value[1])) {
                        $optimized['sizes'][] = [
                            'name' => $this->cleanString($value[0]),
                            'qty' => (int)$value[1]
                        ];
                    }
                    // Agar bu totals ma'lumoti bo'lsa (oxirgi element)
                    elseif (is_numeric($value[0]) && is_numeric($value[1])) {
                        $optimized['totals'] = [
                            'netto' => round((float)$value[0], 2),
                            'brutto' => round((float)$value[1], 2)
                        ];
                    }
                }
            }

            return $optimized;

        } catch (\Exception $e) {
            \Log::warning("Sticker #{$index} ni optimizatsiya qilishda xatolik: " . $e->getMessage());
            return null;
        }
    }

    private function cleanString(?string $value): string
    {
        if (!$value) {
            return '';
        }

        // HTML entities va keraksiz bo'shliqlarni tozalash
        $cleaned = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // Maxsus belgilarni tozalash
        $cleaned = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleaned);

        return $cleaned;
    }

    public function __destruct()
    {
        // Memory'ni tozalash
        $this->stickers = [];

        // Vaqtinchalik fayllarni tozalash
        $this->cleanupTempImages();
    }

    private function cleanupTempImages(): void
    {
        try {
            $tempDir = storage_path('app/temp');
            if (is_dir($tempDir)) {
                $files = glob($tempDir . '/optimized_*');
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < (time() - 3600)) { // 1 soatdan eski fayllar
                        unlink($file);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::debug('Temp fayllarni tozalashda xatolik: ' . $e->getMessage());
        }
    }
}
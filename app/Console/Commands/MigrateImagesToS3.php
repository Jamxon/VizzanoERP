<?php

namespace App\Console\Commands;

use App\Models\EmployeeAbsence;
use App\Models\EmployeeHolidays;
use App\Models\Issue;
use App\Models\ModelImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateImagesToS3 extends Command
{
    protected $signature = 'images:migrate-to-s3 {--dry-run : Test without actual migration}';
    protected $description = 'Migrate old images (employees, attendance, models, absences, holidays, issues) to S3 and update DB paths';

    protected $imagePaths = [
        'public/models/',
        'models/',
        'absences/',
        'holidays/',
        'issues/',
    ];

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - hech narsa o‘zgartirilmaydi');
            $this->newLine();
        }

        $this->info('🚀 Starting migration process...');
        $this->newLine();

        $summary = [];

        // 3️⃣ Model Images
        $summary[] = $this->migrateImages(ModelImages::class, 'image', 'modelImages', $this->imagePaths);

        // 4️⃣ Employee Absences
        $summary[] = $this->migrateImages(EmployeeAbsence::class, 'image', 'employeeAbsences', $this->imagePaths);

        // 5️⃣ Employee Holidays
        $summary[] = $this->migrateImages(EmployeeHolidays::class, 'image', 'employeeHolidays', $this->imagePaths);

        // 6️⃣ Issues
        $summary[] = $this->migrateImages(Issue::class, 'image', 'issues', $this->imagePaths);

        // 📊 Yakuniy summary
        $this->newLine();
        $this->info($dryRun ? '🧪 DRY RUN tugadi!' : '🎉 Migration completed!');
        $this->table(
            ['Table', 'Migrated', 'Skipped', 'Not Found'],
            $summary
        );

        return 0;
    }

    /**
     * Generic image migration function
     */
    protected function migrateImages($modelClass, $column, $folder, $searchPaths)
    {
        $dryRun = $this->option('dry-run');
        $count = 0;
        $skipped = 0;
        $errors = 0;

        $this->info("📂 Migrating {$modelClass} -> {$column} to {$folder}/...");

        $modelClass::whereNotNull($column)->chunk(100, function ($records) use ($modelClass, $column, $folder, $searchPaths, $dryRun, &$count, &$skipped, &$errors) {
            foreach ($records as $rec) {
                $oldUrl = $rec->getRawOriginal($column);

                if (empty($oldUrl)) {
                    continue;
                }

                try {
                    // 🔹 Agar allaqachon S3 bo‘lsa skip
                    if (strpos($oldUrl, 's3.twcstorage.ru') !== false ||
                        strpos($oldUrl, 'amazonaws.com') !== false) {
                        $skipped++;
                        continue;
                    }

                    $filename = basename($oldUrl);

                    // 🔹 Faylni bir nechta papkadan qidirish
                    $localPath = $this->findFile($filename, $searchPaths);

                    if ($localPath) {
                        $newPath = $folder . '/' . $filename;

                        if (!$dryRun) {
                            $fileContents = file_get_contents($localPath);
                            Storage::disk('s3')->put($newPath, $fileContents, 'public');

                            $s3Url = Storage::disk('s3')->url($newPath);
                            $rec->$column = $s3Url;
                            $rec->save();
                        }

                        $count++;
                        $this->line("✅ {$modelClass} #{$rec->id}: {$filename} → {$newPath}");
                    } else {
                        $errors++;
                        if ($errors <= 10) {
                            $this->warn("⚠️ {$modelClass} #{$rec->id}: Fayl topilmadi - {$filename}");
                        }
                    }
                } catch (\Exception $e) {
                    $errors++;
                    if ($errors <= 10) {
                        $this->error("❌ {$modelClass} #{$rec->id}: " . $e->getMessage());
                    }
                }
            }
        });

        $this->newLine();
        $this->info("📊 {$modelClass}: {$count} migrated, {$skipped} skipped, {$errors} not found");

        return [class_basename($modelClass), $count, $skipped, $errors];
    }

    /**
     * Faylni qidirish
     */
    protected function findFile($filename, $paths)
    {
        foreach ($paths as $path) {
            $fullPath = base_path($path . $filename);
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }
        return null;
    }
}

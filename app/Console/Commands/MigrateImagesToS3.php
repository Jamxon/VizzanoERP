<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateImagesToS3 extends Command
{
    protected $signature = 'images:migrate-to-s3 {--dry-run : Test without actual migration} {--scan : Scan all possible image locations}';
    protected $description = 'Migrate old images to S3 and update DB paths';

    protected $imagePaths = [
        'public/images/',
        'public/',
        'storage/app/public/images/',
        'storage/app/public/',
    ];

    protected $hikvisionPaths = [
        'public/hikvision/',
        'storage/app/public/hikvision/',
        'storage/app/public/hikvision_images/',
    ];

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $scan = $this->option('scan');

        if ($scan) {
            return $this->scanImages();
        }

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - Hech narsa o\'zgartirilmaydi');
            $this->newLine();
        }

        $this->info('🚀 Starting migration process...');
        $this->newLine();

        // ========================
        // 1️⃣ EMPLOYEE IMAGES
        // ========================
        $this->info('📸 Migrating employee profile images from public/images/...');

        $employeeCount = 0;
        $employeeSkipped = 0;
        $employeeErrors = 0;

        Employee::whereNotNull('img')->chunk(100, function ($employees) use (&$employeeCount, &$employeeSkipped, &$employeeErrors, $dryRun) {
            foreach ($employees as $employee) {
                $oldUrl = $employee->getRawOriginal('img');

                if (empty($oldUrl)) {
                    continue;
                }

                try {
                    // 🔹 Agar S3 URL bo'lsa - skip
                    if (strpos($oldUrl, 's3.twcstorage.ru') !== false ||
                        strpos($oldUrl, 'amazonaws.com') !== false) {
                        $employeeSkipped++;
                        $this->line("⏭️  Employee #{$employee->id}: Allaqachon S3 URL");
                        continue;
                    }

                    // 🔹 Faqat fayl nomini olish
                    $filename = basename($oldUrl);

                    // 🔹 Bir nechta papkalardan qidirish
                    $localPath = $this->findFile($filename, $this->imagePaths);

                    if ($localPath) {
                        $newPath = 'employees/' . $filename;

                        if (!$dryRun) {
                            // S3 ga yuklash
                            $fileContents = file_get_contents($localPath);
                            Storage::disk('s3')->put($newPath, $fileContents, 'public');

                            // S3 URL olish va database yangilash
                            $s3Url = Storage::disk('s3')->url($newPath);
                            $employee->img = $s3Url;
                            $employee->save();
                        }

                        $employeeCount++;
                        $s3Url = Storage::disk('s3')->url($newPath);
                        $this->info("✅ Employee #{$employee->id}: {$filename} → {$s3Url}");
                    } else {
                        $employeeErrors++;
                        $this->warn("⚠️  Employee #{$employee->id}: Fayl topilmadi - {$localPath}");
                    }
                } catch (\Exception $e) {
                    $employeeErrors++;
                    $this->error("❌ Employee #{$employee->id}: " . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->info("✅ Employee: {$employeeCount} ko'chirildi, {$employeeSkipped} skip, {$employeeErrors} xato");
        $this->newLine();

        // ========================
        // 2️⃣ ATTENDANCE IMAGES
        // ========================
        $this->info('📷 Migrating attendance images from public/hikvision/...');

        $attendanceCount = 0;
        $attendanceSkipped = 0;
        $attendanceErrors = 0;

        Attendance::whereNotNull('check_in_image')->chunk(100, function ($records) use (&$attendanceCount, &$attendanceSkipped, &$attendanceErrors, $dryRun) {
            foreach ($records as $att) {
                $oldUrl = $att->check_in_image;

                if (empty($oldUrl)) {
                    continue;
                }

                try {
                    // 🔹 Agar allaqachon S3 URL bo'lsa - skip
                    if (strpos($oldUrl, 's3.twcstorage.ru') !== false ||
                        strpos($oldUrl, 'amazonaws.com') !== false) {
                        $attendanceSkipped++;
                        continue;
                    }

                    // 🔹 Faqat fayl nomini olish
                    $filename = basename($oldUrl);

                    // 🔹 Bir nechta papkalardan qidirish
                    $localPath = $this->findFile($filename, $this->hikvisionPaths);

                    if ($localPath) {
                        $newPath = 'hikvisionImages/' . $filename;

                        if (!$dryRun) {
                            // S3 ga yuklash
                            $fileContents = file_get_contents($localPath);
                            Storage::disk('s3')->put($newPath, $fileContents, 'public');

                            // S3 URL olish va database yangilash
                            $s3Url = Storage::disk('s3')->url($newPath);
                            $att->check_in_image = $s3Url;
                            $att->save();
                        }

                        $attendanceCount++;
                        $s3Url = Storage::disk('s3')->url($newPath);
                        $this->info("✅ Attendance #{$att->id}: {$filename} → {$s3Url}");
                    } else {
                        $attendanceErrors++;
                        if ($attendanceErrors <= 10) {
                            $this->warn("⚠️  Attendance #{$att->id}: Fayl topilmadi - {$localPath}");
                        }
                    }
                } catch (\Exception $e) {
                    $attendanceErrors++;
                    if ($attendanceErrors <= 10) {
                        $this->error("❌ Attendance #{$att->id}: " . $e->getMessage());
                    }
                }
            }
        });

        $this->newLine();
        $this->info("✅ Attendance: {$attendanceCount} ko'chirildi, {$attendanceSkipped} skip, {$attendanceErrors} topilmadi");
        $this->newLine();

        // ========================
        // 📊 FINAL SUMMARY
        // ========================
        $this->info($dryRun ? '🧪 DRY RUN tugadi!' : '🎉 Migration completed!');
        $this->table(
            ['Type', 'Migrated', 'Skipped', 'Not Found'],
            [
                ['Employees', $employeeCount, $employeeSkipped, $employeeErrors],
                ['Attendances', $attendanceCount, $attendanceSkipped, $attendanceErrors],
                ['Total', $employeeCount + $attendanceCount, $employeeSkipped + $attendanceSkipped, $attendanceErrors + $attendanceErrors],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->info('💡 Haqiqiy migratsiya uchun: php artisan images:migrate-to-s3');
        }

        return 0;
    }

    /**
     * Faylni bir nechta papkalardan qidirish
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

    /**
     * Barcha papkalarni scan qilish
     */
    protected function scanImages()
    {
        $this->info('🔍 Scanning image locations...');
        $this->newLine();

        // Employee images
        $this->info('📸 Employee images scan:');
        $employeeFiles = Employee::whereNotNull('img')
            ->whereNotLike('img', '%s3.twcstorage.ru%')
            ->whereNotLike('img', '%amazonaws.com%')
            ->limit(10)
            ->get();

        foreach ($employeeFiles as $emp) {
            $filename = basename($emp->getRawOriginal('img'));
            $found = false;

            foreach ($this->imagePaths as $path) {
                $fullPath = base_path($path . $filename);
                if (file_exists($fullPath)) {
                    $this->info("✅ Found: {$filename} in {$path}");
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $this->warn("❌ Not found: {$filename}");
            }
        }

        $this->newLine();

        // Attendance images
        $this->info('📷 Attendance images scan:');
        $attFiles = Attendance::whereNotNull('check_in_image')
            ->whereNotLike('check_in_image', '%s3.twcstorage.ru%')
            ->whereNotLike('check_in_image', '%amazonaws.com%')
            ->limit(10)
            ->get();

        foreach ($attFiles as $att) {
            $filename = basename($att->check_in_image);
            $found = false;

            foreach ($this->hikvisionPaths as $path) {
                $fullPath = base_path($path . $filename);
                if (file_exists($fullPath)) {
                    $this->info("✅ Found: {$filename} in {$path}");
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $this->warn("❌ Not found: {$filename}");
            }
        }

        $this->newLine();
        $this->info('💡 Run migration: php artisan images:migrate-to-s3');

        return 0;
    }
}
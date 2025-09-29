<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateImagesToS3 extends Command
{
    protected $signature = 'images:migrate-to-s3';
    protected $description = 'Migrate old storage images to S3 and update DB paths';

    public function handle()
    {
        $this->info('🚀 Starting migration process...');
        $this->newLine();

        // ========================
        // 1️⃣ EMPLOYEE IMAGES
        // ========================
        $this->info('📸 Migrating employee profile images...');

        $employeeCount = 0;
        $employeeErrors = 0;

        Employee::whereNotNull('img')->chunk(100, function ($employees) use (&$employeeCount, &$employeeErrors) {
            foreach ($employees as $employee) {
                $oldUrl = $employee->getRawOriginal('img'); // ⚠️ Accessor o'tkazib yuborish

                if (empty($oldUrl)) {
                    continue;
                }

                try {
                    // 🔹 Har xil formatlarni aniqlash
                    if (filter_var($oldUrl, FILTER_VALIDATE_URL)) {
                        // To'liq URL: http://example.com/storage/images/123456.jpg
                        $oldPath = parse_url($oldUrl, PHP_URL_PATH);
                        // "/storage/images/file.jpg" -> "images/file.jpg"
                        $oldPath = preg_replace('#^/storage/#', '', $oldPath);
                    } elseif (strpos($oldUrl, 'storage/') === 0) {
                        // Nisbiy: storage/images/file.jpg
                        $oldPath = str_replace('storage/', '', $oldUrl);
                    } else {
                        // Oddiy: images/123456.jpg
                        $oldPath = $oldUrl;
                    }

                    // 🔹 Faylni tekshirish
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        $file = Storage::disk('public')->get($oldPath);
                        $filename = basename($oldPath);
                        $newPath = 'employees/' . $filename;

                        // S3 ga yuklash
                        Storage::disk('s3')->put($newPath, $file, 'public');

                        // ✅ Database yangilash (faqat path, accessor keyin URL ga aylantiradi)
                        $employee->img = $newPath;
                        $employee->save();

                        $employeeCount++;
                        $this->info("✅ Employee #{$employee->id}: {$oldPath} → {$newPath}");
                    } else {
                        $employeeErrors++;
                        $this->warn("⚠️  Employee #{$employee->id}: Fayl topilmadi - {$oldPath}");
                        $this->line("   Original: {$oldUrl}");
                    }
                } catch (\Exception $e) {
                    $employeeErrors++;
                    $this->error("❌ Employee #{$employee->id}: " . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->info("✅ Employee images: {$employeeCount} muvaffaqiyatli, {$employeeErrors} xato");
        $this->newLine();

        // ========================
        // 2️⃣ ATTENDANCE IMAGES
        // ========================
        $this->info('📷 Migrating attendance check-in images...');

        $attendanceCount = 0;
        $attendanceErrors = 0;

        Attendance::whereNotNull('check_in_image')->chunk(100, function ($records) use (&$attendanceCount, &$attendanceErrors) {
            foreach ($records as $att) {
                $oldUrl = $att->check_in_image;

                if (empty($oldUrl)) {
                    continue;
                }

                try {
                    $oldPath = null;

                    // 🔹 Turli formatlarni aniqlash
                    if (filter_var($oldUrl, FILTER_VALIDATE_URL)) {
                        // To'liq URL
                        $oldPath = parse_url($oldUrl, PHP_URL_PATH);
                        $oldPath = preg_replace('#^/storage/#', '', $oldPath);
                    } elseif (strpos($oldUrl, 'storage/') === 0) {
                        // storage/hikvisionImages/...
                        $oldPath = str_replace('storage/', '', $oldUrl);
                    } elseif (preg_match('#^[a-f0-9\-]+/hikvisionImages/#', $oldUrl)) {
                        // ⚠️ S3 bucket nomi bilan: 07258afc-45d27b4b-.../hikvisionImages/...
                        // Faqat hikvisionImages/ dan keyingi qismni olish
                        $oldPath = preg_replace('#^[a-f0-9\-]+/#', '', $oldUrl);
                    } elseif (strpos($oldUrl, 'hikvisionImages/') === 0) {
                        // hikvisionImages/file.jpg
                        $oldPath = $oldUrl;
                    } else {
                        // Oddiy path
                        $oldPath = $oldUrl;
                    }

                    // 🔹 Faylni tekshirish
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        $file = Storage::disk('public')->get($oldPath);
                        $filename = basename($oldPath);
                        $newPath = 'hikvisionImages/' . $filename;

                        // S3 ga yuklash
                        Storage::disk('s3')->put($newPath, $file, 'public');

                        // ✅ Database yangilash
                        $att->check_in_image = $newPath;
                        $att->save();

                        $attendanceCount++;
                        $this->info("✅ Attendance #{$att->id}: {$oldPath} → {$newPath}");
                    } else {
                        $attendanceErrors++;
                        $this->warn("⚠️  Attendance #{$att->id}: Fayl topilmadi - {$oldPath}");
                        $this->line("   Original: {$oldUrl}");
                    }
                } catch (\Exception $e) {
                    $attendanceErrors++;
                    $this->error("❌ Attendance #{$att->id}: " . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->info("✅ Attendance images: {$attendanceCount} muvaffaqiyatli, {$attendanceErrors} xato");
        $this->newLine();

        // ========================
        // 📊 FINAL SUMMARY
        // ========================
        $this->info('🎉 Migration completed!');
        $this->table(
            ['Type', 'Success', 'Errors'],
            [
                ['Employees', $employeeCount, $employeeErrors],
                ['Attendances', $attendanceCount, $attendanceErrors],
                ['Total', $employeeCount + $attendanceCount, $employeeErrors + $attendanceErrors],
            ]
        );

        return 0;
    }
}
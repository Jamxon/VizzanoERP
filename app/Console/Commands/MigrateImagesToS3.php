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
        $this->info('📌 Faqat local storage dagi fayllar ko\'chiriladi');
        $this->newLine();

        // ========================
        // 1️⃣ EMPLOYEE IMAGES
        // ========================
        $this->info('📸 Migrating employee profile images...');

        $employeeCount = 0;
        $employeeSkipped = 0;
        $employeeErrors = 0;

        Employee::whereNotNull('img')->chunk(100, function ($employees) use (&$employeeCount, &$employeeSkipped, &$employeeErrors) {
            foreach ($employees as $employee) {
                $oldUrl = $employee->getRawOriginal('img');

                if (empty($oldUrl)) {
                    continue;
                }

                try {
                    // 🔹 Agar S3 URL bo'lsa - o'tkazib yuborish
                    if (strpos($oldUrl, 's3.twcstorage.ru') !== false ||
                        strpos($oldUrl, 'amazonaws.com') !== false) {
                        $employeeSkipped++;
                        $this->line("⏭️  Employee #{$employee->id}: S3 da allaqachon bor, skip");
                        continue;
                    }

                    // 🔹 Local path aniqlash
                    if (filter_var($oldUrl, FILTER_VALIDATE_URL)) {
                        $oldPath = parse_url($oldUrl, PHP_URL_PATH);
                        $oldPath = preg_replace('#^/storage/#', '', $oldPath);
                    } elseif (strpos($oldUrl, 'storage/') === 0) {
                        $oldPath = str_replace('storage/', '', $oldUrl);
                    } else {
                        $oldPath = $oldUrl;
                    }

                    // 🔹 Faylni tekshirish
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        $file = Storage::disk('public')->get($oldPath);
                        $filename = basename($oldPath);
                        $newPath = 'employees/' . $filename;

                        // S3 ga yuklash
                        Storage::disk('s3')->put($newPath, $file, 'public');

                        // ✅ Database yangilash
                        $employee->img = $newPath;
                        $employee->save();

                        $employeeCount++;
                        $this->info("✅ Employee #{$employee->id}: {$oldPath} → {$newPath}");
                    } else {
                        $employeeErrors++;
                        $this->warn("⚠️  Employee #{$employee->id}: Fayl topilmadi - {$oldPath}");
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
        $this->info('📷 Migrating attendance check-in images...');

        $attendanceCount = 0;
        $attendanceSkipped = 0;
        $attendanceErrors = 0;

        Attendance::whereNotNull('check_in_image')->chunk(100, function ($records) use (&$attendanceCount, &$attendanceSkipped, &$attendanceErrors) {
            foreach ($records as $att) {
                $oldUrl = $att->check_in_image;

                if (empty($oldUrl)) {
                    continue;
                }

                try {
                    // 🔹 Agar S3 URL bo'lsa - o'tkazib yuborish
                    if (strpos($oldUrl, 's3.twcstorage.ru') !== false ||
                        strpos($oldUrl, 'amazonaws.com') !== false) {
                        $attendanceSkipped++;
                        continue; // Jim o'tkazib yuborish
                    }

                    $oldPath = null;

                    // 🔹 Local path aniqlash
                    if (filter_var($oldUrl, FILTER_VALIDATE_URL)) {
                        $oldPath = parse_url($oldUrl, PHP_URL_PATH);
                        $oldPath = preg_replace('#^/storage/#', '', $oldPath);
                    } elseif (strpos($oldUrl, 'storage/') === 0) {
                        $oldPath = str_replace('storage/', '', $oldUrl);
                    } elseif (preg_match('#^[a-f0-9\-]+/hikvisionImages/#', $oldUrl)) {
                        // Bucket nomi bilan: 07258afc-.../hikvisionImages/file.jpg
                        $oldPath = preg_replace('#^[a-f0-9\-]+/#', '', $oldUrl);
                    } elseif (strpos($oldUrl, 'hikvisionImages/') === 0) {
                        $oldPath = $oldUrl;
                    } else {
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
                        // Faqat muhim xatolarni ko'rsatish
                        if ($attendanceErrors <= 10) {
                            $this->warn("⚠️  Attendance #{$att->id}: Local da topilmadi - {$oldPath}");
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
        $this->info("✅ Attendance: {$attendanceCount} ko'chirildi, {$attendanceSkipped} S3 da bor, {$attendanceErrors} topilmadi");
        $this->newLine();

        // ========================
        // 📊 FINAL SUMMARY
        // ========================
        $this->info('🎉 Migration completed!');
        $this->table(
            ['Type', 'Migrated', 'Skipped (S3)', 'Not Found'],
            [
                ['Employees', $employeeCount, $employeeSkipped, $employeeErrors],
                ['Attendances', $attendanceCount, $attendanceSkipped, $attendanceErrors],
                ['Total', $employeeCount + $attendanceCount, $employeeSkipped + $attendanceSkipped, $attendanceErrors + $attendanceErrors],
            ]
        );

        $this->newLine();
        $this->info('💡 S3 dagi o\'chgan rasmlar uchun check_in_image = NULL qilish kerakmi?');
        $this->info('   Agar kerak bo\'lsa: UPDATE attendances SET check_in_image = NULL WHERE check_in_image LIKE "%s3.twcstorage.ru%"');

        return 0;
    }
}
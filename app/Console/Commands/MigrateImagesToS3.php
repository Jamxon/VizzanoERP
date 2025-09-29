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
                $oldUrl = $employee->img;

                if (empty($oldUrl)) {
                    continue; // ❌ return emas, continue bo'lishi kerak!
                }

                try {
                    // 🔹 URL yoki path ekanligini aniqlash
                    if (filter_var($oldUrl, FILTER_VALIDATE_URL)) {
                        // To'liq URL: http://example.com/storage/employees/image.jpg
                        $oldPath = parse_url($oldUrl, PHP_URL_PATH);
                        $oldPath = ltrim(str_replace('/storage/', '', $oldPath), '/');
                    } elseif (strpos($oldUrl, 'storage/') === 0) {
                        // Nisbiy path: storage/employees/image.jpg
                        $oldPath = str_replace('storage/', '', $oldUrl);
                    } else {
                        // Faqat path: employees/image.jpg
                        $oldPath = $oldUrl;
                    }

                    // 🔹 Faylni tekshirish va ko'chirish
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        $file = Storage::disk('public')->get($oldPath);
                        $newPath = 'employees/' . basename($oldPath);

                        // S3 ga yuklash
                        Storage::disk('s3')->put($newPath, $file, 'public');

                        // ✅ Database yangilash
                        $employee->img = $newPath;
                        $employee->save();

                        $employeeCount++;
                        $this->info("✅ Employee #{$employee->id}: {$newPath}");
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
                    // 🔹 URL yoki path ekanligini aniqlash
                    if (filter_var($oldUrl, FILTER_VALIDATE_URL)) {
                        // To'liq URL
                        $oldPath = parse_url($oldUrl, PHP_URL_PATH);
                        $oldPath = ltrim(str_replace('/storage/', '', $oldPath), '/');
                    } elseif (strpos($oldUrl, 'storage/') === 0) {
                        // Nisbiy path
                        $oldPath = str_replace('storage/', '', $oldUrl);
                    } else {
                        // Faqat path
                        $oldPath = $oldUrl;
                    }

                    // 🔹 Faylni tekshirish va ko'chirish
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        $file = Storage::disk('public')->get($oldPath);
                        $newPath = 'hikvisionImages/' . basename($oldPath);

                        // S3 ga yuklash
                        Storage::disk('s3')->put($newPath, $file, 'public');

                        // ✅ Database yangilash
                        $att->check_in_image = $newPath;
                        $att->save();

                        $attendanceCount++;
                        $this->info("✅ Attendance #{$att->id}: {$newPath}");
                    } else {
                        $attendanceErrors++;
                        $this->warn("⚠️  Attendance #{$att->id}: Fayl topilmadi - {$oldPath}");
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
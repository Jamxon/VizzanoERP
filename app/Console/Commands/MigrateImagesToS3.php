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
        $this->info('Migrating profile images...');

        Employee::whereNotNull('img')->chunk(100, function ($employees) {
            foreach ($employees as $employee) {
                $oldUrl = $employee->img;

                if (!$oldUrl) {
                    return; // hech narsa qilmaymiz
                }

// 🔹 Agar URL bo‘lsa (http bilan boshlansa) → nisbiy pathni ajratib olamiz
                if (filter_var($oldUrl, FILTER_VALIDATE_URL)) {
                    $oldPath = str_replace(url('storage').'/', '', $oldUrl);
                } else {
                    // 🔹 Aks holda (faqat path saqlangan bo‘lsa) → shuni ishlatamiz
                    $oldPath = $oldUrl;
                }

                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    $file = Storage::disk('public')->get($oldPath);
                    $newPath = 'employees/' . basename($oldPath);

                    Storage::disk('s3')->put($newPath, $file);
                    Storage::disk('s3')->setVisibility($newPath, 'public'); // 🔹 agar ochiq bo‘lishi kerak bo‘lsa

                    $employee->update(['img' => $newPath]);

                    $this->info("✅ Employee {$employee->id} moved: {$newPath}");
                } else {
                    $this->warn("⚠️ Fayl topilmadi: {$oldUrl}");
                }
            }
        });

        $this->info('Profile images migration completed.');


        $this->info('Migrating Attendance check_in images...');
        Attendance::whereNotNull('check_in_image')->chunk(100, function ($records) {
            foreach ($records as $att) {
                $oldUrl = $att->check_in_image;

                if (!$oldUrl) {
                    continue;
                }

                // 🔹 Agar to‘liq URL bo‘lsa → nisbiy pathni ajratib olamiz
                if (filter_var($oldUrl, FILTER_VALIDATE_URL)) {
                    $oldPath = str_replace(url('storage').'/', '', $oldUrl);
                } else {
                    // 🔹 Aks holda → o‘zini ishlatamiz
                    $oldPath = $oldUrl;
                }

                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    $file = Storage::disk('public')->get($oldPath);
                    $newPath = 'hikvisionImages/' . basename($oldPath);

                    Storage::disk('s3')->put($newPath, $file);
                    Storage::disk('s3')->setVisibility($newPath, 'public'); // agar umumiy bo‘lishi kerak bo‘lsa

                    $att->update(['check_in_image' => $newPath]);

                    $this->info("✅ Attendance {$att->id} ko‘chirildi: {$newPath}");
                } else {
                    $this->warn("⚠️ Fayl topilmadi: {$oldUrl}");
                }
            }
        });
        $this->info('✅ Migration completed!');
    }
}

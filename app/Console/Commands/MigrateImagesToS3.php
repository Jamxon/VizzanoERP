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
                $oldPath = $employee->img;

                if (Storage::disk('public')->exists($oldPath)) {
                    // Faylni o‘qish
                    $file = Storage::disk('public')->get($oldPath);

                    // S3 ga yozish
                    $newPath = "images/" . basename($oldPath);
                    Storage::disk('s3')->put($newPath, $file);

                    // DB ni yangilash
                    $employee->update([
                        'img' => $newPath
                    ]);

                    $this->info("✅ {$employee->id} ko‘chirildi: {$newPath}");
                } else {
                    $this->warn("⚠️ Fayl topilmadi: {$oldPath}");
                }
            }
        });

        $this->info('Profile images migration completed.');


        $this->info('Migrating Attendance check_in images...');
        Attendance::whereNotNull('check_in_image')->chunk(100, function ($records) {
            foreach ($records as $att) {
                $oldPath = $att->check_in_image;
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    $file = Storage::disk('public')->get($oldPath);
                    $newPath = 'hikvisionImages/' . basename($oldPath);
                    Storage::disk('s3')->put($newPath, $file);
                    $att->update(['check_in_image' => $newPath]);
                    $this->info("✅ Attendance {$att->id} ko‘chirildi: {$newPath}");
                }
            }
        });
        $this->info('✅ Migration completed!');
    }
}

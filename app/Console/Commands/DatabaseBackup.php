<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup extends Command
{
    protected $signature = 'database:backup';
    protected $description = 'Har kuni 02:00 da database backup yaratib Telegramga yuboradi va o‘chiradi';

    public function handle()
    {
        $date = now()->format('Y-m-d_H-i-s');
        $fileName = "backup_{$date}.sql";
        $filePath = storage_path("app/{$fileName}");

        $command = sprintf(
            'PGPASSWORD=%s pg_dump -U %s -h %s -d %s > %s',
            env('DB_PASSWORD'),
            env('DB_USERNAME'),
            env('DB_HOST'),
            env('DB_DATABASE'),
            $filePath
        );

        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            $this->error("Database backup yaratishda xatolik yuz berdi! Xatolik kodi: $resultCode");
            return;
        }

        $this->info("Database backup yaratildi: $fileName");

        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        $response = Http::attach('document', fopen($filePath, 'r'), $fileName)
            ->post("https://api.telegram.org/bot{$botToken}/sendDocument", [
                'chat_id' => $chatId,
            ]);

        fclose(fopen($filePath, 'r'));

        if ($response->successful()) {
            $this->info("Backup Telegramga yuborildi.");

            unlink($filePath);
            $this->info("Backup fayli serverdan o‘chirildi.");
        } else {
            $this->error("Backup yuborilmadi: " . $response->body());
        }
    }
}

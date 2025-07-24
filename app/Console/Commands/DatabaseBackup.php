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

        // pg_dump orqali backup olish
        $command = sprintf(
            'PGPASSWORD=%s pg_dump -U %s -h %s -d %s > %s',
            "vizzanopro",    // parol
            "vizzano",       // user
            "176.124.208.61", // host
            "vizzano",       // db
            $filePath
        );

        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            $this->error("Database backup yaratishda xatolik yuz berdi! Xatolik kodi: $resultCode : $command");
            return;
        }

        $this->info("Database backup yaratildi: $fileName");

        // .sql faylni zip qilamiz
        $zipFileName = "{$fileName}.zip";
        $zipPath = storage_path("app/{$zipFileName}");

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filePath, $fileName);
            $zip->close();
        } else {
            $this->error("Zip fayl yaratilmadi.");
            return;
        }

        $this->info("Zip fayl yaratildi: $zipFileName");

        // Telegramga yuboramiz
        $botToken = "7905618693:AAFsNBRPGOA5TFVWr8gORlyH_rtXzCYhLS8";
        $chatId = "-1002476073696";

        $response = Http::attach('document', fopen($zipPath, 'r'), $zipFileName)
            ->post("https://api.telegram.org/bot{$botToken}/sendDocument", [
                'chat_id' => $chatId,
            ]);

        fclose(fopen($zipPath, 'r'));

        if ($response->successful()) {
            $this->info("Backup Telegramga yuborildi.");

            // Tozalash
            unlink($filePath);   // .sql
            unlink($zipPath);    // .zip

            $this->info("Backup fayllar serverdan o‘chirildi.");
        } else {
            $this->error("Backup yuborilmadi: " . $response->body());
        }
    }
}

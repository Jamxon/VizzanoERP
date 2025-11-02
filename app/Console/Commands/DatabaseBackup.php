<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DatabaseBackup extends Command
{
    protected $signature = 'database:backup';
    protected $description = 'Har kuni database backup yaratib Telegramga yuboradi va fayllarni o‘chiradi';

    public function handle()
    {
        ini_set('memory_limit', '4096M');
        $date = now()->format('Y-m-d_H-i-s');
        $fileName = "backup_{$date}.sql";
        $filePath = storage_path("app/{$fileName}");

        // 1️⃣ Backup yaratish
        $command = sprintf(
            'PGPASSWORD=%s pg_dump -U %s -h %s -d %s -F c -f %s',
            "vizzanopro",    // parol
            "vizzano",       // user
            "176.124.208.61", // host
            "vizzano",       // db
            $filePath
        );
        exec($command, $output, $resultCode);
        if ($resultCode !== 0) {
            $this->error("Database backupda xatolik: kod {$resultCode}");
            return;
        }

        $zipFileName = "{$fileName}.zip";
        $zipPath = storage_path("app/{$zipFileName}");

        // 2️⃣ Zip yaratish
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filePath, $fileName);
            $zip->close();
        } else {
            $this->error("Zip fayl yaratilmadi");
            return;
        }

        unlink($filePath); // sqlni o‘chiramiz

        // 3️⃣ Bo‘laklab yuborish
        $this->sendFileInChunks($zipPath, $zipFileName);

        unlink($zipPath); // zipni o‘chiramiz
    }

    private function sendFileInChunks($filePath, $originalName)
    {
        $botToken = "7905618693:AAFsNBRPGOA5TFVWr8gORlyH_rtXzCYhLS8";
        $chatId = "-1002476073696";
        $chunkSize = 45 * 1024 * 1024; // 45 MB

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            $this->error("Faylni ochib bo‘lmadi: $filePath");
            return;
        }

        $part = 1;

        while (!feof($handle)) {

            $chunkData = fread($handle, $chunkSize);
            if (!$chunkData) break;

            $chunkName = "{$originalName}.part{$part}";
            $this->info("📦 Yuborilmoqda: {$chunkName}");

            try {
                $tmpFile = tmpfile();
                fwrite($tmpFile, $chunkData);
                fseek($tmpFile, 0);

                $response = Http::timeout(600) // 10 minutes
                ->attach('document', $tmpFile, $chunkName)
                    ->post("https://api.telegram.org/bot{$botToken}/sendDocument", [
                        'chat_id' => $chatId,
                        'caption' => "Backup bo‘lak #{$part}"
                    ]);

                fclose($tmpFile);

                if (!$response->successful()) {
                    $this->error("❌ Telegram javobi: " . $response->body());
                    sleep(180); // ❗ Katta delay
                    continue;
                }

                $this->info("✅ {$chunkName} yuborildi.");

                if (!feof($handle)) {
                    $this->info("⏳ Telegram ratelimit kutilyapti (90s)...");
                    sleep(90);
                }

            } catch (\Exception $e) {
                $this->error("🔥 Xatolik: " . $e->getMessage());
                sleep(180);
            }

            $part++;
        }

        fclose($handle);
        $this->info("🎉 HAMMA BO‘LAKLAR JO‘NATILDI ✅");

    }


}
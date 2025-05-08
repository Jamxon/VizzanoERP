<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ItemsExport;
use Illuminate\Support\Facades\Storage;

class ProcessExportCompleted implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function handle()
    {
        // Faylni yaratish
        $filePath = storage_path('app/public/materiallar.xlsx');

        // Faylni storagega saqlash
        Excel::store(new ItemsExport, 'public/materiallar.xlsx');

        // Fayl URL'ini olish
        $fileUrl = url('storage/materiallar.xlsx');

        // Fayl tayyor bo'lganidan keyin front-endga bu URLni yuborish
        // Agar kerak bo'lsa, bu jarayonni jobga qo'shish mumkin (masalan, Redis orqali yoki event orqali)
        // Bu yerda faqat fayl URL'ini qaytarib yuborish lozim.
    }
}

<?php

namespace App\Exports;

use App\Models\OrderSubModel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Events\AfterSheet;

class TarificationCategoryExport implements FromCollection, WithHeadings
{
    protected $orderSubModelId;
    protected $mergeRows = []; // Bu yerda kategoriya header qatorlarining indekslarini saqlaymiz

    // Export qilish uchun orderSubModel ID sini qabul qilamiz
    public function __construct($orderSubModelId)
    {
        $this->orderSubModelId = $orderSubModelId;
    }

    public function collection(): Collection
    {
        // OrderSubModelni tarificationCategories va ularning tarificationlarini bilan yuklab olish
        $orderSubModel = OrderSubModel::with(['tarificationCategories.tarifications'])
            ->find($this->orderSubModelId);

        // Agar orderSubModel topilmasa, bo'sh collection qaytaramiz
        if (!$orderSubModel) {
            return collect([]);
        }

        $rows = new Collection();
        $currentRow = 1; // Excel qator raqamlari 1-dan boshlanadi

        // Har bir tarificationCategory bo'yicha:
        foreach ($orderSubModel->tarificationCategories as $category) {
            // 1. Kategoriya nomi – bitta hujayradan iborat qator (keyin hujayralar birlashtiriladi)
            $rows->push([$category->name]);
            $this->mergeRows[] = $currentRow; // bu qatorda birlashtirish kerak
            $currentRow++;

            // 2. Tarification ustun nomlari
            $header = ['code', 'user', 'name', 'razryad', 'typewriter', 'second', 'summa'];
            $rows->push($header);
            $currentRow++;

            // 3. Kategoriya ostidagi tarificationlar
            foreach ($category->tarifications as $tarification) {
                $rows->push([
                    $tarification->code,
                    $tarification->user,
                    $tarification->name,
                    $tarification->razryad,
                    $tarification->typewriter,
                    $tarification->second,
                    $tarification->summa,
                ]);
                $currentRow++;
            }
            // Agar har bir blokdan keyin bo'sh qator qo'shmoqchi bo'lsangiz:
            // $rows->push([]);
            // $currentRow++;
        }

        return $rows;
    }

    // RegisterEvents orqali hujayralarni birlashtirish va stil qo'llash
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Har bir kategoriya header qatorini A–G ustunlari bo'ylab birlashtiramiz
                foreach ($this->mergeRows as $row) {
                    $cellRange = "A{$row}:G{$row}";
                    $sheet->mergeCells($cellRange);
                    // Stil: qalin va markazlashtirilgan
                    $sheet->getStyle($cellRange)->applyFromArray([
                        'font' => [
                            'bold' => true,
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);
                }
            },
        ];
    }

    public function headings(): array
    {
        return ['code', 'user', 'name', 'razryad', 'typewriter', 'second', 'summa'];
    }
}

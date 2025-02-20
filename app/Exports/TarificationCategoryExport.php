<?php

namespace App\Exports;

use App\Models\OrderSubModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class TarificationCategoryExport implements FromCollection, WithEvents
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
        $orderSubModel = OrderSubModel::with(['tarificationCategories.tarifications'])->find($this->orderSubModelId);

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
            $header = ['code', 'employee', 'name', 'razryad', 'typewriter', 'second', 'summa'];
            $rows->push($header);
            $currentRow++;

            // 3. Kategoriya ostidagi tarificationlar
            foreach ($category->tarifications as $tarification) {
                $rows->push([
                    $tarification->code,
                    $tarification->employee->name,
                    $tarification->name,
                    $tarification->razryad->name,
                    $tarification->typewriter->name,
                    $tarification->second,
                    $tarification->summa,
                ]);
                $currentRow++;
            }
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Har bir kategoriya header qatorini A–G ustunlari bo'ylab birlashtiramiz va stil beramiz
                foreach ($this->mergeRows as $row) {
                    $cellRange = "A{$row}:G{$row}";
                    $sheet->mergeCells($cellRange);
                    $sheet->getStyle($cellRange)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);
                }

                // Ustun kengliklarini sozlash:
                $sheet->getColumnDimension('A')->setWidth(15); // code
                $sheet->getColumnDimension('B')->setWidth(20); // employee
                $sheet->getColumnDimension('C')->setWidth(30); // name
                $sheet->getColumnDimension('D')->setWidth(15); // razryad
                $sheet->getColumnDimension('E')->setWidth(20); // typewriter
                $sheet->getColumnDimension('F')->setWidth(10); // second
                $sheet->getColumnDimension('G')->setWidth(15); // summa

                // Agar kerak bo'lsa, kategoriya headerlari uchun maxsus kenglikni ham belgilash mumkin
                foreach ($this->mergeRows as $row) {
                    // Masalan, birinchi hujayrani kengroq qilish:
                    $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                        'font' => ['size' => 14, 'bold' => true],
                    ]);
                }
            },
        ];
    }

    public function headings(): array
    {
        return [];
    }
}

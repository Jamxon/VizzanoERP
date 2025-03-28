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
    protected $mergeRows = []; // Kategoriya header qatorlarining indekslarini saqlaymiz

    // Export qilish uchun orderSubModel ID sini qabul qilamiz
    public function __construct($orderSubModelId)
    {
        $this->orderSubModelId = $orderSubModelId;
    }

    public function collection(): Collection
    {
        // OrderSubModelni tarificationCategories va ularning tarificationlarini bilan yuklab olish
        $orderSubModel = OrderSubModel::with(['tarificationCategories.tarifications'])->find($this->orderSubModelId);

        if (!$orderSubModel) {
            return collect([]);
        }

        $rows = new Collection();
        $currentRow = 1; // Excel qator raqamlari 1-dan boshlanadi

        foreach ($orderSubModel->tarificationCategories as $category) {
            // 1. Kategoriya nomi (merged qator: A-G)
            $rows->push([$category->name]);
            $this->mergeRows[] = $currentRow;
            $currentRow++;

            // 2. Tarification ustun nomlari (yangi header: code, employee_id, employee, name, razryad, typewriter, second, summa)
            $header = ['code', 'employee_id', 'employee', 'name', 'razryad', 'typewriter', 'second', 'summa'];
            $rows->push($header);
            $currentRow++;

            // 3. Kategoriya ostidagi tarificationlar
            foreach ($category->tarifications as $tarification) {
                $rows->push([
                    $tarification->code,
                    $tarification->employee->id ?? null,   // Employee id
                    $tarification->employee->name, // Employee nomi
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

                // Har bir kategoriya header qatorini A–H ustunlari bo'ylab birlashtiramiz (8 ta ustun)
                foreach ($this->mergeRows as $row) {
                    $cellRange = "A{$row}:H{$row}";
                    $sheet->mergeCells($cellRange);
                    $sheet->getStyle($cellRange)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);
                }

                // Ustun kengliklarini sozlash
                $sheet->getColumnDimension('A')->setWidth(15); // code
                $sheet->getColumnDimension('B')->setWidth(12); // employee_id
                $sheet->getColumnDimension('C')->setWidth(20); // employee
                $sheet->getColumnDimension('D')->setWidth(30); // name
                $sheet->getColumnDimension('E')->setWidth(15); // razryad
                $sheet->getColumnDimension('F')->setWidth(20); // typewriter
                $sheet->getColumnDimension('G')->setWidth(10); // second
                $sheet->getColumnDimension('H')->setWidth(15); // summa
            },
        ];
    }

    public function headings(): array
    {
        return [];
    }
}

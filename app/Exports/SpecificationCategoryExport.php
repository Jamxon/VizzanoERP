<?php

namespace App\Exports;

use App\Models\OrderSubModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class SpecificationCategoryExport implements FromCollection, WithEvents
{
    protected $orderSubModelId;
    protected $mergeRows = []; // Kategoriya header qatorlarining indekslarini saqlaydi

    // Export qilish uchun orderSubModel ID sini qabul qilamiz
    public function __construct($orderSubModelId)
    {
        $this->orderSubModelId = $orderSubModelId;
    }

    public function collection(): Collection
    {
        // OrderSubModelni specificationCategories va ularning specifications bilan yuklab olish
        $orderSubModel = OrderSubModel::with(['specificationCategories.specifications'])->find($this->orderSubModelId);

        if (!$orderSubModel) {
            return collect([]);
        }

        $rows = new Collection();
        $currentRow = 1; // Excel qatorlari 1-dan boshlanadi

        foreach ($orderSubModel->specificationCategories as $category) {
            // 1. Kategoriya nomi (merged qator: A-D)
            $rows->push([$category->name]);
            $this->mergeRows[] = $currentRow;
            $currentRow++;

            // 2. Specifications ustun nomlari (header: code, name, quantity, comment)
            $header = ['code', 'name', 'quantity', 'comment'];
            $rows->push($header);
            $currentRow++;

            // 3. Kategoriya ostidagi specifications ma'lumotlari
            foreach ($category->specifications as $spec) {
                $rows->push([
                    $spec->code,
                    $spec->name,
                    $spec->quantity,
                    $spec->comment,
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

                // Har bir kategoriya header qatorini A–D ustunlari bo'ylab birlashtiramiz (4 ta ustun)
                foreach ($this->mergeRows as $row) {
                    $cellRange = "A{$row}:D{$row}";
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
                $sheet->getColumnDimension('B')->setWidth(30); // name
                $sheet->getColumnDimension('C')->setWidth(15); // quantity
                $sheet->getColumnDimension('D')->setWidth(30); // comment
            },
        ];
    }
}

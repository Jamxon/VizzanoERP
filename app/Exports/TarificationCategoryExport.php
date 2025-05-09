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
    protected $mergeRows = [];

    public function __construct($orderSubModelId)
    {
        $this->orderSubModelId = $orderSubModelId;
    }

    public function collection(): Collection
    {
        $orderSubModel = OrderSubModel::with(['tarificationCategories.tarifications.razryad'])->find($this->orderSubModelId);

        if (!$orderSubModel) {
            return collect([]);
        }

        $rows = new Collection();
        $currentRow = 1;

        foreach ($orderSubModel->tarificationCategories as $category) {
            // Kategoriya nomi C ustunida bo'ladi (import formatiga mos)
            $rows->push([null, null, $category->name]);
            $this->mergeRows[] = $currentRow;
            $currentRow++;

            foreach ($category->tarifications as $tarification) {
                $rows->push([
                    $tarification->second ?? null,
                    null,
                    $tarification->name ?? null,
                    optional($tarification->razryad)->name ?? null,
                    null,
                    null,
                    $tarification->summa ?? null,
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

                // Ustun kengliklari (importdagi ustunlar bilan bir xil)
                $sheet->getColumnDimension('A')->setWidth(10); // second
                $sheet->getColumnDimension('B')->setWidth(5);  // bo'sh
                $sheet->getColumnDimension('C')->setWidth(40); // name/category
                $sheet->getColumnDimension('D')->setWidth(12); // razryad
                $sheet->getColumnDimension('E')->setWidth(5);  // bo'sh
                $sheet->getColumnDimension('F')->setWidth(5);  // bo'sh
                $sheet->getColumnDimension('G')->setWidth(15); // summa
            },
        ];
    }

    public function headings(): array
    {
        return [];
    }
}

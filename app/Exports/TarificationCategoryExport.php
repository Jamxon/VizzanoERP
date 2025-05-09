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
            // 1. Category name (merged across A–G)
            $rows->push([$category->name]);
            $this->mergeRows[] = $currentRow;
            $currentRow++;

            // 2. Header row for tarification data
            $rows->push(['second', null, 'name', 'razryad', null, null, 'summa']);
            $currentRow++;

            // 3. Tarification data rows
            foreach ($category->tarifications as $tarification) {
                // Agar 'second' qiymati mavjud bo'lsa, formula qo'shish
                if ($tarification->second) {
                    // Excelda formula: second * 0.6
                    $formula = "=B{$currentRow}*0.6";
                } else {
                    // Agar qiymat yo'q bo'lsa, 0 ni ko'rsatish
                    $formula = "=0";
                }

                $rows->push([
                    $formula,  // Formula qo'shish
                    $tarification->second ?? 0,  // Tarification 'second' qiymati
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

                // Merge category name cells
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

                // Ustun kengliklari
                $sheet->getColumnDimension('A')->setWidth(10); // second
                $sheet->getColumnDimension('B')->setWidth(5);  // blank
                $sheet->getColumnDimension('C')->setWidth(40); // name/category
                $sheet->getColumnDimension('D')->setWidth(12); // razryad
                $sheet->getColumnDimension('E')->setWidth(5);  // blank
                $sheet->getColumnDimension('F')->setWidth(5);  // blank
                $sheet->getColumnDimension('G')->setWidth(15); // summa
            },
        ];
    }
}

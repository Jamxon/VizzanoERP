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
    protected $formulaRows = [];

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
            
            // 3. Tarification data rows
            foreach ($category->tarifications as $tarification) {
                $bcolumn = $tarification->second / 0.6;
                // Store actual value in A column initially, we'll replace with formula later
                $rows->push([
                    $tarification->second, // Temporarily put the actual value
                    $bcolumn,
                    $tarification->name ?? null,
                    optional($tarification->razryad)->name ?? null,
                    null,
                    null,
                    $tarification->summa ?? null,
                ]);
                // Store the current row for later formula replacement
                $this->formulaRows[] = ['row' => $currentRow, 'value' => $tarification->second];
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

                // Apply formulas after sheet is created
                foreach ($this->formulaRows as $formulaData) {
                    $row = $formulaData['row'];
                    // Set formula in A column that references B column of the same row
                    $sheet->setCellValue("A{$row}", "=B{$row}*0.6");
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
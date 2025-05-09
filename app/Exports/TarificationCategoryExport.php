<?php

namespace App\Exports;

use App\Models\OrderSubModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class TarificationCategoryExport implements FromCollection, WithEvents
{
    protected $orderSubModelId;
    protected $mergeRows = [];
    protected $formulaCells = [];
    protected $debugInfo = [];

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

        // Debug row to verify where we start counting rows
        $rows->push(['DEBUG: START', 'Row 1']);
        $currentRow++;

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
                // Calculate and validate B column value
                $bValue = $tarification->second > 0 ? $tarification->second / 0.6 : 0;

                // Save debug info for this row
                $this->debugInfo[] = [
                    'row' => $currentRow,
                    'second' => $tarification->second,
                    'bValue' => $bValue
                ];

                // Store the actual calculated value temporarily in A column
                $rows->push([
                    $tarification->second, // Real value for second
                    $bValue,               // B column value
                    $tarification->name ?? null,
                    optional($tarification->razryad)->name ?? null,
                    null,
                    null,
                    $tarification->summa ?? null,
                ]);

                // Save the cell address for later formula application
                $this->formulaCells[] = [
                    'cell' => 'A' . $currentRow,
                    'bValue' => $bValue,
                    'second' => $tarification->second
                ];

                $currentRow++;
            }
        }

        // Add debug row at the end
        $rows->push(['DEBUG: END', 'Row ' . $currentRow]);

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

                // Add debug information
                $debugRow = $sheet->getHighestRow() + 2;
                $sheet->setCellValue("A{$debugRow}", "DEBUG INFO");
                $sheet->getStyle("A{$debugRow}")->getFont()->setBold(true);
                $debugRow++;

                // Write all debug info
                foreach ($this->debugInfo as $info) {
                    $sheet->setCellValue("A{$debugRow}", "Row: " . $info['row']);
                    $sheet->setCellValue("B{$debugRow}", "Second: " . $info['second']);
                    $sheet->setCellValue("C{$debugRow}", "B Value: " . $info['bValue']);
                    $debugRow++;
                }

                // Set formulas for A column cells - explicitly ensure they are formulas
                foreach ($this->formulaCells as $info) {
                    $cell = $info['cell'];
                    $rowNumber = substr($cell, 1);
                    $bValue = $info['bValue'];
                    $second = $info['second'];

                    if ($bValue > 0) {
                        // Set as formula if B value exists
                        $formula = "=B{$rowNumber}*0.6";
                        $sheet->setCellValueExplicit(
                            $cell,
                            $formula,
                            DataType::TYPE_FORMULA
                        );
                    } else {
                        // Ensure we at least keep the original value
                        $sheet->setCellValue($cell, $second);
                    }
                }

                // Ustun kengliklari
                $sheet->getColumnDimension('A')->setWidth(15); // second
                $sheet->getColumnDimension('B')->setWidth(10); // B column value
                $sheet->getColumnDimension('C')->setWidth(40); // name/category
                $sheet->getColumnDimension('D')->setWidth(12); // razryad
                $sheet->getColumnDimension('E')->setWidth(5);  // blank
                $sheet->getColumnDimension('F')->setWidth(5);  // blank
                $sheet->getColumnDimension('G')->setWidth(15); // summa
            },
        ];
    }
}
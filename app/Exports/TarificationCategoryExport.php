<?php

namespace App\Exports;

use App\Models\OrderSubModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TarificationCategoryExport implements FromCollection, WithEvents, WithStrictNullComparison
{
    protected $orderSubModelId;
    protected $mergeRows = [];
    protected $formulaData = [];

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
            $rows->push(['second', 'B column', 'name', 'razryad', null, null, 'summa']);
            $currentRow++;

            // 3. Tarification data rows
            foreach ($category->tarifications as $tarification) {
                // Use a placeholder for column A, will be replaced with formula
                $second = $tarification->second ?? 0;
                $bValue = $second > 0 ? $second / 0.6 : 0;

                // Store row with actual values - A column will get formula later
                $rows->push([
                    "FORMULA_PLACEHOLDER_{$currentRow}", // Formula placeholder
                    $bValue, // B column value
                    $tarification->name ?? null,
                    optional($tarification->razryad)->name ?? null,
                    null,
                    null,
                    $tarification->summa ?? null,
                ]);

                // Save formula data for later
                $this->formulaData[] = [
                    'row' => $currentRow,
                    'bValue' => $bValue,
                    'second' => $second
                ];

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
                $spreadsheet = $sheet->getParent();

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

                // Explicitly apply formulas to cells (replacing placeholders)
                foreach ($this->formulaData as $data) {
                    $row = $data['row'];
                    $bValue = $data['bValue'];
                    $cell = "A{$row}";

                    // Replace placeholder with formula
                    $formula = "=B{$row}*0.6";

                    // First ensure B column has the correct value
                    $sheet->getCell("B{$row}")->setValue($bValue);

                    // Then set the formula
                    $sheet->getCell($cell)->setValue($formula);

                    // Format the cell to display decimal numbers
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                    $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                }

                // Force recalculation
                $spreadsheet->getCalculationEngine()->disableCalculationCache();
                $spreadsheet->getCalculationEngine()->calculateFormulas();

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(15); // second with formula
                $sheet->getColumnDimension('B')->setWidth(10); // B column
                $sheet->getColumnDimension('C')->setWidth(40); // name/category
                $sheet->getColumnDimension('D')->setWidth(12); // razryad
                $sheet->getColumnDimension('E')->setWidth(5);  // blank
                $sheet->getColumnDimension('F')->setWidth(5);  // blank
                $sheet->getColumnDimension('G')->setWidth(15); // summa
            },
        ];
    }
}
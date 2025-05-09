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
        $orderSubModel = OrderSubModel::with(['tarificationCategories.tarifications.razryad', 'tarificationCategories.tarifications.typewriter'])->find($this->orderSubModelId);

        if (!$orderSubModel) {
            return collect([]);
        }

        $rows = new Collection();
        $currentRow = 1;

        foreach ($orderSubModel->tarificationCategories ?? [] as $category) {
            $rows->push([$category->name ?? '']);
            $this->mergeRows[] = $currentRow;
            $currentRow++;

            $header = ['code', 'name', 'razryad', 'typewriter', 'second', 'summa'];
            $rows->push($header);
            $currentRow++;

            foreach ($category->tarifications ?? [] as $tarification) {
                $rows->push([
                    $tarification->code ?? '',
                    $tarification->name ?? '',
                    optional($tarification->razryad)->name ?? '',
                    optional($tarification->typewriter)->name ?? '',
                    $tarification->second ?? '',
                    $tarification->summa ?? '',
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
                    $cellRange = "A{$row}:H{$row}";
                    $sheet->mergeCells($cellRange);
                    $sheet->getStyle($cellRange)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);
                }

                $sheet->getColumnDimension('A')->setWidth(15);
                $sheet->getColumnDimension('B')->setWidth(12);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getColumnDimension('D')->setWidth(30);
                $sheet->getColumnDimension('E')->setWidth(15);
                $sheet->getColumnDimension('F')->setWidth(20);
                $sheet->getColumnDimension('G')->setWidth(10);
                $sheet->getColumnDimension('H')->setWidth(15);
            },
        ];
    }

    public function headings(): array
    {
        return [];
    }
}

<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class BoxStickerExport implements FromView, WithStyles,  WithDrawings
{
    protected array $stickers;
    protected string $imagePath;
    protected string $submodel;
    protected string $model;

    public function __construct(array $stickers, string $imagePath, string $submodel, string $model)
    {
        $this->stickers = $stickers;
        $this->imagePath = $imagePath;
        $this->submodel = $submodel;
        $this->model = $model;
    }

    public function view(): View
    {
        return view('exports.box_sticker', [
            'stickers' => $this->stickers,
            'imagePath' => $this->imagePath,
            'submodel' => $this->submodel,
            'model' => $this->model,
        ]);
    }


    public function styles(Worksheet $sheet)
    {
        $sheet->getDefaultRowDimension()->setRowHeight(25);
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(9);
        $sheet->getColumnDimension('C')->setWidth(9);
        $sheet->getColumnDimension('D')->setWidth(9);
        $sheet->getColumnDimension('E')->setWidth(9);
        $sheet->getColumnDimension('F')->setWidth(9);
        $sheet->getColumnDimension('G')->setWidth(9);
    }

    public function drawings(): array
    {
        $drawings = [];
        $startRow = 1;

        foreach ($this->stickers as $index => $sticker) {
            if (!file_exists($this->imagePath)) continue;

            $drawing = new Drawing();
            $drawing->setName("Logo $index");
            $drawing->setDescription("Logo for sticker $index");
            $drawing->setPath($this->imagePath);
            $drawing->setHeight(70);
            $drawing->setCoordinates('A' . $startRow);
            $drawing->setOffsetY(5);
            $drawings[] = $drawing;

            // Har bir quti orasida taxminan 15 qator bo‘shliq bo‘lsin
            $startRow += 15;
        }

        return $drawings;
    }
}

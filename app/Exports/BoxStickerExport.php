<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BoxStickerExport implements FromView, WithStyles, WithDrawings
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
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);
    }

    public function drawings(): array
    {
        if (!file_exists($this->imagePath)) return [];

        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Contragent Logo');
        $drawing->setPath($this->imagePath);
        $drawing->setHeight(70);
        $drawing->setCoordinates('A1');

        return [$drawing];
    }
}

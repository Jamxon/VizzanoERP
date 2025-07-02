<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class BoxStickerExport implements FromView, WithTitle, WithStyles//, WithDrawings
{
    protected $stickers;
    protected $imagePath;
    protected $submodel;

    public function __construct($stickers, $imagePath, $submodel)
    {
        $this->stickers = $stickers;
        $this->imagePath = $imagePath;
        $this->submodel = $submodel;
    }

//    public function drawings()
//    {
//        $drawing = new Drawing();
//        $drawing->setName('Logo');
//        $drawing->setDescription('Contragent logotipi');
//        $drawing->setPath($this->imagePath); // absolute path bo'lishi kerak
//        $drawing->setHeight(90);
//        $drawing->setCoordinates('A1');
//        $drawing->setOffsetX(10);
//        $drawing->setOffsetY(5);
//
//        return [$drawing];
//    }

    public function view(): \Illuminate\View\View
    {
        return view('exports.box_sticker', [
            'stickers' => $this->stickers,
            'imagePath' => $this->imagePath,
            'submodel' => $this->submodel,
        ]);
    }

    public function title(): string
    {
        return 'Box Stickers';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        $sheet->getStyle('A:G')->getFont()->setSize(12);
    }
}


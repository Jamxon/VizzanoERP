<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BoxStickerExport implements FromView, WithTitle, WithStyles
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


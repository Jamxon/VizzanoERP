<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class BoxStickerExport implements FromView, WithTitle, WithStyles, WithDrawings
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

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->stickers as $index => $sticker) {
            $sheets[] = new class($sticker, $this->imagePath, $this->submodel, $index) implements FromView, WithTitle, WithDrawings {
                protected $sticker, $imagePath, $submodel, $index;

                public function __construct($sticker, $imagePath, $submodel, $index)
                {
                    $this->sticker = $sticker;
                    $this->imagePath = $imagePath;
                    $this->submodel = $submodel;
                    $this->index = $index;
                }

                public function title(): string
                {
                    return 'Sticker ' . ($this->index + 1);
                }

                public function view(): \Illuminate\View\View
                {
                    return view('exports.box_sticker_single', [
                        'sticker' => $this->sticker,
                        'imagePath' => $this->imagePath,
                        'submodel' => $this->submodel,
                    ]);
                }

                public function drawings()
                {
                    $drawing = new Drawing();
                    $drawing->setName('Logo');
                    $drawing->setDescription('Logo for sticker');
                    $drawing->setPath($this->imagePath);
                    $drawing->setHeight(90);
                    $drawing->setCoordinates('A1'); // har bir sheetda A1 dan boshlanadi
                    return [$drawing];
                }
            };
        }

        return $sheets;
    }
    public function drawings()
    {
        $drawings = [];
        foreach (range(0, count($this->stickers) - 1) as $i) {
            $drawing = new Drawing();
            $drawing->setName("Logo-$i");
            $drawing->setPath($this->imagePath);
            $drawing->setHeight(90);
            $drawing->setCoordinates('A' . ($i * 20 + 1)); // masalan A1, A21, A41
            $drawings[] = $drawing;
        }
        return $drawings;
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


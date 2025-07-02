<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BoxStickerExport implements WithMultipleSheets
{
    protected array $stickers;
    protected string $imagePath;
    protected string $submodel;

    public function __construct(array $stickers, string $imagePath, string $submodel)
    {
        $this->stickers = $stickers;
        $this->imagePath = $imagePath;
        $this->submodel = $submodel;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->stickers as $index => $sticker) {
            $sheets[] = new class($sticker, $this->imagePath, $this->submodel, $index + 1)
                implements \Maatwebsite\Excel\Concerns\FromView,
                \Maatwebsite\Excel\Concerns\WithTitle,
                \Maatwebsite\Excel\Concerns\WithStyles,
                \Maatwebsite\Excel\Concerns\WithDrawings {

                protected $sticker;
                protected $imagePath;
                protected $submodel;
                protected $index;

                public function __construct($sticker, $imagePath, $submodel, $index)
                {
                    $this->sticker = $sticker;
                    $this->imagePath = $imagePath;
                    $this->submodel = $submodel;
                    $this->index = $index;
                }



                public function view(): View
                {
                    return view('exports.box_sticker', [
                        'sticker' => $this->sticker,
                        'imagePath' => $this->imagePath,
                        'submodel' => $this->submodel,
                        'index' => $this->index,
                    ]);
                }

                public function title(): string
                {
                    return 'Quti ' . $this->index;
                }

                public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
                {
                    $sheet->getDefaultRowDimension()->setRowHeight(20);
//                    $sheet->getStyle('A:G')->getFont()->setSize(12);

                    // 👉 A ustunini kichikroq qilish:
                    $sheet->getColumnDimension('A')->setWidth(12); // yoki 10, 12 — qanday bo'lishini istasang

                    // Optional: boshqa ustunlar ham sozlansa yaxshi bo'ladi
                    $sheet->getColumnDimension('B')->setWidth(9);
                    $sheet->getColumnDimension('C')->setWidth(9);
                    $sheet->getColumnDimension('D')->setWidth(9);
                    $sheet->getColumnDimension('E')->setWidth(9);
                    $sheet->getColumnDimension('F')->setWidth(9);
                    $sheet->getColumnDimension('G')->setWidth(9);
                }

                public function drawings(): array
                {
                    return [];
                }
            };
        }

        return $sheets;
    }
}

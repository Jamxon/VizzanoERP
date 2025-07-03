<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class BoxStickerExport
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

    public function store(string $path): void
    {
        $pdf = Pdf::loadView('exports.box_sticker_pdf', [
            'stickers' => $this->stickers,
            'imagePath' => $this->imagePath,
            'submodel' => $this->submodel,
            'model' => $this->model,
        ])->setPaper('a4');

        Storage::disk('public')->put($path, $pdf->output());
    }
}

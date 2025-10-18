<?php

namespace App\Exports;

use App\Models\CashboxTransaction;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Carbon\Carbon;

class TransactionsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    protected $request;
    protected $looping = 0;
    protected $totalAmount = 0;
    protected $currencyName = '';
    protected $periodText = '';

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = CashboxTransaction::with(['currency', 'via'])
            ->where('branch_id', auth()->user()->employee->branch_id);

        if ($this->request->filled('cashbox_id')) {
            $query->where('cashbox_id', $this->request->cashbox_id);
        }

        if ($this->request->filled('type')) {
            $query->where('type', $this->request->type);
        }

        if ($this->request->filled('currency_id')) {
            $query->where('currency_id', $this->request->currency_id);
        }

        if ($this->request->filled('date')) {
            $query->whereDate('date', $this->request->date);
        }

        if ($this->request->filled('start_date') && $this->request->filled('end_date')) {
            $query->whereBetween('date', [$this->request->start_date, $this->request->end_date]);
        }

        $transactions = $query->orderBy('date', 'desc')->get();

        // Summani hisoblash (float konvert bilan)
        $this->totalAmount = $transactions->sum(function ($t) {
            return floatval($t->amount);
        });

        $this->currencyName = optional($transactions->first()?->currency)->name ?? '';

        if ($this->request->filled('start_date') && $this->request->filled('end_date')) {
            $this->periodText = "Davr: {$this->request->start_date} dan {$this->request->end_date} gacha";
        } elseif ($this->request->filled('date')) {
            $this->periodText = "Sana: {$this->request->date}";
        } else {
            $this->periodText = "Barcha davr uchun";
        }

        return $transactions;
    }

    public function map($tx): array
    {
        $this->looping++;

        return [
            $this->looping,
            $tx->date ? Carbon::parse($tx->date)->format('Y-m-d') : '',
            number_format(floatval($tx->amount), 2) . ' ' . ($tx->currency?->name ?? '-'),
            $tx->purpose ?? '',
            $tx->comment ?? '',
            $tx->via?->name ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            ["Kassa tranzaksiyalari ro'yxati"],
            [$this->periodText],
            ["Umumiy miqdor: " . number_format($this->totalAmount, 2) . ' ' . $this->currencyName],
            [],
            ['No', 'Sana', 'Miqdor', 'Mahsulot yoki shaxs ismi', 'Ochiqlama', 'Chiqim qiluvchi'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Har bir title qatori uchun ustunlarni birlashtirish (A1:F1, A2:F2, A3:F3)
                foreach ([1, 2, 3] as $row) {
                    $event->sheet->mergeCells("A{$row}:F{$row}");
                    $event->sheet->getStyle("A{$row}")
                        ->getAlignment()
                        ->setHorizontal('center')
                        ->setVertical('center');
                    $event->sheet->getStyle("A{$row}")
                        ->getFont()
                        ->setBold(true)
                        ->setSize(12);
                }

                // Jadval ustunlari (5-qator) bold qilish
                $event->sheet->getStyle('A5:F5')->getFont()->setBold(true);
            },
        ];
    }
}
<?php

namespace App\Exports;

use App\Models\CashboxTransaction;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class TransactionsExport implements FromCollection, WithMapping, WithEvents, ShouldAutoSize, WithCustomStartCell, WithHeadings
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

        // Umumiy summa
        $this->totalAmount = $transactions->sum(fn($t) => floatval($t->amount));
        $this->currencyName = optional($transactions->first()?->currency)->name ?? '-';

        // Davr matni
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

    public function startCell(): string
    {
        // Jadval 5-qator (No, Sana...) dan boshlansin
        return 'A5';
    }

    public function headings(): array
    {
        return [
            ['No', 'Sana', 'Miqdor', 'Mahsulot yoki shaxs ismi', 'Ochiqlama', 'Chiqim qiluvchi'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                // Yuqoriga 3 ta sarlavha yozamiz
                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:F1');
                $sheet->setCellValue('A1', "Kassa tranzaksiyalari ro'yxati");

                $sheet->mergeCells('A2:F2');
                $sheet->setCellValue('A2', $this->periodText);

                $sheet->mergeCells('A3:F3');
                $sheet->setCellValue('A3', "Umumiy miqdor: " . number_format($this->totalAmount, 2) . ' ' . $this->currencyName);

                // Markazda va bold qilib
                foreach ([1, 2, 3] as $row) {
                    $sheet->getStyle("A{$row}")
                        ->getAlignment()->setHorizontal('center');
                    $sheet->getStyle("A{$row}")
                        ->getFont()->setBold(true)->setSize(12);
                }

                // Ustun sarlavhalarni bold qilish
                $sheet->getStyle('A5:F5')->getFont()->setBold(true);
            },
        ];
    }
}
<?php

namespace App\Exports;

use App\Models\CashboxTransaction;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class TransactionsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
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

        // Umumiy yig‘indi va valyuta nomini olish
        $this->totalAmount = $transactions->sum('amount');
        $this->currencyName = optional($transactions->first()?->currency)->name ?? '';
        
        // Vaqt oralig‘ini matnga aylantirish
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
            number_format($tx->amount, 2) . ' ' . ($tx->currency?->name ?? '-'),
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
            [], // bo‘sh qatordan keyin sarlavha
            ['No', 'Sana', 'Miqdor', 'Mahsulot yoki shaxs ismi', 'Ochiqlama', 'Chiqim qiluvchi'],
        ];
    }
}
<?php

namespace App\Exports;

use App\Models\CashboxTransaction;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class TransactionsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    protected $looping;

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

        return $query->orderBy('date', 'desc')->get();
    }

    public function map($tx): array
    {
        $this->looping++;

        return [
            $this->looping,
            $tx->date ? Carbon::parse($tx->date)->format('Y-m-d') : '',
            ($tx->amount ?? 0) . ' ' . ($tx->currency?->name ?? '-'),
            $tx->purpose ?? '',
            $tx->comment ?? '',
            $tx->via?->name ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            'No',
            'Sana',
            'Miqdor',
            'Mahsulot yoki shaxs ismi',
            'Ochiqlama',
            'Chiqim qiluvchi',
        ];
    }
}

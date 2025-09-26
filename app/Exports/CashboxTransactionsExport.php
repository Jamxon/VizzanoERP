<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromView;

class CashboxTransactionsExport implements FromView
{
    protected $branchId, $startDate, $endDate, $type;

    public function __construct($branchId, $startDate, $endDate, $type = null)
    {
        $this->branchId  = $branchId;
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
        $this->type      = $type;
    }

    public function view(): View
    {
        $query = DB::table('cashbox_transactions')
            ->select('date', 'purpose', 'amount', 'comment')
            ->where('branch_id', $this->branchId)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->orderBy('purpose')
            ->orderBy('date');

        if ($this->type) {
            $query->where('type', $this->type);
        }

        $transactions = $query->get()->groupBy('purpose');

        return view('exports.cashbox_transactions', [
            'transactions' => $transactions
        ]);
    }
}
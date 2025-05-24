<?php

namespace App\Http\Controllers;

use App\Models\Cashbox;
use App\Models\CashboxBalance;
use App\Models\CashboxTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CasherController extends Controller
{
    public function storeIncome(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'cashbox_id' => 'required|exists:cashboxes,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'source' => 'nullable|string|max:255',
            'via' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'date' => 'nullable|date',
        ]);

        try {
            $data['type'] = 'income';
            $data['date'] = $data['date'] ?? now()->toDateString();
            $data['branch_id'] = auth()->user()->employee->branch_id;

            DB::transaction(function () use ($data) {
                CashboxTransaction::create($data);

                $balance = CashboxBalance::firstOrCreate(
                    [
                        'cashbox_id' => $data['cashbox_id'],
                        'currency_id' => $data['currency_id'],
                    ],
                    [
                        'amount' => 0
                    ]
                );

                $balance->increment('amount', $data['amount']);
            });

        }
        catch (\Exception $e) {
            return response()->json([
                'message' => '❌ Kirim muvaffaqiyatsiz.',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json(['message' => '✅ Kirim muvaffaqiyatli qo‘shildi.']);
    }

    public function storeExpense(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'cashbox_id' => 'required|exists:cashboxes,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'destination' => 'nullable|string|max:255',
            'via' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000',
            'date' => 'nullable|date',
        ]);

        $data['type'] = 'expense';
        $data['date'] = $data['date'] ?? now()->toDateString();
        $data['branch_id'] = auth()->user()->employee->branch_id ?? null;

        try {
            // 1. Balance tekshiruv
            $balance = CashboxBalance::where('cashbox_id', $data['cashbox_id'])
                ->where('currency_id', $data['currency_id'])
                ->value('amount');

            if ($balance === null || $balance < $data['amount']) {
                return response()->json([
                    'message' => '❌ Kassada yetarli mablag‘ mavjud emas.'
                ], 422);
            }

            // 2. Saqlash
            DB::transaction(function () use ($data) {
                CashboxTransaction::create($data);

                CashboxBalance::where('cashbox_id', $data['cashbox_id'])
                    ->where('currency_id', $data['currency_id'])
                    ->decrement('amount', $data['amount']);
            });

            return response()->json(['message' => '✅ Chiqim muvaffaqiyatli yozildi.']);

        } catch (\Exception $e) {
            \Log::error("Chiqim saqlashda xatolik: " . $e->getMessage(), ['data' => $data]);

            return response()->json([
                'message' => '❌ Xatolik yuz berdi. Iltimos, keyinroq urinib ko‘ring.'
            ], 500);
        }
    }

    public function getBalances(): \Illuminate\Http\JsonResponse
    {
        $cashboxes = Cashbox::with(['balances.currency'])
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->get();

        return response()->json([
            'cashboxes' => $cashboxes->map(function ($cashbox) {
                return [
                    'id' => $cashbox->id,
                    'name' => $cashbox->name,
                    'balances' => $cashbox->balances->map(function ($balance) {
                        return [
                            'currency' => $balance->currency->code,
                            'amount' => number_format($balance->amount, 2, '.', ' ')
                        ];
                    }),
                    'transactions' => $cashbox->transactions->map(function ($transaction) {
                        return [
                            'type' => $transaction->type,
                            'amount' => number_format($transaction->amount, 2, '.', ' '),
                            'currency' => $transaction->currency->code,
                            'date' => $transaction->date,
                            'source' => $transaction->source,
                            'destination' => $transaction->destination,
                            'via' => $transaction->via,
                            'purpose' => $transaction->purpose,
                            'comment' => $transaction->comment,
                        ];
                    })
                ];
            })
        ]);
    }

    public function getTransactions(Request $request): \Illuminate\Http\JsonResponse
    {
        $transactions = CashboxTransaction::with('currency')
            ->where('cashbox_id', $request->cashbox_id)
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'transactions' => $transactions->map(function ($tx) {
                return [
                    'type' => $tx->type,
                    'amount' => number_format($tx->amount, 2, '.', ' '),
                    'currency' => $tx->currency->code,
                    'date' => $tx->date,
                    'source' => $tx->source,
                    'destination' => $tx->destination,
                    'via' => $tx->via,
                    'purpose' => $tx->purpose,
                    'comment' => $tx->comment,
                ];
            })
        ]);
    }

    public function transferBetweenCashboxes(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'from_cashbox_id' => 'required|exists:cashboxes,id',
            'to_cashbox_id' => 'required|exists:cashboxes,id|different:from_cashbox_id',
            'from_currency_id' => 'required|exists:currencies,id',
            'to_currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'date' => 'nullable|date',
            'comment' => 'nullable|string|max:1000',
        ]);

        $data['date'] = $data['date'] ?? now()->toDateString();
        $targetAmount = round($data['amount'] * $data['exchange_rate'], 2);

        // Balance tekshirish
        $balance = CashboxBalance::where('cashbox_id', $data['from_cashbox_id'])
            ->where('currency_id', $data['from_currency_id'])
            ->value('amount');

        if ($balance === null || $balance < $data['amount']) {
            return response()->json([
                'message' => '❌ Jo‘natilayotgan kassada yetarli mablag‘ yo‘q.'
            ], 422);
        }

        DB::transaction(function () use ($data, $targetAmount) {
            // 1. Chiqim yoziladi
            CashboxTransaction::create([
                'cashbox_id' => $data['from_cashbox_id'],
                'type' => 'expense',
                'currency_id' => $data['from_currency_id'],
                'amount' => $data['amount'],
                'comment' => $data['comment'],
                'target_cashbox_id' => $data['to_cashbox_id'],
                'exchange_rate' => $data['exchange_rate'],
                'target_amount' => $targetAmount,
                'date' => $data['date'],
            ]);

            // 2. Kirim yoziladi
            CashboxTransaction::create([
                'cashbox_id' => $data['to_cashbox_id'],
                'type' => 'income',
                'currency_id' => $data['to_currency_id'],
                'amount' => $targetAmount,
                'comment' => '🔁 O‘tkazma: kassa ID ' . $data['from_cashbox_id'],
                'target_cashbox_id' => $data['from_cashbox_id'],
                'exchange_rate' => $data['exchange_rate'],
                'target_amount' => $data['amount'],
                'date' => $data['date'],
            ]);

            // 3. Balansni yangilash
            CashboxBalance::where('cashbox_id', $data['from_cashbox_id'])
                ->where('currency_id', $data['from_currency_id'])
                ->decrement('amount', $data['amount']);

            CashboxBalance::updateOrCreate(
                [
                    'cashbox_id' => $data['to_cashbox_id'],
                    'currency_id' => $data['to_currency_id'],
                ],
                [
                    'amount' => DB::raw("amount + {$targetAmount}")
                ]
            );
        });

        return response()->json([
            'message' => "✅ Pul muvoffaqiyatli o‘tkazildi.\n{$data['amount']} → {$targetAmount}"
        ]);
    }

}

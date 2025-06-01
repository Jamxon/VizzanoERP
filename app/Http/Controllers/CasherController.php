<?php

namespace App\Http\Controllers;

use App\Models\Cashbox;
use App\Models\CashboxBalance;
use App\Models\CashboxTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CasherController extends Controller
{
    public function getGroupsByDepartmentId(Request $request)
    {
        $departmentId = $request->input('department_id');

        if (!$departmentId) {
            return response()->json(['message' => '❌ department_id kiritilmadi.'], 422);
        }

        $groups = \App\Models\Group::where('department_id', $departmentId)
            ->with(['employees' => function ($query) {
                $query->select('id', 'group_id', 'balance');
            }])
            ->get();

        $result = $groups->map(function ($group) {
            $totalBalance = $group->employees->sum('balance');
            return [
                'id' => $group->id,
                'name' => $group->name,
                'total_balance' => $totalBalance,

            ];
        });

        return response()->json($result);
    }

    public function getDepartments()
    {
        $user = auth()->user();
        $departments = $user->employee->branch->departments()
            ->with(['employees' => function ($query) {
                $query->select('id', 'department_id', 'balance');
            }])
            ->get();
            dd($departments);

        $result = $departments->map(function ($department) {
            $totalBalance = $department->employees->sum('balance');
            return [
                'id' => $department->id,
                'name' => $department->name,
                'total_balance' => $totalBalance,
            ];
        });

        return response()->json($result);
    }


    public function getSource(): \Illuminate\Http\JsonResponse
    {
        $sources = \App\Models\IncomeSource::all();
        return response()->json($sources);
    }

    public function getVia(): \Illuminate\Http\JsonResponse
    {
        $via = \App\Models\IncomeVia::all();
        return response()->json($via);
    }

    public function getDestination(): \Illuminate\Http\JsonResponse
    {
        $destinations = \App\Models\IncomeDestination::all();
        return response()->json($destinations);
    }

    public function storeIncome(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'source_id' => 'nullable|exists:income_sources,id',
            'source_name' => 'nullable|string|max:255',
            'via_id' => 'required|exists:employees,id',
            'comment' => 'nullable|string|max:1000',
            'date' => 'nullable|date',
            'purpose' => 'nullable|string|max:1000',
        ]);

        if (empty($data['source_id'])) {
            if (empty($data['source_name'])) {
                return response()->json([
                    'message' => '❌ source_id ham source_name ham kiritilmadi.'
                ], 422);
            }

            $source = \App\Models\IncomeSource::firstOrCreate([
                'name' => $data['source_name']
            ]);
            $data['source_id'] = $source->id;
        }

        try {
            $data['type'] = 'income';
            $data['date'] = $data['date'] ?? now()->toDateString();
            $data['branch_id'] = auth()->user()->employee->branch_id;

            DB::transaction(function () use (&$data) {
                // ✅ 1. Branch bo‘yicha bitta Cashbox topamiz yoki yaratamiz
                $cashbox = \App\Models\Cashbox::firstOrCreate(
                    ['branch_id' => $data['branch_id']],
                    ['name' => 'Avto Cashbox: ' . now()->format('Y-m-d H:i:s')]
                );

                $data['cashbox_id'] = $cashbox->id;

                // ✅ 2. Transaction yozamiz
                \App\Models\CashboxTransaction::create($data);

                // ✅ 3. Cashbox balanceni yangilaymiz (currency_id bo‘yicha)
                $balance = \App\Models\CashboxBalance::firstOrCreate(
                    [
                        'cashbox_id' => $cashbox->id,
                        'currency_id' => $data['currency_id'],
                    ],
                    ['amount' => 0]
                );

                $balance->increment('amount', $data['amount']);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => '❌ Kirim muvaffaqiyatsiz.' . $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => '✅ Kirim muvaffaqiyatli qo‘shildi.']);
    }

    public function storeExpense(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'destination_id' => 'required|exists:employees,id',
            'via_id' => 'required|exists:employees,id',
            'purpose' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000',
            'date' => 'nullable|date',
        ]);

        $data['type'] = 'expense';
        $data['date'] = $data['date'] ?? now()->toDateString();
        $data['branch_id'] = auth()->user()->employee->branch_id;

        try {
            DB::transaction(function () use (&$data) {
                // ✅ Branch bo‘yicha bitta Cashbox topamiz yoki yaratamiz
                $cashbox = \App\Models\Cashbox::firstOrCreate(
                    ['branch_id' => $data['branch_id']],
                    ['name' => 'Avto Cashbox: ' . now()->format('Y-m-d H:i:s')]
                );

                $data['cashbox_id'] = $cashbox->id;

                // ✅ Balansni tekshiramiz
                $balance = \App\Models\CashboxBalance::firstOrCreate(
                    [
                        'cashbox_id' => $cashbox->id,
                        'currency_id' => $data['currency_id'],
                    ],
                    ['amount' => 0]
                );

                if ($balance->amount < $data['amount']) {
                    throw new \Exception('❌ Kassada yetarli mablag‘ mavjud emas.');
                }

                // ✅ Transaction yozamiz
                \App\Models\CashboxTransaction::create($data);

                // ✅ Balansni kamaytiramiz
                $balance->decrement('amount', $data['amount']);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => '❌ Chiqim muvaffaqiyatsiz.' . $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => '✅ Chiqim muvaffaqiyatli yozildi.']);
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
                    'balance' => $cashbox->balances->map(function ($balance) {
                        return [
                            'currency' => $balance->currency->name,
                            'amount' => number_format($balance->amount, 2, '.', ' '),
                        ];
                    })
                ];
            })
        ]);
    }

    public function getTransactions(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = CashboxTransaction::with('currency', 'cashbox')
            ->where('branch_id', auth()->user()->employee->branch_id);

        if ($request->filled('cashbox_id')) {
            $query->where('cashbox_id', $request->cashbox_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('search')) {
            $search = mb_strtolower($request->search); // PHP tomonda lowercase qilish
            $query->where(function ($q) use ($search) {
                $q->whereHas('source', function ($q2) use ($search) {
                    $q2->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                })
                    ->orWhereHas('via', function ($q3) use ($search) {
                        $q3->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('destination', function ($q4) use ($search) {
                        $q4->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereRaw('LOWER(purpose) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(comment) LIKE ?', ["%{$search}%"]);
            });
        }

        $transactions = $query->orderBy('date', 'desc')->get();

        return response()->json([
            'transactions' => $transactions->map(function ($tx) {
                return [
                    'cashbox' => $tx->cashbox,
                    'type' => $tx->type,
                    'amount' => number_format($tx->amount, 2, '.', ' '),
                    'currency' => $tx->currency,
                    'date' => $tx->date,
                    'source' => $tx->source,
                    'destination' => $tx->destination,
                    'via' => $tx->via,
                    'purpose' => $tx->purpose,
                    'comment' => $tx->comment,
                    'created_at' => $tx->created_at->format('Y-m-d H:i:s'),
                ];
            })
        ]);
    }

    public function transferBetweenCashboxes(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $data = $request->validate([
                'from_currency_id' => 'required|exists:currencies,id',
                'to_currency_id' => 'required|exists:currencies,id|different:from_currency_id',
                'from_amount' => 'required|numeric|min:0.01',
                'to_amount' => 'required|numeric|min:0.01',
                'exchange_rate' => 'required|numeric|min:0.0000001',
                'date' => 'nullable|date',
                'comment' => 'nullable|string|max:1000',
            ]);

            $data['date'] = $data['date'] ?? now()->toDateString();

            $branchId = auth()->user()->employee->branch_id;

            // Har ikkala kassani bir xil filialdan olish
            $cashbox = Cashbox::where('branch_id', $branchId)->firstOrFail();

            // Balansni tekshirish
            $fromBalance = CashboxBalance::where('cashbox_id', $cashbox->id)
                ->where('currency_id', $data['from_currency_id'])
                ->value('amount');

            if ($fromBalance === null || $fromBalance < $data['from_amount']) {
                return response()->json([
                    'message' => '❌ Kassada yetarli mablag‘ mavjud emas.'
                ], 422);
            }

            DB::transaction(function () use ($data, $cashbox) {
                // Chiqim
                CashboxTransaction::create([
                    'cashbox_id' => $cashbox->id,
                    'type' => 'expense',
                    'currency_id' => $data['from_currency_id'],
                    'amount' => $data['from_amount'],
                    'target_cashbox_id' => $cashbox->id,
                    'exchange_rate' => $data['exchange_rate'],
                    'target_amount' => $data['to_amount'],
                    'date' => $data['date'],
                    'comment' => '🔁 Valyuta ayirboshlash',
                    'branch_id' => $cashbox->branch_id,
                    'via_id' => auth()->user()->employee->id, // Hozirgi foydalanuvchi
                    'purpose' => $data['comment'] ?? null,
                ]);

                // Kirim
                CashboxTransaction::create([
                    'cashbox_id' => $cashbox->id,
                    'type' => 'income',
                    'currency_id' => $data['to_currency_id'],
                    'amount' => $data['to_amount'],
                    'target_cashbox_id' => $cashbox->id,
                    'exchange_rate' => $data['exchange_rate'],
                    'target_amount' => $data['from_amount'],
                    'date' => $data['date'],
                    'comment' => '🔁 Valyuta ayirboshlash',
                    'branch_id' => $cashbox->branch_id,
                    'via_id' => auth()->user()->employee->id, // Hozirgi foydalanuvchi
                    'purpose' => $data['comment'] ?? null,
                ]);

                // From balans kamayadi
                CashboxBalance::where('cashbox_id', $cashbox->id)
                    ->where('currency_id', $data['from_currency_id'])
                    ->decrement('amount', $data['from_amount']);

                // To balans oshadi
                CashboxBalance::updateOrCreate(
                    [
                        'cashbox_id' => $cashbox->id,
                        'currency_id' => $data['to_currency_id'],
                    ],
                    [
                        'amount' => DB::raw("amount + {$data['to_amount']}")
                    ]
                );
            });

            return response()->json([
                'message' => "✅ Pul muvaffaqiyatli ayirboshlanib saqlandi:\n{$data['from_amount']} → {$data['to_amount']}"
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => '❌ Xatolik yuz berdi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeRequestForm(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'purpose' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000',
            'deadline' => 'required|date|after_or_equal:today',
        ]);

        try {
            $requestForm = \App\Models\RequestForm::create([
                'employee_id' => $validated['employee_id'],
                'currency_id' => $validated['currency_id'],
                'amount' => $validated['amount'],
                'purpose' => $validated['purpose'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'status' => 'pending',
                'created_by' => auth()->id(),
                'branch_id' => auth()->user()->employee->branch_id,
                'deadline' => $validated['deadline'],
            ]);

            return response()->json([
                'message' => '✅ Talabnoma muvaffaqiyatli yaratildi.',
                'request_id' => $requestForm->id
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => '❌ Talabnoma yaratishda xatolik yuz berdi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRequestForm(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = \App\Models\RequestForm::with('employee', 'currency', 'creator', 'approver')
            ->where('branch_id', auth()->user()->employee->branch_id);

        // 🔍 Search
        if ($request->filled('search')) {
            $search = mb_strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', fn($q2) => $q2->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]))
                    ->orWhereRaw('LOWER(purpose) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(comment) LIKE ?', ["%{$search}%"]);
            });
        }

        // 📅 Deadline sanasi bo‘yicha filter
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('deadline', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->whereDate('deadline', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->whereDate('deadline', '<=', $request->end_date);
        }

        // 📋 Status bo‘yicha filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requestForms = $query->orderBy('deadline', 'desc')->paginate(10);

        return response()->json([
            'data' => $requestForms->getCollection()->transform(function ($form) {
                return [
                    'id' => $form->id,
                    'employee' => $form->employee->name,
                    'currency' => $form->currency->name,
                    'amount' => number_format($form->amount, 2, '.', ' '),
                    'purpose' => $form->purpose,
                    'comment' => $form->comment,
                    'status' => $form->status,
                    'created_at' => $form->created_at->format('Y-m-d H:i:s'),
                    'created_by' => $form->creator->employee->name ?? 'N/A',
                    'approved_by' => $form->approver->employee->name ?? 'N/A',
                    'approved_at' => $form->approved_at ? $form->approved_at->format('Y-m-d H:i:s') : null,
                    'deadline' => $form->deadline,
                ];
            }),
            'current_page' => $requestForms->currentPage(),
            'last_page' => $requestForms->lastPage(),
            'per_page' => $requestForms->perPage(),
            'total' => $requestForms->total(),
            'from' => $requestForms->firstItem(),
            'to' => $requestForms->lastItem(),
        ]);
    }
}

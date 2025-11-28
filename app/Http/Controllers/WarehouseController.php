<?php

namespace App\Http\Controllers;

use App\Models\Contragent;
use App\Models\Department;
use App\Models\Destination;
use App\Models\Employee;
use App\Models\MonthlySelectedOrder;
use App\Models\Order;
use App\Models\Source;
use App\Models\StockBalance;
use App\Models\StockEntry;
use App\Models\StockEntryItem;
use App\Models\Warehouse;
use App\Models\WarehouseCompleteOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Log;

class WarehouseController extends Controller
{

    public function exportStockBalancesPdf(): \Illuminate\Http\Response
    {
        $branchId = auth()->user()?->employee?->branch_id;

        $stockBalances = StockBalance::with([
            'item.unit',
            'item.color',
            'item.type',
            'item.currency',
            'warehouse',
            'order'
        ])
            ->whereHas('item', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->get();

        $today = now()->format('d.m.Y');
        $pdf = Pdf::loadView('pdf.stock-balances', compact('stockBalances'));
        return $pdf->download("$today-stock.pdf");
    }

    public function getBalance(Request $request): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()?->employee?->branch_id;
        $warehouseId = $request->input('warehouse_id');
        $search = $request->input('search');

        $query = StockBalance::where('quantity', '>', 0)
            ->when($warehouseId, function ($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->whereHas('warehouse', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->join('items', 'items.id', '=', 'stock_balances.item_id');

        // Search (kiril va lotin)
        if ($search) {
            $latin = transliterate_to_latin($search);
            $cyrillic = transliterate_to_cyrillic($search);
            $original = mb_strtolower($search, 'UTF-8');

            $query->where(function ($q) use ($original, $latin, $cyrillic) {
                $q->whereRaw('LOWER(items.name) LIKE ?', ["%{$original}%"])
                    ->orWhereRaw('LOWER(items.name) LIKE ?', ["%{$latin}%"])
                    ->orWhereRaw('LOWER(items.name) LIKE ?', ["%{$cyrillic}%"])
                    ->orWhereRaw('LOWER(items.code) LIKE ?', ["%{$original}%"]);
            });
        }

        // Klonlangan query orqali total quantity hisoblash
        $totalQuantity = (clone $query)->sum('stock_balances.quantity');

        // Asosiy query davom ettiriladi
        $balances = $query
            ->with([
                'item.unit',
                'item.color',
                'warehouse',
                'order'
            ])
            ->select('stock_balances.*')
            ->orderByRaw('stock_balances.quantity <= items.min_quantity DESC')
            ->orderBy('items.name')
            ->paginate(20);

        return response()->json([
            'data' => $balances,
            'total_quantity' => $totalQuantity,
        ]);
    }

    public function getUsers(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = trim($request->input('search'));
        $branchId = auth()->user()?->employee?->branch_id;

        $users = \App\Models\User::whereHas('employee', function ($query) use ($branchId, $search) {
            $query->where('branch_id', $branchId)
                ->where('status', 'working')
                ->when($search, function ($q, $s) {
                    $q->where('name', 'ILIKE', '%' . $s . '%');
                });
        })
            ->with(
                'employee',
                'employee.department',
                'employee.position',
            )
            ->get();

        return response()->json($users);
    }

    public function getWarehouses(): \Illuminate\Http\JsonResponse
    {
        $warehouses = Warehouse::where('branch_id', auth()->user()->employee->branch_id)->get();

        return response()->json($warehouses);
    }

    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('branch_id', auth()->user()->employee->branch_id)
            ->with([
                'contragent'
            ])
            ->get();

        return response()->json($orders);
    }

    public function showOrder(Order $order): \Illuminate\Http\JsonResponse
    {
        $order->load([
            'stockBalance',
            'stockBalance.item',
            'stockBalance.warehouse',
            'stockEntry',
            'stockEntry.items.item',
            'stockEntry.warehouse',
            'stockEntry.source',
            'stockEntry.destination',
            'stockEntry.employee',
            'stockEntry.contragent',
            'stockEntry.responsibleUser',
        ]);

        // Filtrlash: faqat quantity > 0 bo‘lganlarini qoldirish
        $filteredBalances = $order->stockBalance->filter(function ($balance) {
            return $balance->quantity > 0;
        })->values(); // values() indeksi to'g'ri bo'lishi uchun

        // Javobga yangi filtered stockBalance ni joylaymiz
        $order->setRelation('stockBalance', $filteredBalances);

        return response()->json($order);
    }

    public function getContragents(): \Illuminate\Http\JsonResponse
    {
        $contragents = Contragent::where('branch_id', auth()->user()->employee->branch_id)
            ->get();

        return response()->json($contragents);
    }

    public function getDestinations(): \Illuminate\Http\JsonResponse
    {
        $destinations = Destination::all();

        return response()->json($destinations);
    }

    public function getIncoming(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Ensure filters are only set if they exist in the request
            $filters = $request->only([
                'source_id',
                'warehouse_id',
                'search'
            ]);

            $search = trim($filters['search'] ?? '');
            $sourceId = $filters['source_id'] ?? null;
            $warehouseId = $filters['warehouse_id'] ?? null;
            $createdFrom = $request->input('start_date');
            $createdTo = $request->input('end_date');

            $incoming = StockEntry::query()
                ->where('type', 'incoming')

                // Filter: manba
                ->when($sourceId, fn ($q, $v) => $q->where('source_id', $v))

                // Filter: ombor
                ->when($warehouseId, fn ($q, $v) => $q->where('warehouse_id', $v))

                // Filter: created_at date range
                ->when($createdFrom, fn($q) => $q->whereDate('created_at', '>=', $createdFrom))
                ->when($createdTo, fn($q) => $q->whereDate('created_at', '<=', $createdTo))


                // Qidiruv: comment, id, user_id, user->employee->name, order_id
                ->when($search, function ($query, $search) {
                    $lowerSearch = mb_strtolower($search);
                    $likeSearch = '%' . $lowerSearch . '%';

                    return $query->where(function ($q) use ($lowerSearch, $search, $likeSearch) {
                        // Comment bo'yicha qidirish
                        $q->orWhere('comment', 'ILIKE', "%$search%");

                        // Raqamli qidiruvlar uchun
                        if (is_numeric($search)) {
                            $q->orWhere('id', (int)$search);
                            $q->orWhere('user_id', (int)$search);
                            $q->orWhere('order_id', (int)$search);
                        }

                        // ID larni string sifatida qidirish
                        $q->orWhereRaw('CAST(id AS VARCHAR) LIKE ?', ['%' . $search . '%']);
                        $q->orWhereRaw('CAST(user_id AS VARCHAR) LIKE ?', ['%' . $search . '%']);
                        $q->orWhereRaw('CAST(order_id AS VARCHAR) LIKE ?', ['%' . $search . '%']);

                        // User va employee bo'yicha qidirish
                        $q->orWhereHas('employee', function ($q) use ($lowerSearch, $likeSearch) {
                            $q->whereRaw('LOWER(name) LIKE ?', [$likeSearch]);
                        });

                        // YANGI: item.code bo‘yicha qidiruv
                        $q->orWhereHas('items', function ($q) use ($likeSearch) {
                            $q->whereHas('item', function ($sub) use ($likeSearch) {
                                $sub->whereRaw('LOWER(code) LIKE ?', [$likeSearch])
                                    ->orWhereRaw('LOWER(name) LIKE ?', [$likeSearch]);
                            });
                        });

                    });
                })

                // Loading necessary relationships
                ->with([
                    'items.currency',
                    'items.item',
                    'items.item.unit',
                    'warehouse',
                    'source',
                    'destination',
                    'employee',
                    'order',
                    'contragent',
                    'responsibleUser',
                ])

                ->latest('updated_at')
                ->paginate(20);

            return response()->json($incoming);
        } catch (\Throwable $e) {
            // Handle errors gracefully and return meaningful messages
            return response()->json([
                'message' => 'Maʼlumotlarni olishda xatolik yuz berdi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadPdf($id): \Illuminate\Http\Response
    {
        $entry = StockEntry::with([
            'items.currency',
            'items.item',
            'warehouse',
            'source',
            'destination',
            'employee',
            'order',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.stock-entry', compact('entry'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("stock-entry-{$entry->id}.pdf");
    }

    public function storeIncoming(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'      => 'required|exists:warehouses,id',
            'source_id'         => 'nullable|exists:sources,id',
            'source_name'       => 'nullable|string',
            'comment'           => 'nullable|string',
            'order_id'          => 'nullable|exists:orders,id',
            'contragent_id'     => 'nullable|exists:contragent,id',
            'responsible_user_id' => 'nullable|exists:users,id',
            'items'             => 'required|array|min:1',
            'items.*.item_id'   => 'required|exists:items,id',
            'items.*.quantity'  => 'required|numeric|min:0.01',
            'items.*.price'     => 'required|numeric|min:0',
            'items.*.currency_id' => 'required|exists:currencies,id',
        ]);

        DB::beginTransaction();

        try {
            // Manba nomi bo‘yicha avtomatik source yaratish
            if (!$validated['source_id'] && $validated['source_name']) {
                $source = Source::firstOrCreate(['name' => $validated['source_name']]);
                $validated['source_id'] = $source->id;
            }

            // Yangi kirim yozuvini yaratish
            $entry = StockEntry::create([
                'type'          => 'incoming',
                'warehouse_id'  => $validated['warehouse_id'],
                'source_id'     => $validated['source_id'] ?? null,
                'comment'       => $validated['comment'] ?? null,
                'order_id'      => $validated['order_id'] ?? null,
                'contragent_id' => $validated['contragent_id'] ?? null,
                'user_id'       => auth()->user()->employee->id,
                'responsible_user_id' => $validated['responsible_user_id'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                // Har bir mahsulot uchun entry item yozuvi
                $entryItem = $entry->items()->create($item);

                $itemModel = \App\Models\Item::find($item['item_id']);

                // Zaxira (stock balance) yangilanishi
                $balance = StockBalance::firstOrCreate(
                    [
                        'item_id'      => $item['item_id'],
                        'warehouse_id' => $validated['warehouse_id'],
                        'order_id'     => $validated['order_id'] ?? null,
                    ],
                    ['quantity' => 0]
                );

                $oldQty = $balance->quantity;
                $balance->increment('quantity', $item['quantity']);

                // Log yozish - tushunarliroq formatda
                Log::add(
                    auth()->id(),
                    'Omborga kirim',
                    'stock_in',
                    [
                        'item_name' => $itemModel->name,
                        'entry_id' => $entry->id,
                        'old_quantity' => $oldQty,
                        'added_quantity' => $item['quantity'],
                    ],
                    [
                        'item_id' => $item['item_id'],
                        'entry_id' => $entry->id,
                        'new_quantity' => $balance->quantity,
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Kirim muvaffaqiyatli qo‘shildi.',
                'entry' => $entry->load('items'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Xatolik yuz berdi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOutcome(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $filters = $request->only([
                'destination_id',
                'warehouse_id',
                'search'
            ]);

            $search = trim($filters['search'] ?? '');
            $sourceId = $filters['destination_id'] ?? null;
            $warehouseId = $filters['warehouse_id'] ?? null;
            $createdFrom = $request->input('start_date');
            $createdTo = $request->input('end_date');

            $outgoing = StockEntry::query()
                ->where('type', 'outcome')

                // Filter: manba
                ->when($sourceId, fn ($q, $v) => $q->where('destination_id', $v))

                // Filter: ombor
                ->when($warehouseId, fn ($q, $v) => $q->where('warehouse_id', $v))

                // Filter: created_at date range
                ->when($createdFrom, fn($q) => $q->whereDate('created_at', '>=', $createdFrom))
                ->when($createdTo, fn($q) => $q->whereDate('created_at', '<=', $createdTo))

                // Qidiruv: comment, id, user_id, user->employee->name, order_id
                ->when($search, function ($query, $search) {
                    $lowerSearch = mb_strtolower($search);
                    $likeSearch = '%' . $lowerSearch . '%';

                    return $query->where(function ($q) use ($lowerSearch, $search, $likeSearch) {
                        $q->orWhere('comment', 'ILIKE', "%$search%");

                        if (is_numeric($search)) {
                            $q->orWhere('id', (int)$search);
                            $q->orWhere('user_id', (int)$search);
                            $q->orWhere('order_id', (int)$search);
                        }

                        $q->orWhereRaw('CAST(id AS VARCHAR) LIKE ?', ['%' . $search . '%']);
                        $q->orWhereRaw('CAST(user_id AS VARCHAR) LIKE ?', ['%' . $search . '%']);
                        $q->orWhereRaw('CAST(order_id AS VARCHAR) LIKE ?', ['%' . $search . '%']);

                        // Employee name orqali qidiruv
                        $q->orWhereHas('employee', function ($q) use ($likeSearch) {
                            $q->whereRaw('LOWER(name) LIKE ?', [$likeSearch]);
                        });

                        // YANGI: item.code bo‘yicha qidiruv
                        $q->orWhereHas('items', function ($q) use ($likeSearch) {
                            $q->whereHas('item', function ($sub) use ($likeSearch) {
                                $sub->whereRaw('LOWER(code) LIKE ?', [$likeSearch])
                                    ->orWhereRaw('LOWER(name) LIKE ?', [$likeSearch]);
                            });
                        });
                    });
                })

                // Eager load related models
                ->with([
                    'items.currency',
                    'items.item',
                    'items.item.unit',
                    'warehouse',
                    'source',
                    'destination',
                    'employee',
                    'order',
                    'contragent',
                    'responsibleUser',
                ])

                ->latest('updated_at')
                ->paginate(20);

            return response()->json($outgoing);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Chiqimlarni olishda xatolik yuz berdi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeOutcome(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'        => 'required|exists:warehouses,id',
            'destination_id'      => 'nullable|exists:destinations,id',
            'destination_name'    => 'nullable|string',
            'comment'             => 'nullable|string',
            'order_id'            => 'nullable|exists:orders,id',
            'contragent_id'       => 'nullable|exists:contragent,id',
            'responsible_user_id' => 'nullable|exists:users,id',
            'items'               => 'required|array|min:1',
            'items.*.item_id'     => 'required|exists:items,id',
            'items.*.quantity'    => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();

        try {
            // Yangi destination yaratish
            if (!$validated['destination_id'] && $validated['destination_name']) {
                $destination = Destination::firstOrCreate(['name' => $validated['destination_name']]);
                $validated['destination_id'] = $destination->id;
            }

            // Yangi chiqim yozuvi
            $entry = StockEntry::create([
                'type'               => 'outcome',
                'warehouse_id'       => $validated['warehouse_id'],
                'destination_id'     => $validated['destination_id'] ?? null,
                'comment'            => $validated['comment'] ?? null,
                'order_id'           => $validated['order_id'] ?? null,
                'contragent_id'      => $validated['contragent_id'] ?? null,
                'user_id'            => auth()->user()->employee->id,
                'responsible_user_id'=> $validated['responsible_user_id'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $itemModel = \App\Models\Item::find($item['item_id']);

                // Entry item
                $entryItem = $entry->items()->create([
                    'item_id'     => $item['item_id'],
                    'quantity'    => $item['quantity'],
                    'price'       => $itemModel->price,
                    'currency_id' => $itemModel->currency_id,
                ]);

                // Zaxira topish
                $balance = StockBalance::where('item_id', $item['item_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->where(function ($query) use ($validated) {
                        if ($validated['order_id']) {
                            $query->where('order_id', $validated['order_id']);
                        } else {
                            $query->whereNull('order_id');
                        }
                    })
                    ->first();

                if (!$balance) {
                    throw new \Exception("Zaxirada mahsulot topilmadi: {$itemModel->name}");
                }

                $oldQty = $balance->quantity;

                if ($oldQty < $item['quantity']) {
                    throw new \Exception("Zaxirada yetarli mahsulot yo‘q: {$itemModel->name}. Max: {$oldQty}, kerak: {$item['quantity']}");
                }

                $balance->decrement('quantity', $item['quantity']);

                // Log
                Log::add(
                    auth()->id(),
                    'Ombordan chiqim',
                    'stock_out',
                    [
                        'item_name' => $itemModel->name,
                        'entry_id' => $entry->id,
                        'old_quantity' => $oldQty,
                        'removed_quantity' => $item['quantity'],
                    ],
                    [
                        'item_id' => $item['item_id'],
                        'entry_id' => $entry->id,
                        'new_quantity' => $balance->quantity,
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Chiqim muvaffaqiyatli bajarildi.',
                'entry'   => $entry->load('items'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Xatolik yuz berdi.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function warehouseCompleteOrderStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        $data['department_id'] = auth()->user()->employee->department_id;
        $data['quantity'] = Order::findOrFail($data['order_id'])->quantity;

        $exist = WarehouseCompleteOrder::where('order_id', $data['order_id'])
            ->where('department_id', $data['department_id'])
            ->first();

        if ($exist) {
            return response()->json([
                'message' => 'Record already exists',
                'data' => $exist
            ], 200);
        }

        $record = WarehouseCompleteOrder::create($data);

        return response()->json([
            'message' => 'Created successfully',
            'data' => $record
        ], 201);
    }

    public function warehouseCompleteOrdersGet(Request $request): \Illuminate\Http\JsonResponse
    {
        $month = $request->month ?? now()->format('Y-m');
        $startDate = Carbon::parse($month)->startOfMonth()->toDateString();
        $endDate = Carbon::parse($month)->endOfMonth()->toDateString();

        $department = Department::find(auth()->user()->employee->department_id);
        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }
        $departmentBudget = $department->departmentBudget;
        if (!$departmentBudget) {
            return response()->json(['message' => 'Department budget not found'], 404);
        }
        if ($departmentBudget->type !== 'minute_based') {
            return response()->json(['message' => 'Department budget type not supported'], 400);
        }

        // 1) Employees
        $employees = Employee::where('department_id', $department->id)
            ->select('id', 'name', 'percentage', 'position_id', 'img', 'payment_type', 'salary')
            ->get();

        if ($employees->isEmpty()) {
            return response()->json(['message' => 'No employees found'], 404);
        }

        $totalPercentage = $employees->sum('percentage');
        if ($totalPercentage <= 0) {
            return response()->json(['message' => 'Employees percentage sum is zero'], 422);
        }

        // Positions (bulk)
        $positionIds = $employees->pluck('position_id')->filter()->unique()->toArray();
        $positions = DB::table('positions')->whereIn('id', $positionIds)->pluck('name', 'id');

        // Working days
        $total_working_days = DB::table('attendance')
            ->whereBetween('date', [$startDate, $endDate])
            ->select(DB::raw('count(distinct date) as days'))
            ->value('days') ?? 0;

        // Attendance
        $attendancePresent = DB::table('attendance')
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('employee_id', $employees->pluck('id')->toArray())
            ->where('status', 'present')
            ->select('employee_id', DB::raw('count(*) as present_days'))
            ->groupBy('employee_id')
            ->pluck('present_days', 'employee_id')
            ->toArray();

        // Selected order IDs
        $selectedOrderIds = MonthlySelectedOrder::whereDate('month', $month . '-01')
            ->pluck('order_id')
            ->toArray();

        if (empty($selectedOrderIds)) {
            return response()->json([
                'id' => $department->id,
                'name' => $department->name,
                'budget' => [
                    'id' => $departmentBudget->id,
                    'quantity' => (string) $departmentBudget->quantity,
                    'type' => $departmentBudget->type,
                ],
                'employee_count' => $employees->count(),
                'employees' => [],
                'orders' => [],
                'totals' => [
                    'earned' => 0,
                    'possible_full_earn' => 0,
                ],
            ]);
        }

        // Produced quantity
        $producedPerOrder = WarehouseCompleteOrder::where('department_id', $department->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('order_id', $selectedOrderIds)
            ->select('order_id', DB::raw('SUM(quantity) as produced_quantity'))
            ->groupBy('order_id')
            ->pluck('produced_quantity', 'order_id')
            ->toArray();

        // Orders load
        $orders = Order::with(['orderModel.model'])
            ->whereIn('id', $selectedOrderIds)
            ->get()
            ->keyBy('id');

        $ordersResult = [];
        $totalEarnedAll = 0;
        $totalPossibleAll = 0;

        foreach ($selectedOrderIds as $orderId) {
            $order = $orders->get($orderId);
            if (!$order) continue;

            $plannedQty = (int) ($order->quantity ?? 0);
            $producedQty = (int) ($producedPerOrder[$orderId] ?? 0);
            $remainingQty = max(0, $plannedQty - $producedQty);

            $minutePerPiece = $order->orderModel->model->minute ?? 0;
            $rate = (float) $departmentBudget->quantity;

            $earnedTotalForOrder = $rate * $minutePerPiece * $producedQty;
            $possibleFullForOrder = $rate * $minutePerPiece * $plannedQty;
            $remainingMoneyForOrder = $rate * $minutePerPiece * $remainingQty;

            $totalEarnedAll += $earnedTotalForOrder;
            $totalPossibleAll += $possibleFullForOrder;

            // Employees per order
            $empList = [];
            foreach ($employees as $emp) {
                $empPercent = (float) $emp->percentage;
                $empFactor = $empPercent / $totalPercentage;

                $empList[] = [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'percentage' => number_format($empPercent, 2, '.', ''),
                    'position' => $emp->position_id ? [
                        'id' => $emp->position_id,
                        'name' => $positions->get($emp->position_id) ?? 'N/A'
                    ] : null,
                    'orders' => [
                        'order' => [
                            'id' => $order->id,
                            'name' => $order->name,
                            'minute' => (string) $minutePerPiece,
                        ],
                        'planned_quantity' => $plannedQty,
                        'produced_quantity' => $producedQty,
                        'remaining_quantity' => $remainingQty,
                        'earned_amount' => round($earnedTotalForOrder * $empFactor, 2),
                        'remaining_earn_amount' => round($remainingMoneyForOrder * $empFactor, 2),
                        'possible_full_earn_amount' => round($possibleFullForOrder * $empFactor, 2),
                    ],
                ];
            }

            $ordersResult[] = [
                'order' => [
                    'id' => $order->id,
                    'name' => $order->name,
                    'minute' => (string) $minutePerPiece,
                ],
                'planned_quantity' => $plannedQty,
                'produced_quantity' => $producedQty,
                'remaining_quantity' => $remainingQty,
                'earned_amount' => round($earnedTotalForOrder, 2),
                'remaining_earn_amount' => round($remainingMoneyForOrder, 2),
                'possible_full_earn_amount' => round($possibleFullForOrder, 2),
                'employees' => $empList,
            ];
        }

        // FINAL EMPLOYEE LIST (with totals)
        $employeesFinal = $employees->map(function($emp) use (
            $positions,
            $attendancePresent,
            $total_working_days,
            $totalEarnedAll,
            $totalPossibleAll,
            $totalPercentage
        ) {
            $empPercent = (float) $emp->percentage;
            $factor = $empPercent / $totalPercentage;

            return [
                'id' => $emp->id,
                'name' => $emp->name,
                'percentage' => number_format($empPercent, 2, '.', ''),
                'position' => $emp->position_id ? [
                    'id' => $emp->position_id,
                    'name' => $positions->get($emp->position_id) ?? 'N/A'
                ] : null,
                'attendance' => [
                    'present_days' => (int) ($attendancePresent[$emp->id] ?? 0),
                    'total_working_days' => (int) $total_working_days,
                ],

                // 🔥 Siz so‘ragan yangi maydonlar:
                'total_earned_share' => round($totalEarnedAll * $factor, 2),
                'total_possible_share' => round($totalPossibleAll * $factor, 2),

                'orders' => []
            ];
        })->values()->toArray();

        return response()->json([
            'id' => $department->id,
            'name' => $department->name,
            'budget' => [
                'id' => $departmentBudget->id,
                'quantity' => (string) $departmentBudget->quantity,
                'type' => $departmentBudget->type,
            ],
            'employee_count' => $employees->count(),
            'employees' => $employeesFinal,
            'orders' => $ordersResult,
            'totals' => [
                'earned' => round($totalEarnedAll, 2),
                'possible_full_earn' => round($totalPossibleAll, 2),
            ],
        ]);
    }

}
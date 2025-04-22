<?php

namespace App\Http\Controllers;

use App\Models\Contragent;
use App\Models\Destination;
use App\Models\Order;
use App\Models\Source;
use App\Models\StockBalance;
use App\Models\StockEntry;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Log;

class WarehouseController extends Controller
{
    public function getBalance(Request $request): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()?->employee?->branch_id;

        $warehouseId = $request->input('warehouse_id');

        $balance = StockBalance::where('quantity', '>', 0)
            ->when($warehouseId, function ($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->whereHas('warehouse', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->with([
                'item.unit',
                'warehouse',
                'order'
            ])
            ->get();

        return response()->json($balance);
    }

    public function showBalance(Request $request): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()?->employee?->branch_id;
        $stockBalanceId = $request->input('stock_balance_id');

        // Asosiy balance ni topamiz
        $balance = StockBalance::where('id', $stockBalanceId)
            ->whereHas('warehouse', fn($q) => $q->where('branch_id', $branchId))
            ->with(['item.unit', 'warehouse', 'order'])
            ->firstOrFail();

        // Kirim-chiqim entrylarini topamiz (entry darajasida)
        $entries = StockEntry::where('warehouse_id', $balance->warehouse_id)
            ->whereHas('items', function ($q) use ($balance) {
                $q->where('item_id', $balance->item_id);
            })
            ->when($balance->order_id, function ($q) use ($balance) {
                $q->where('order_id', $balance->order_id);
            }, function ($q) {
                $q->whereNull('order_id');
            })
            ->with([
                'items.item.unit',
                'warehouse',
                'source',
                'destination',
                'employee',
                'responsibleUser.employee',
                'contragent'
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'balance' => $balance,
            'entries' => $entries,
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

                    });
                })

                // Loading necessary relationships
                ->with([
                    'items.currency',
                    'items.item',
                    'warehouse',
                    'source',
                    'destination',
                    'employee',
                    'order',
                    'contragent',
                    'responsibleUser',
                ])

                ->latest('updated_at')
                ->paginate(10);

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

                        $q->orWhereHas('employee', function ($q) use ($likeSearch) {
                            $q->whereRaw('LOWER(name) LIKE ?', [$likeSearch]);
                        });
                    });
                })

                // Eager load related models
                ->with([
                    'items.currency',
                    'items.item',
                    'warehouse',
                    'source',
                    'destination',
                    'employee',
                    'order',
                    'contragent',
                    'responsibleUser',
                ])

                ->latest('updated_at')
                ->paginate(10);

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

}
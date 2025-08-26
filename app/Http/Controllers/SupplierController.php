<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Item;
use App\Models\Log;
use App\Models\SupplierOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SupplierController extends Controller
{
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:users,id',
            'comment'     => 'nullable|string',
            'deadline'    => 'required|date',
            'items'       => 'required|array|min:1',
            'items.*.item_id'  => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            // Kod yaratish: ORD-0001
            $lastId = SupplierOrder::max('id') + 1;
            $code   = 'ORD-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

            $order = SupplierOrder::create([
                'supplier_id'    => $request->supplier_id,
                'code'           => $code,
                'comment'        => $request->comment,
                'status'         => 'new',
                'created_by'     => auth()->id(),
                'deadline'       => $request->deadline,
                'completed_date' => null,
                'received_date'  => null,
                'branch_id'      => auth()->user()->employee->branch_id,
            ]);

            foreach ($request->items as $row) {
                $it = Item::findOrFail($row['item_id']);

                $order->items()->create([
                    'item_id'     => $row['item_id'],
                    'quantity'    => $row['quantity'],
                    'price'       => $it->price,
                    'currency_id' => $it->currency_id,
                ]);
            }

            DB::commit();

            // --- Telegramga batafsil xabar tayyorlash ---
            // Buyurtmani kerakli relationlar bilan yuklaymiz
            $order->load([
                'supplier:id,name',
                'items.item:id,name,unit', // agar itemda unit bo‘lsa
            ]);

            // Currency kodlarini birdaniga olib qo‘yamiz (N+1 bo‘lmasin)
            $currencyMap = [];
            if (class_exists(\App\Models\Currency::class)) {
                $currencyMap = Currency::whereIn('id', $order->items->pluck('currency_id')->unique())
                    ->get()->keyBy('id')
                    ->map(fn ($c) => $c->code ?? $c->name ?? 'UZS')
                    ->toArray();
            }

            // format helper
            $fmt = function ($n) {
                $s = number_format((float)$n, 2, '.', ' ');
                return rtrim(rtrim($s, '0'), '.'); // 1200.00 -> 1200 ; 12.50 -> 12.5
            };

            $lines  = [];
            $totals = []; // ['USD' => 123.45, 'UZS' => ...]
            $i = 1;

            foreach ($order->items as $oi) {
                $name   = $oi->item->name ?? ('#'.$oi->item_id);
                $qty    = $oi->quantity;
                $price  = $oi->price;
                $codeC  = $currencyMap[$oi->currency_id] ?? 'UZS';
                $line   = $qty * $price;

                $totals[$codeC] = ($totals[$codeC] ?? 0) + $line;

                $unitTxt = $oi->item->unit ?? ''; // ixtiyoriy
                $unitStr = $unitTxt ? " <i>($unitTxt)</i>" : '';

                $lines[] =
                    "{$i}. <b>{$name}</b>{$unitStr}\n".
                    "   ├─ Miqdor: <b>{$fmt($qty)}</b>\n".
                    "   ├─ Narx: <b>{$fmt($price)} {$codeC}</b>\n".
                    "   └─ Jami: <b>{$fmt($line)} {$codeC}</b>";
                $i++;
            }

            $totalsStr = '';
            foreach ($totals as $cCode => $sum) {
                $totalsStr .= "• <b>{$fmt($sum)} {$cCode}</b>\n";
            }

            $creator = auth()->user()->employee->name ?? auth()->user()->name ?? '---';
            $comment = trim((string)$order->comment);
            $commentLine = $comment ? "\n📝 Izoh: {$comment}\n" : "";

            $message =
                "<b>🆕 Yangi buyurtma yaratildi!</b>\n\n".
                "📌 Kod: <b>{$order->code}</b>\n".
                "🏢 Ta'minotchi: <b>{$order->supplier->employee->name}</b>\n".
                "📅 Deadline: <b>{$order->deadline}</b>\n".
                "👤 Yaratuvchi: <b>{$creator}</b>".
                $commentLine.
                "\n<b>🧾 Pozitsiyalar:</b>\n".
                implode("\n", $lines).
                "\n\n<b>📦 Umumiy summalar (valyuta bo‘yicha):</b>\n{$totalsStr}";

            // Telegramga yuborish (HTML parse_mode)
            $botToken = '7976854451:AAGKnnFBQJ0-GC8FAwET_GD09S4PuktlrDo';
            $chatId   = -1002865541209;

            try {
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id'    => $chatId,
                    'text'       => $message,
                    'parse_mode' => 'HTML'
                ]);
            } catch (\Throwable $te) {
                // xabar yuborishda muammo bo‘lsa — logga yozib qo‘yamiz, lekin API javobini buzmaymiz
                \Log::warning('Telegram sendMessage failed: '.$te->getMessage());
            }

            // Log
            Log::add(
                auth()->user()->id,
                "Ta'minotchiga buyurtma berildi ($code)",
                'create',
                null,
                $order->load('supplier', 'items.item')
            );

            return response()->json([
                'message'  => 'Buyurtma muvaffaqiyatli yaratildi.',
                'order_id' => $order->id,
                'code'     => $order->code
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Buyurtma yaratishda xatolik yuz berdi.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getSupplier(): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()?->employee?->branch_id;

        if (!$branchId) {
            return response()->json(['message' => 'Branch ID topilmadi.'], 400);
        }

        $suppliers = User::whereHas('role', function ($query) {
            $query->where('name', 'supplier');
        })
            ->whereHas('employee', function ($query) use ($branchId) {
                $query->where('status', '!=', 'kicked')
                    ->where('branch_id', $branchId);
            })
            ->with('employee')
            ->get();

        return response()->json($suppliers);
    }

    public function getSupplierOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()?->employee?->branch_id;

        if (!$branchId) {
            return response()->json(['message' => 'Branch aniqlanmadi'], 400);
        }

        $search = $request->input('search');

        $supplierOrders = SupplierOrder::where('branch_id', $branchId)
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('deadline'), function ($query) use ($request) {
                $query->whereDate('deadline', $request->input('deadline'));
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(code) LIKE ?', ['%' . mb_strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(comment) LIKE ?', ['%' . mb_strtolower($search) . '%'])
                        ->orWhereHas('supplier.employee', function ($q) use ($search) {
                            $q->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($search) . '%']);
                        });
                });
            })
            ->with([
                'items.item',
                'items.item.unit',
                'items.item.color',
                'items.item.type',
                'items.item.currency',
                'supplier.employee',
                'createdBy.employee',
            ])
            ->orderBy('deadline', 'desc')
            ->paginate(20);

        return response()->json($supplierOrders);
    }

    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()?->employee?->branch_id;

        if (!$branchId) {
            return response()->json(['message' => 'Branch aniqlanmadi'], 400);
        }

        $search = $request->input('search');

        $orders = SupplierOrder::where('branch_id', $branchId)
            ->where('supplier_id', auth()->id())
            ->where('status', '!=', 'inactive')
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('deadline'), function ($query) use ($request) {
                $query->whereDate('deadline', $request->input('deadline'));
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(code) LIKE ?', ['%' . mb_strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(comment) LIKE ?', ['%' . mb_strtolower($search) . '%'])
                        ->orWhereHas('supplier.employee', function ($q) use ($search) {
                            $q->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($search) . '%']);
                        });
                });
            })
            ->with([
                'items.item',
                'items.item.unit',
                'items.item.color',
                'items.item.type',
                'items.item.currency',
                'supplier.employee',
                'createdBy.employee',
            ])
            ->orderBy('deadline', 'desc')
            ->paginate(20);

        return response()->json($orders);
    }

    public function destroySupplierOrder($id): \Illuminate\Http\JsonResponse
    {
        $order = SupplierOrder::findOrFail($id);

        if ($order->status !== 'new') {
            return response()->json(['message' => 'Faqat yangi buyurtmalarni o\'chirish mumkin.'], 400);
        }

        if ($order->created_by !== auth()->id()) {
            return response()->json(['message' => 'Siz faqat o\'zingizning buyurtmalaringizni o\'chira olasiz.'], 403);
        }

        $order->update([
            'status' => 'inactive',
        ]);

        Log::add(
            auth()->user()->id,
            "Ta'minotchiga buyurtma o'chirildi (ID: $id)",
            'delete',
            null,
            $order
        );

        return response()->json(['message' => 'Buyurtma muvaffaqiyatli o\'chirildi.'], 200);
    }

    public function receiveSupplierOrder($id): \Illuminate\Http\JsonResponse
    {
        $order = SupplierOrder::findOrFail($id);

        if ($order->status !== 'new') {
            return response()->json(['message' => 'Faqat yangi buyurtmalarni qabul qilish mumkin.'], 400);
        }

        if ($order->supplier_id !== auth()->id()) {
            return response()->json(['message' => 'Siz faqat o\'zingizning buyurtmalaringizni qabul qilishingiz mumkin.'], 403);
        }

        $order->update([
            'status' => 'active',
            'received_date' => now(),
        ]);

        Log::add(
            auth()->user()->id,
            "Ta'minotchiga buyurtma qabul qilindi (ID: $id)",
            'update',
            null,
            $order
        );

        return response()->json(['message' => 'Buyurtma muvaffaqiyatli qabul qilindi.'], 200);
    }
}

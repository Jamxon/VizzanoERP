<?php

namespace App\Http\Controllers;

use App\Exports\PackingListExport;
use App\Jobs\PackageExportJob;
use App\Models\Employee;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Bonus;
use Maatwebsite\Excel\Facades\Excel;

class PackageMasterController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = $request->input('search', '');

        $orders = Order::where('branch_id', auth()->user()->employee->branch_id)
            ->whereIn('status', ['tailoring', 'tailored', 'checking', 'checked'])
            ->where(function ($query) use ($search) {
                $query->whereHas('orderModel.model', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            })
            ->with(
                'orderModel.model',
                'orderModel.material',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size'
            )
            ->get();

        return response()->json($orders);
    }

    public function showOrder(Order $order): \Illuminate\Http\JsonResponse
    {
            $order->load(
                'packageOutcomes',
                'orderModel.model',
                'orderModel.material',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
            );

        return response()->json($order);
    }

    public function packageStore(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'sizes' => 'required|array',
        ]);

        $orders = Order::with(['orderModel.model', 'contragent'])->whereIn('id', $validated['orders'])->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Buyurtmalar topilmadi'], 404);
        }

        $modelName = $orders->first()?->orderModel?->model->name ?? 'Model nomi yo‘q';
        $customerName = $orders->first()?->contragent->name ?? 'Buyurtmachi yo‘q';

        $colorMap = [];
        $summaryMap = [];

        foreach ($validated['sizes'] as $sizeItem) {
            $sizeId = $sizeItem['size_id'];
            $capacity = $sizeItem['capacity'];
            $bruttoKg = $sizeItem['kg'] ?? 0; // <-- bu brutto (Вес брутто)
            $nettoKg = round($bruttoKg - 1.4, 2); // <-- bu neto

            $colors = $sizeItem['colors'];
            $sizeName = OrderSize::find($sizeId)?->size->name ?? 'Размер топилмади';

            foreach ($colors as $colorItem) {
                foreach ($colorItem as $colorName => $qty) {
                    $colorMap[$colorName][] = [
                        'size_name' => $sizeName,
                        'qty' => $qty,
                        'capacity' => $capacity,
                        'brutto' => $bruttoKg,
                        'netto' => $nettoKg,
                    ];
                }
            }
        }

        $data = [];
        $index = 1;
        $summaryList = [
            ['№', 'Артикул', 'Коллекция зима Комбинезон', 'Коробка (шт)', 'Обший (шт)', 'Нетто (кг)', 'Брутто (кг)']
        ];

        $totalPacks = 0;
        $totalQtyAll = 0;
        $totalNetto = 0;
        $totalBrutto = 0;

        foreach ($colorMap as $color => $items) {
            $leftovers = [];
            $packCount = 0;
            $totalQty = 0;
            $netto = 0;
            $brutto = 0;

            foreach ($items as $item) {
                $qty = $item['qty'];
                $sizeName = $item['size_name'];
                $capacity = $item['capacity'];
                $packNo = 1;

                while ($qty >= $capacity) {
                    $data[] = ['', "Артикул: $modelName", '', '', '', '', '', '', ''];
                    $data[] = [$index, "Цвет: $color", $sizeName, $customerName, $packNo, 1, $capacity, $item['netto'],  $item['brutto']];
                    $data[] = ['', "Юбка для девочки", '', '', '', '', '', '', ''];

                    $qty -= $capacity;
                    $packNo++;
                    $index++;

                    $packCount++;
                    $totalQty += $capacity;
                    $netto += $item['netto'];
                    $brutto += $item['brutto'];
                }

                if ($qty > 0) {
                    $leftovers[] = ['size_name' => $sizeName, 'qty' => $qty];
                }
            }

            if (count($leftovers)) {
                $data[] = ['', "Артикул: $modelName", '', '', '', '', '', '', ''];
                $data[] = [
                    $index,
                    "Цвет: $color",
                    $leftovers[0]['size_name'] ?? '',
                    $customerName,
                    $packNo,
                    1,
                    $leftovers[0]['qty'] ?? '',
                    '',
                    ''
                ];
                $data[] = [
                    '',
                    "Юбка для девочки",
                    $leftovers[1]['size_name'] ?? '',
                    '',
                    '',
                    '',
                    $leftovers[1]['qty'] ?? '',
                    '',
                    ''
                ];
                $index++;
                $packCount++;
                $qtySum = ($leftovers[0]['qty'] ?? 0) + ($leftovers[1]['qty'] ?? 0);
                $totalQty += $qtySum;
                $netto += $qtySum * 1.45;
                $brutto += $qtySum * 1.62;
            }

            // Umumiy yig‘ish
            $totalPacks += $packCount;
            $totalQtyAll += $totalQty;
            $totalNetto += $netto;
            $totalBrutto += $brutto;
        }

// Faqat bitta yakuniy qator
        $summaryList[] = [
            1,
            $modelName,
            'Комбенизон для девочки',
            $totalPacks,
            $totalQtyAll,
            round($totalNetto, 2),
            round($totalBrutto, 2),
        ];

        $stickers = [];

        foreach ($colorMap as $color => $items) {
            $rows = [];
            $totalQty = [];
            $brutto = 0;
            $netto = 0;

            foreach ($items as $item) {
                $size = $item['size_name'];
                $qty = $item['qty'];
                $totalQty[$size] = ($totalQty[$size] ?? 0) + $qty;
                $brutto += $item['brutto'];
                $netto += $item['netto'];
            }

            $sizeRows = [];
            foreach ($totalQty as $size => $qty) {
                $sizeRows[] = [$size, $qty];
            }

            $stickers[] = [
                ['Костюм для девочки'],
                [''],
                ['Арт:', $modelName],
                ['Цвет:', $color],
                ['Размер', 'Количество'],
                ...$sizeRows,
                [''],
                ['Нетто(кг)', 'Брутто(кг)'],
                [round($netto, 2), round($brutto, 2)],
                [''],
            ];
        }

//
//        $summaryList[] = [
//            '', '', '',
//            array_sum(array_column($summaryList, 3)),
//            array_sum(array_column($summaryList, 4)),
//            array_sum(array_column($summaryList, 5)),
//            array_sum(array_column($summaryList, 6)),
//        ];

        return Excel::download(new PackingListExport($data, $summaryList), 'packing_list.xlsx');

//        $timestamp = now()->timestamp;
//        $unique = \Illuminate\Support\Str::random(6);
//        $fileName = "packing_result_{$timestamp}_{$unique}.zip";
//
//// Bu nom asosida job ichida zip saqlanadi
//        $jobPath = "exports/temp_{$timestamp}_{$unique}"; // jobga parameter sifatida kerak bo‘ladi
//
//        dispatch(new PackageExportJob($data, $summaryList, $stickers, $fileName));
//
//        $url = asset("storage/exports/{$fileName}");
//
//        return response()->json([
//            'status' => 'processing',
//            'message' => 'Fayllar tayyorlanmoqda. Tez orada yuklab olish mumkin.',
//            'url' => $url
//        ]);
    }

}

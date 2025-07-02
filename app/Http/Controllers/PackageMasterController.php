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

    public function packageStore(Request $request): BinaryFileResponse|JsonResponse
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
        $imagePath = $orders->first()?->contragent->image ?? null;
        $absolutePath = public_path($imagePath);
        $submodelName = $orders->first()?->orderModel?->submodels->first()?->submodel?->name ?? 'Submodel nomi yo‘q';

        $colorMap = [];

        foreach ($validated['sizes'] as $sizeItem) {
            $sizeId = $sizeItem['size_id'];
            $capacity = $sizeItem['capacity'];
            $bruttoKg = $sizeItem['kg'] ?? 0;
            $nettoKg = round($bruttoKg - 1.4, 2);

            $sizeName = OrderSize::find($sizeId)?->size->name ?? '---';

            foreach ($sizeItem['colors'] as $colorItem) {
                foreach ($colorItem as $colorName => $qty) {
                    $colorMap[$colorName][] = [
                        'size_name' => $sizeName,
                        'qty' => $qty,
                        'capacity' => $capacity,
                        'netto' => $nettoKg,
                        'brutto' => $bruttoKg,
                    ];
                }
            }
        }

        $data = [];
        $summaryList = [
            ['№', 'Артикул', 'Комбинезон', 'Коробка (шт)', 'Обший (шт)', 'Нетто (кг)', 'Брутто (кг)']
        ];

        $totalPacks = $totalQtyAll = $totalNetto = $totalBrutto = 0;
        $index = 1;
        $stickers = [];

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
                    // Packing fayl
                    $data[] = ['', "Артикул: $modelName", '', '', '', '', '', '', ''];
                    $data[] = [$index, "Цвет: $color", $sizeName, $customerName, $packNo, 1, $capacity, $item['netto'],  $item['brutto']];
                    $data[] = ['', "Юбка для девочки", '', '', '', '', '', '', ''];

                    // Box sticker fayl
                    $stickers[] = [
                        ['Размер', 'Количество'],
                        [$sizeName, $capacity],
                        [$item['netto'], $item['brutto']],
                        'color' => $color,
                        'model' => $modelName,
                    ];

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
                $data[] = [$index, "Цвет: $color", $leftovers[0]['size_name'] ?? '', $customerName, $packNo, 1, $leftovers[0]['qty'] ?? '', '', ''];
                $data[] = ['', "Юбка для девочки", $leftovers[1]['size_name'] ?? '', '', '', '', $leftovers[1]['qty'] ?? '', '', ''];

                $sizes = [];

                if (isset($leftovers[0])) $sizes[] = [$leftovers[0]['size_name'], $leftovers[0]['qty']];
                if (isset($leftovers[1])) $sizes[] = [$leftovers[1]['size_name'], $leftovers[1]['qty']];

                $qtySum = array_sum(array_column($sizes, 1));

                $stickers[] = [
                    ['Размер', 'Количество'],
                    ...$sizes,
                    [round($qtySum * 1.45, 2), round($qtySum * 1.62, 2)],
                    'color' => $color,
                    'model' => $modelName,
                ];

                $index++;
                $packCount++;
                $totalQty += $qtySum;
                $netto += round($qtySum * 1.45, 2);
                $brutto += round($qtySum * 1.62, 2);
            }

            // Summary hisob
            $totalPacks += $packCount;
            $totalQtyAll += $totalQty;
            $totalNetto += $netto;
            $totalBrutto += $brutto;
        }

        $summaryList[] = [
            1,
            $modelName,
            'Комбинезон для девочки',
            $totalPacks,
            $totalQtyAll,
            round($totalNetto, 2),
            round($totalBrutto, 2),
        ];

        // Fayl yaratish
        $timestamp = now()->timestamp;
        $unique = \Illuminate\Support\Str::random(6);
        $fileName = "packing_result_{$timestamp}_{$unique}.zip";
        $jobPath = "exports/temp_{$timestamp}_{$unique}";

        dispatch(new PackageExportJob($data, $summaryList, $stickers, $fileName, $absolutePath, $submodelName, $modelName));

        $url = asset("storage/exports/{$fileName}");

        return response()->json([
            'status' => 'processing',
            'message' => 'Fayllar tayyorlanmoqda. Tez orada yuklab olish mumkin.',
            'url' => $url
        ]);
    }

}

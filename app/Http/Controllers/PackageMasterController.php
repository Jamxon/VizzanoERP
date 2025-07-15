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
use Illuminate\Support\Str;
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


    public function packageStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'sizes' => 'required|array',
        ]);

        $orders = Order::with(['orderModel.model', 'contragent'])->whereIn('id', $validated['orders'])->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Buyurtmalar topilmadi'], 404);
        }

        $modelName = $orders->first()?->orderModel?->model->name ?? 'Model nomi yoq';
        $customerName = $orders->first()?->contragent->name ?? 'Buyurtmachi yoq';
        $imagePath = $orders->first()?->contragent->image ?? null;
        $absolutePath = public_path($imagePath);
        $submodelName = $orders->first()?->orderModel?->submodels->first()?->submodel?->name ?? 'Submodel nomi yoq';
        $orderSizes = $orders->first()?->orderModel?->sizes->map(fn($item) => $item->size->name)->toArray() ?? [];

        $colorMap = [];
        $sizesMap = [];

        foreach ($validated['sizes'] as $sizeItem) {
            $sizeId = $sizeItem['size_id'];
            $capacity = $sizeItem['capacity'];
            $bruttoKg = $sizeItem['kg'] ?? 0;
            $nettoKg = round($bruttoKg - 1.4, 2);

            $sizeName = OrderSize::find($sizeId)?->size->name ?? '---';
            if (!in_array($sizeName, $sizesMap)) $sizesMap[] = $sizeName;

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
        $summaryList = [[
            '№', 'Артикул', 'Комбинезон', 'Коробка (шт)', 'Обший (шт)', 'Нетто (кг)', 'Брутто (кг)'
        ]];

        $totalPacks = $totalQtyAll = $totalNetto = $totalBrutto = 0;
        $stickers = [];
        $allLeftovers = [];

        foreach ($colorMap as $color => $items) {
            $leftovers = [];
            $packCount = 0;
            $totalQty = $netto = $brutto = 0;
            $index = 1;

            foreach ($items as $item) {
                $qty = $item['qty'];
                $sizeName = $item['size_name'];
                $capacity = $item['capacity'];

                while ($qty >= $capacity) {
                    $data[] = ['', "Артикул: $modelName", '', '', '', '', '', '', ''];
                    $data[] = [$index, "Цвет: $color", $sizeName, $customerName, $packCount + 1, 1, $capacity, $item['netto'], $item['brutto']];
                    $data[] = ['', $submodelName, '', '', '', '', '', '', ''];

                    $stickers[] = [
                        [$sizeName, $capacity],
                        [round($item['netto'], 2), round($item['brutto'], 2)],
                        'color' => $color,
                        'model' => $modelName,
                        'orderSizes' => $orderSizes,
                    ];

                    $qty -= $capacity;
                    $packCount++;
                    $index++;
                    $totalQty += $capacity;
                    $netto += $item['netto'];
                    $brutto += $item['brutto'];
                }

                if ($qty > 0) {
                    $leftovers[] = [...$item, 'qty' => $qty, 'color' => $color];
                }
            }

            foreach ($leftovers as $leftover) $allLeftovers[] = $leftover;

            $totalPacks += $packCount;
            $totalQtyAll += $totalQty;
            $totalNetto += $netto;
            $totalBrutto += $brutto;
        }

        $groupedByCapacity = collect($allLeftovers)->groupBy('capacity');

        foreach ($groupedByCapacity as $capacity => $leftoversGroup) {
            $packages = [];
            $current = [];
            $qtySum = 0;

            foreach ($leftoversGroup as $item) {
                if ($qtySum + $item['qty'] <= $capacity) {
                    $current[] = $item;
                    $qtySum += $item['qty'];
                } else {
                    $packages[] = $current;
                    $current = [$item];
                    $qtySum = $item['qty'];
                }
            }

            if (!empty($current)) $packages[] = $current;

            foreach ($packages as $pkg) {
                $totalPacks++;
                $colors = collect($pkg)->pluck('color')->unique()->implode(', ');

                $data[] = ['', "Артикул: $modelName", '', '', '', '', '', '', ''];
                $data[] = [$totalPacks, "Цвет: $colors", $pkg[0]['size_name'], $customerName, $totalPacks, 1, $pkg[0]['qty'], '', ''];

                if (count($pkg) > 1) {
                    foreach (array_slice($pkg, 1) as $row) {
                        $data[] = ['', $submodelName, $row['size_name'], '', '', '', $row['qty'], '', ''];
                    }
                } else {
                    $data[] = ['', $submodelName, '', '', '', '', '', '', ''];
                }

                // Har bir size bo‘yicha qty
                $qtyBySize = collect($pkg)->mapWithKeys(fn($i) => [$i['size_name'] => $i['qty']]);
                $sizes = [];
                $totalQtyLeft = 0;
                foreach ($sizesMap as $sizeName) {
                    $qty = $qtyBySize[$sizeName] ?? '';
                    $sizes[] = [$sizeName, $qty];
                    $totalQtyLeft += is_numeric($qty) ? $qty : 0;
                }

                // Netto va brutto
                $netto = collect($pkg)->sum('netto');
                $brutto = collect($pkg)->sum('brutto');

                $grouped = $this->groupSizesInRows($sizes);
                $grouped[] = [round($netto, 2), round($brutto, 2)];

                // TO‘G‘RILANGAN sticker formati
                $stickers[] = [
                    'sizes' => $grouped,
                    'color' => $colors,
                    'model' => $modelName,
                    'orderSizes' => $orderSizes,
                ];

                // Umumiy statistikaga qo‘shish
                $totalQtyAll += $totalQtyLeft;
                $totalNetto += $netto;
                $totalBrutto += $brutto;
            }
        }

        $summaryList[] = [
            1, $modelName, 'Комбинезон для девочки',
            $totalPacks, $totalQtyAll,
            round($totalNetto, 2), round($totalBrutto, 2),
        ];

        $fileName = 'packing_result_' . now()->timestamp . '_' . Str::random(6) . '.zip';

        dispatch(new PackageExportJob(
            $data, $summaryList, $stickers, $fileName, $absolutePath, $submodelName, $modelName
        ));

        return response()->json([
            'status' => 'processing',
            'message' => 'Fayllar tayyorlanmoqda. Tez orada yuklab olish mumkin.',
            'url' => asset("storage/exports/{$fileName}"),
        ]);
    }

    private function groupSizesInRows(array $sizes): array
    {
        $grouped = [];
        $row = [];

        foreach ($sizes as [$size, $qty]) {
            $row[] = $size;
            $row[] = $qty;
            if (count($row) === 6) {
                $grouped[] = $row;
                $row = [];
            }
        }

        if (!empty($row)) {
            while (count($row) < 6) {
                $row[] = '';
            }
            $grouped[] = $row;
        }

        return $grouped;
    }

}

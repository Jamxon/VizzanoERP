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

    public function packageStore(Request $request)
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
        $orderSizes = $orders->first()?->orderModel?->sizes->size->pluck('name')->toArray() ?? [];
        $colorMap = [];
        $sizesMap = [];

        foreach ($validated['sizes'] as $sizeItem) {
            $sizeId = $sizeItem['size_id'];
            $capacity = $sizeItem['capacity'];
            $bruttoKg = $sizeItem['kg'] ?? 0;
            $nettoKg = round($bruttoKg - 1.4, 2);

            $sizeName = OrderSize::find($sizeId)?->size->name ?? '---';

            if (!in_array($sizeName, $sizesMap)) {
                $sizesMap[] = $sizeName;
            }

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

            $index = 1; // Har rang uchun index boshlanadi

            foreach ($items as $item) {
                $qty = $item['qty'];
                $sizeName = $item['size_name'];
                $capacity = $item['capacity'];

                while ($qty >= $capacity) {
                    // Packing faylga qo'shish
                    $data[] = ['', "Артикул: $modelName", '', '', '', '', '', '', ''];
                    $data[] = [$index, "Цвет: $color", $sizeName, $customerName, $packCount + 1, 1, $capacity, $item['netto'],  $item['brutto']];
                    $data[] = ['', "Юбка для девочки", '', '', '', '', '', '', ''];

                    // Box sticker uchun shu paketdagi faqat bitta o'lcham va miqdor
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
                    $leftovers[] = ['size_name' => $sizeName, 'qty' => $qty, 'netto' => $item['netto'], 'brutto' => $item['brutto']];
                }
            }

            if (count($leftovers)) {
                // Packing faylga leftovers qo'shish
                $data[] = ['', "Артикул: $modelName", '', '', '', '', '', '', ''];
                $data[] = [$index, "Цвет: $color", $leftovers[0]['size_name'] ?? '', $customerName, $packCount + 1, 1, $leftovers[0]['qty'] ?? '', '', ''];
                $data[] = ['', "Юбка для девочки", $leftovers[1]['size_name'] ?? '', '', '', '', $leftovers[1]['qty'] ?? '', '', ''];

                // Size'lar bo'yicha qty map tuzib olamiz
                $qtyBySize = collect($leftovers)->mapWithKeys(fn($left) => [
                    $left['size_name'] => $left['qty']
                ])->toArray();

                $sizes = [];
                $totalQtyLeft = 0;

                // Har bir sizesMap elementiga qarab qty ni olamiz yoki '' beramiz
                foreach ($sizesMap as $sizeName) {
                    $qty = $qtyBySize[$sizeName] ?? '';
                    $sizes[] = [$sizeName, $qty];
                    $totalQtyLeft += is_numeric($qty) ? $qty : 0;
                }

                // Netto va Brutto yig'ish
                $totalNettoLeft = collect($leftovers)->sum('netto');
                $totalBruttoLeft = collect($leftovers)->sum('brutto');

                // Sticker massivini tayyorlash
                $sizesRows = [['Размер', 'Количество'], ...$sizes, [round($totalNettoLeft, 2), round($totalBruttoLeft, 2)]];

                $stickers[] = [
                    ...$sizesRows,
                    'color' => $color,
                    'model' => $modelName,
                    'orderSizes' => $orderSizes,
                ];

                $index++;
                $packCount++;
                $totalQty += $totalQtyLeft;
                $netto += $totalNettoLeft;
                $brutto += $totalBruttoLeft;
            }

            // Umumiy yig'ish
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

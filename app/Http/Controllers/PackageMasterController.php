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
use Illuminate\Support\Facades\Cache;
use App\Models\Bonus;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;

class PackageMasterController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = $request->input('search', '');
        $cacheKey = "orders_" . auth()->user()->employee->branch_id . "_" . md5($search);

        // Cache bilan optimizatsiya
        $orders = Cache::remember($cacheKey, 300, function () use ($search) {
            return Order::where('branch_id', auth()->user()->employee->branch_id)
                ->when($search, function ($query, $search) {
                    $query->whereHas('orderModel.model', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
                })
                ->with([
                    'orderModel.model:id,name',
                    'orderModel.material:id,name',
                    'orderModel.submodels.submodel:id,name',
                    'orderModel.sizes.size:id,name'
                ])
                ->select('id', 'branch_id', 'contragent_id')
                ->get();
        });

        return response()->json($orders);
    }

    public function showOrder(Order $order): \Illuminate\Http\JsonResponse
    {
        $order->load([
            'packageOutcomes',
            'orderModel.model:id,name',
            'orderModel.material:id,name',
            'orderModel.submodels.submodel:id,name',
            'orderModel.sizes.size:id,name',
        ]);

        return response()->json($order);
    }

    public function packageStore(Request $request): \Illuminate\Http\JsonResponse
    {
        set_time_limit(120); // Unlimited for background job
        ini_set('memory_limit', '2G');

        $validated = $request->validate([
            'orders' => 'required|array',
            'sizes' => 'required|array',
        ]);

        // Ma'lumotlarni optimizatsiyalangan tarzda olish
        $orders = Order::with(['orderModel.model:id,name', 'contragent:id,name,image'])
            ->whereIn('id', $validated['orders'])
            ->get(['id', 'contragent_id']);

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Buyurtmalar topilmadi'], 404);
        }

        // Asosiy ma'lumotlarni olish
        $firstOrder = $orders->first();
        $modelName = $firstOrder?->orderModel?->model->name ?? 'Model nomi yoq';
        $customerName = $firstOrder?->contragent->name ?? 'Buyurtmachi yoq';
        $imagePath = $firstOrder?->contragent->image;
        $absolutePath = $imagePath ? public_path($imagePath) : null;

        // Submodel va sizes'larni optimizatsiyalangan tarzda olish
        [$submodelName, $orderSizes] = $this->getModelDetails($firstOrder);

        // Ma'lumotlarni qayta ishlash
        [$colorMap, $sizesMap] = $this->processValidatedSizes($validated['sizes']);

        // Paketlash ma'lumotlarini yaratish
        [$data, $summaryList, $stickers, $totals] = $this->createPackagingData(
            $colorMap, $sizesMap, $modelName, $customerName, $submodelName, $orderSizes
        );

        // Job'ni dispatch qilish
        $this->dispatchExportJob($data, $summaryList, $stickers, $modelName, $absolutePath, $submodelName, $totals);

        $fileName = $this->generateFileName();
        $url = asset("storage/exports/{$fileName}");

        return response()->json([
            'status' => 'processing',
            'message' => 'Fayllar tayyorlanmoqda. Tez orada yuklab olish mumkin.',
            'url' => $url
        ]);
    }

    private function getModelDetails($firstOrder): array
    {
        $submodelName = Cache::remember(
            "submodel_{$firstOrder->id}",
            600,
            fn() => $firstOrder?->orderModel?->submodels->first()?->submodel?->name ?? 'Submodel nomi yoq'
        );

        $orderSizes = Cache::remember(
            "order_sizes_{$firstOrder->id}",
            600,
            fn() => $firstOrder?->orderModel?->sizes->map(fn($item) => $item->size->name)->toArray() ?? []
        );

        return [$submodelName, $orderSizes];
    }

    private function processValidatedSizes(array $validatedSizes): array
    {
        $colorMap = [];
        $sizesMap = [];

        // Size ID'larni oldindan yuklash
        $sizeIds = collect($validatedSizes)->pluck('size_id')->unique();
        $orderSizes = OrderSize::whereIn('id', $sizeIds)
            ->with('size:id,name')
            ->get()
            ->keyBy('id');

        foreach ($validatedSizes as $sizeItem) {
            $sizeId = $sizeItem['size_id'];
            $capacity = $sizeItem['capacity'];
            $bruttoKg = $sizeItem['kg'] ?? 0;
            $nettoKg = round($bruttoKg - 1.4, 2);

            $sizeName = $orderSizes[$sizeId]?->size->name ?? '---';

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

        return [$colorMap, $sizesMap];
    }

    private function createPackagingData(array $colorMap, array $sizesMap, string $modelName, string $customerName, string $submodelName, array $orderSizes): array
    {
        $data = [];
        $summaryList = [
            ['№', 'Артикул', 'Комбинезон', 'Коробка (шт)', 'Обший (шт)', 'Нетто (кг)', 'Брутто (кг)']
        ];

        $totalPacks = $totalQtyAll = $totalNetto = $totalBrutto = 0;
        $stickers = [];
        $allLeftovers = [];

        // Ranglar bo'yicha qayta ishlash (optimizatsiyalangan)
        foreach ($colorMap as $color => $items) {
            [$packData, $packStickers, $leftovers, $packTotals] = $this->processColorItems(
                $items, $color, $modelName, $customerName, $submodelName, $totalPacks
            );

            $data = array_merge($data, $packData);
            $stickers = array_merge($stickers, $packStickers);
            $allLeftovers = array_merge($allLeftovers, $leftovers);

            $totalPacks += $packTotals['packs'];
            $totalQtyAll += $packTotals['qty'];
            $totalNetto += $packTotals['netto'];
            $totalBrutto += $packTotals['brutto'];
        }

        // Qoldiqlarni qayta ishlash
        [$leftoverData, $leftoverStickers, $leftoverTotals] = $this->processLeftovers(
            $allLeftovers, $sizesMap, $modelName, $customerName, $submodelName, $totalPacks, $orderSizes
        );

        $data = array_merge($data, $leftoverData);
        $stickers = array_merge($stickers, $leftoverStickers);

        $totalPacks += $leftoverTotals['packs'];
        $totalQtyAll += $leftoverTotals['qty'];
        $totalNetto += $leftoverTotals['netto'];
        $totalBrutto += $leftoverTotals['brutto'];

        $summaryList[] = [
            1, $modelName, 'Комбинезон для девочки',
            $totalPacks, $totalQtyAll,
            round($totalNetto, 2), round($totalBrutto, 2),
        ];

        return [$data, $summaryList, $stickers, compact('totalPacks', 'totalQtyAll', 'totalNetto', 'totalBrutto')];
    }

    private function processColorItems(array $items, string $color, string $modelName, string $customerName, string $submodelName, int $currentPackCount): array
    {
        $data = [];
        $stickers = [];
        $leftovers = [];
        $packCount = 0;
        $totalQty = 0;
        $netto = 0;
        $brutto = 0;
        $index = 1;

        foreach ($items as $item) {
            $qty = $item['qty'];
            $sizeName = $item['size_name'];
            $capacity = $item['capacity'];

            while ($qty >= $capacity) {
                // Paket ma'lumotlarini qo'shish
                $data[] = ['', "Артикул: $modelName", '', '', '', '', '', '', ''];
                $data[] = [$index, "Цвет: $color", $sizeName, $customerName, $packCount + 1, 1, $capacity, $item['netto'], $item['brutto']];
                $data[] = ['', $submodelName, '', '', '', '', '', '', ''];

                // Sticker qo'shish
                $stickers[] = [
                    [$sizeName, $capacity],
                    [round((float)($item['netto'] ?? 0), 2), round((float)($item['brutto'] ?? 0), 2)],
                    'color' => $color,
                    'model' => $modelName,
                    'orderSizes' => [], // Keyinroq to'ldiriladi
                ];

                $qty -= $capacity;
                $packCount++;
                $index++;
                $totalQty += $capacity;
                $netto += (float)($item['netto'] ?? 0);
                $brutto += (float)($item['brutto'] ?? 0);
            }

            if ($qty > 0) {
                $leftovers[] = [
                    'size_name' => $sizeName,
                    'qty' => $qty,
                    'netto' => $item['netto'],
                    'brutto' => $item['brutto'],
                    'capacity' => $capacity,
                    'color' => $color
                ];
            }
        }

        return [
            $data,
            $stickers,
            $leftovers,
            ['packs' => $packCount, 'qty' => $totalQty, 'netto' => $netto, 'brutto' => $brutto]
        ];
    }

    private function processLeftovers(array $allLeftovers, array $sizesMap, string $modelName, string $customerName, string $submodelName, int $currentPackCount, array $orderSizes): array
    {
        $data = [];
        $stickers = [];
        $totalPacks = 0;
        $totalQty = 0;
        $totalNetto = 0;
        $totalBrutto = 0;

        // Collection'dan foydalanib optimizatsiya qilish
        $groupedByCapacity = collect($allLeftovers)->groupBy('capacity');

        foreach ($groupedByCapacity as $capacity => $leftoversGroup) {
            $leftoverPackages = $this->createLeftoverPackages($leftoversGroup, $capacity);

            foreach ($leftoverPackages as $package) {
                $totalPacks++;
                $packIndex = $currentPackCount + $totalPacks;

                // Ranglarni birlashtirish
                $colors = collect($package)->pluck('color')->unique()->implode(', ');

                // Data qo'shish
                $data[] = ['', "Артикул: $modelName", '', '', '', '', '', '', ''];
                $data[] = [$packIndex, "Цвет: $colors", $package[0]['size_name'], $customerName, $packIndex, 1, $package[0]['qty'], '', ''];

                if (count($package) > 1) {
                    for ($i = 1; $i < count($package); $i++) {
                        $data[] = ['', $submodelName, $package[$i]['size_name'], '', '', '', $package[$i]['qty'], '', ''];
                    }
                } else {
                    $data[] = ['', $submodelName, '', '', '', '', '', '', ''];
                }

                // Sticker yaratish
                [$stickerData, $packageTotals] = $this->createLeftoverSticker($package, $sizesMap, $colors, $modelName, $orderSizes);
                $stickers[] = $stickerData;

                $totalQty += $packageTotals['qty'];
                $totalNetto += $packageTotals['netto'];
                $totalBrutto += $packageTotals['brutto'];
            }
        }

        return [
            $data,
            $stickers,
            ['packs' => $totalPacks, 'qty' => $totalQty, 'netto' => $totalNetto, 'brutto' => $totalBrutto]
        ];
    }

    private function createLeftoverPackages(Collection $leftoversGroup, int $capacity): array
    {
        $leftoverPackages = [];
        $currentPackage = [];
        $currentQty = 0;

        foreach ($leftoversGroup as $leftover) {
            if ($currentQty + $leftover['qty'] <= $capacity) {
                $currentPackage[] = $leftover;
                $currentQty += $leftover['qty'];
            } else {
                if (!empty($currentPackage)) {
                    $leftoverPackages[] = $currentPackage;
                }
                $currentPackage = [$leftover];
                $currentQty = $leftover['qty'];
            }
        }

        if (!empty($currentPackage)) {
            $leftoverPackages[] = $currentPackage;
        }

        return $leftoverPackages;
    }

    private function createLeftoverSticker(array $package, array $sizesMap, string $colors, string $modelName, array $orderSizes): array
    {
        // Size'lar bo'yicha qty map
        $qtyBySize = collect($package)->mapWithKeys(fn($item) => [
            $item['size_name'] => $item['qty']
        ])->toArray();

        $sizes = [];
        $totalQtyLeft = 0;

        foreach ($sizesMap as $sizeName) {
            $qty = $qtyBySize[$sizeName] ?? '';
            $sizes[] = [$sizeName, $qty];
            $totalQtyLeft += is_numeric($qty) ? $qty : 0;
        }

        // Netto va Brutto yig'ish
        $totalNettoLeft = collect($package)->sum(fn($x) => (float)($x['netto'] ?? 0));
        $totalBruttoLeft = collect($package)->sum(fn($x) => (float)($x['brutto'] ?? 0));

        // Sticker ma'lumotlari
        $sizesRows = [
            ['Размер', 'Количество'],
            ...$sizes,
            [round($totalNettoLeft, 2), round($totalBruttoLeft, 2)]
        ];

        $stickerData = [
            ...$sizesRows,
            'color' => $colors,
            'model' => $modelName,
            'orderSizes' => $orderSizes,
        ];

        return [
            $stickerData,
            ['qty' => $totalQtyLeft, 'netto' => $totalNettoLeft, 'brutto' => $totalBruttoLeft]
        ];
    }

    private function dispatchExportJob(array $data, array $summaryList, array $stickers, string $modelName, ?string $absolutePath, string $submodelName, array $totals): void
    {
        $fileName = $this->generateFileName();

        dispatch(new PackageExportJob(
            $data,
            $summaryList,
            $stickers,
            $fileName,
            $absolutePath,
            $submodelName,
            $modelName
        ))->onQueue('high'); // Yuqori prioritet queue
    }

    private function generateFileName(): string
    {
        $timestamp = now()->timestamp;
        $unique = \Illuminate\Support\Str::random(6);
        return "packing_result_{$timestamp}_{$unique}.zip";
    }
}
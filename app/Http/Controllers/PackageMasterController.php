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

        $modelName = $orders->first()?->orderModel?->model->name ?? 'Model nomi yo\'q';
        $customerName = $orders->first()?->contragent->name ?? 'Buyurtmachi yo\'q';

        $colorMap = [];
        $summaryMap = [];

        foreach ($validated['sizes'] as $sizeItem) {
            $sizeId = $sizeItem['size_id'];
            $capacity = $sizeItem['capacity'];
            $bruttoKg = $sizeItem['kg'] ?? 0;
            $nettoKg = round($bruttoKg - 1.4, 2);

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

        // Packing list data (oldingi kodingiz)
        $data = [];
        $index = 1;
        $summaryList = [
            ['№', 'Артикул', 'Коллекция зима Комбинезон', 'Коробка (шт)', 'Обший (шт)', 'Нетто (кг)', 'Брутто (кг)']
        ];

        $totalPacks = 0;
        $totalQtyAll = 0;
        $totalNetto = 0;
        $totalBrutto = 0;

        // ... (packing list yaratish kodi - oldingi kodingiz)

        // Box stickers yaratish (yaxshilangan)
        $stickers = [];
        $stickerNumber = 66; // Boshlang'ich raqam

        foreach ($colorMap as $color => $items) {
            $totalQtyBySize = [];
            $totalNetto = 0;
            $totalBrutto = 0;

            // Har bir rang uchun size bo'yicha miqdorlarni hisoblash
            foreach ($items as $item) {
                $sizeName = $item['size_name'];
                $qty = $item['qty'];

                if (!isset($totalQtyBySize[$sizeName])) {
                    $totalQtyBySize[$sizeName] = 0;
                }
                $totalQtyBySize[$sizeName] += $qty;

                // Umumiy og'irlik hisoblash (miqdorga ko'ra)
                $totalNetto += ($qty * 0.145); // 145g per item
                $totalBrutto += ($qty * 0.165); // 165g per item
            }

            // Sticker ma'lumotlarini yaratish
            $stickerData = [];

            // Header
            $stickerData[] = ['🔲NIKASTYLE', $stickerNumber];
            $stickerData[] = ['Костюм для девочки', ''];
            $stickerData[] = ['Арт:', $modelName];
            $stickerData[] = ['Цвет:', $color];
            $stickerData[] = ['Размер', 'Количество'];

            // Size va quantity qatorlari
            foreach ($totalQtyBySize as $size => $qty) {
                if ($qty > 0) {
                    $stickerData[] = [$size, $qty];
                }
            }

            // Bo'sh qator
            $stickerData[] = ['', ''];

            // Og'irlik
            $stickerData[] = ['Нетто(кг)', 'Брутто(кг)'];
            $stickerData[] = [round($totalNetto, 2), round($totalBrutto, 2)];

            $stickers[] = $stickerData;
            $stickerNumber++;
        }

        // Job dispatch
        $timestamp = now()->timestamp;
        $unique = \Illuminate\Support\Str::random(6);
        $fileName = "packing_result_{$timestamp}_{$unique}.zip";

        dispatch(new PackageExportJob($data, $summaryList, $stickers, $fileName));

        $url = asset("storage/exports/{$fileName}");

        return response()->json([
            'status' => 'processing',
            'message' => 'Fayllar tayyorlanmoqda. Tez orada yuklab olish mumkin.',
            'url' => $url
        ]);
    }

}

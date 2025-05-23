<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetOrderCutResource;
use App\Http\Resources\GetSpecificationResource;
use App\Http\Resources\showOrderCuttingMasterResource;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderCut;
use App\Models\OrderModel;
use App\Models\OrderPrintingTimes;
use App\Models\OrderSize;
use App\Models\OrderSubModel;
use App\Models\Outcome;
use App\Models\OutcomeItemModelDistrubition;
use App\Models\Stok;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CuttingMasterController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $status = $request->input('status');
        $orders = Order::where('status', $status)
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->whereDate('start_date', '<=', now()->addDays(15)->toDateString())
            ->orderBy('start_date', 'asc')
            ->with(
                'instructions',
                'orderModel.model',
                'orderModel.material',
                'orderModel.submodels',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
                'orderPrintingTime',
                'orderPrintingTime.user'
            )
            ->get();

        return response()->json($orders);
    }

    public function sendToConstructor(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'planned_time' => 'required|date',
            'comment' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $order = Order::find($data['order_id']);
            $oldStatus = $order->status;

            $order->update([
                'status' => 'printing'
            ]);

            $orderPrintingTime = OrderPrintingTimes::create([
                'order_id' => $data['order_id'],
                'planned_time' => $data['planned_time'],
                'status' => 'printing',
                'comment' => $data['comment'],
                'user_id' => auth()->user()->id
            ]);

            // Add log entry
            Log::add(
                auth()->user()->id,
                "Buyurtma konstruktorga yuborildi (Order ID: {$data['order_id']})",
                'send',
                ['old_data' => $oldStatus, 'order_id' => $data['order_id']],
                ['new_data' => 'printing', 'planned_time' => $data['planned_time'], 'comment' => $data['comment']]
            );

            DB::commit();
            return response()->json($orderPrintingTime);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getCompletedItems(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderId = $request->input('order_id');

        $orderModelIds = OrderModel::where('order_id', $orderId)->pluck('id')->toArray();

        $outcomeItemModelDistribution = OutcomeItemModelDistrubition::whereIn('model_id', $orderModelIds)
            ->whereHas('outcomeItem.outcome', function ($query) {
                $query->where('outcome_type', 'production')
                    ->whereHas('productionOutcome', function ($query) {
                        $query->where('received_by_id', auth()->id());
                    });
            })
            ->with([
                'outcomeItem.outcome.items.product:id,name',
                'orderModel:id,model_id,order_id',
                'orderModel.model:id,name',
                'orderModel.order:id,start_date',
                'orderModel.order'
            ])
            ->get();



        return response()->json($outcomeItemModelDistribution);
    }

    public function showOrder(Order $order): \Illuminate\Http\JsonResponse
    {
        $order->load([
            'orderModel.model',
            'orderModel.material',
            'orderModel.submodels',
            'orderModel.sizes.size',
            'orderModel.submodels.submodel',
            'orderModel.submodels.specificationCategories',
        ]);

        return response()->json($order);
    }

    public function getSpecificationByOrderId($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::find($id);

        $order->load([
            'orderModel.submodels.specificationCategories',
            'orderModel.submodels.specificationCategories.specifications'
        ]);

        $resource = new GetSpecificationResource($order);

        return response()->json($resource);
    }

    public function markAsCutAndExportMultiplePdfs(Request $request): \Illuminate\Http\Response
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'quantity' => 'required|integer|min:1',
            'submodel_id' => 'required|integer|exists:order_sub_models,id',
            'size_id' => 'required|integer|exists:order_sizes,id',
            'box_capacity' => 'required|integer|min:1',
        ]);

        // Memory va time limitlarini sozlash
        ini_set('memory_limit', '2G');
        set_time_limit(0);

        // Ma'lumotlarni olish
        $submodel = OrderSubmodel::with([
            'orderModel.order:id,name',
            'orderModel.model:id,name',
            'submodel:id,name',
            'tarificationCategories.tarifications.razryad:id,name',
            'tarificationCategories.tarifications.typewriter:id,name',
            'tarificationCategories.tarifications.employee:id,name'
        ])->findOrFail($data['submodel_id']);

        $sizeName = OrderSize::find($data['size_id'])->name ?? '-';
        $totalQuantity = $data['quantity'];
        $capacity = $data['box_capacity'];
        $boxes = intdiv($totalQuantity, $capacity);
        $remainder = $totalQuantity % $capacity;

        // Barcha quti ma'lumotlarini tayyorlash
        $pdfBoxes = [];

        // To'liq qutilar
        for ($i = 0; $i < $boxes; $i++) {
            $pdfBoxes[] = [
                'box_number' => $i + 1,
                'quantity' => $capacity,
                'submodel' => $submodel,
                'size' => $sizeName,
            ];
        }

        // Qolgan quantity
        if ($remainder > 0) {
            $pdfBoxes[] = [
                'box_number' => $boxes + 1,
                'quantity' => $remainder,
                'submodel' => $submodel,
                'size' => $sizeName,
            ];
        }

        // Bitta PDF yaratish
        $fileName = 'kesish_tarifikatsiyasi_' . now()->format('Ymd_His') . '.pdf';

        $pdf = Pdf::loadView('pdf.tarifications-pdf', [
            'boxes' => $pdfBoxes,
            'totalQuantity' => $totalQuantity,
            'totalBoxes' => count($pdfBoxes),
            'submodel' => $submodel,
            'size' => $sizeName,
            'order_id' => $data['order_id'],
        ])
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
                'dpi' => 96,
                'enable_php' => false
            ]);

        return $pdf->download($fileName);
    }

    public function getCuts($id): \Illuminate\Http\JsonResponse
    {
        $cuts = OrderCut::where('order_id', $id)
            ->with([
                'user:id,name',
                'submodel.submodel',
                'size.size'
            ])
            ->get();

        return response()->json($cuts);
    }

    public function finishCutting($id): \Illuminate\Http\JsonResponse
    {
        DB::beginTransaction();
        try {
            $order = Order::find($id);
            $oldStatus = $order->status;

            $order->update([
                'status' => 'pending'
            ]);

            $order->orderPrintingTime->update([
                'status' => 'completed'
            ]);

            // Add log entry
            Log::add(
                auth()->user()->id,
                "Buyurtmani kesish yakunlandi (Order ID: $id)",
                'cut',
                ['old_data' => $oldStatus, 'order_id' => $id],
                ['new_data' => 'pending']
            );

            DB::commit();
            return response()->json([
                'message' => 'Order cutting finished'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
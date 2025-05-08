<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderPrintingTime;
use App\Http\Resources\ShowOrderPrintingTime;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderPrintingTimes;
use Illuminate\Http\Request;

class ConstructorController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('branch_id', auth()->user()->employee->branch_id)
            ->whereIn('status', ['cutting', 'printing'])
            ->with([
                'orderModel',
                'orderModel.submodels.specificationCategories.specifications',
                'orderModel.model',
                'orderModel.material',
                'orderPrintingTime',
            ])
            ->get();

        $resource = OrderPrintingTime::collection($orders);

        return response()->json($resource);
    }

    public function showOrder($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::where('id', $id)
        ->where('branch_id', auth()->user()->employee->branch_id)
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $resource = new ShowOrderPrintingTime($order);  // Individual obyekt uchun

        return response()->json($resource);
    }

    public function sendToCuttingMaster($id): \Illuminate\Http\JsonResponse
    {
        $orderPrintingTime = OrderPrintingTimes::find($id);

        $order = Order::find($orderPrintingTime->order_id);

        $order->update([
            'status' => 'cutting'
        ]);

        $orderPrintingTime->update([
            'status' => 'cutting',
            'actual_time' => now(),
            'user_id' => auth()->user()->id
        ]);


        //add log
        Log::add(
            auth()->user()->id,
            "Buyurtmani kesishga jo'natildi",
            'constructor',
            ['old_data' => $orderPrintingTime->status, 'order_id' => $order->id],
            ['new_data' => 'cutting']
        );

        return response()->json($orderPrintingTime);
    }

}
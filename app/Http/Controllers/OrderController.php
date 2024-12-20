<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderModel;
use App\Models\OrderSubModel;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::orderBy('updated_at', 'asc')->get();
        return response()->json($orders);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'quantity' => 'required|integer',
            'models' => 'required|array',
            'models.*.id' => 'required|integer|exists:models,id',
            'models.*.submodel.id' => 'nullable|integer|exists:sub_models,id',
            'models.*.size.id' => 'nullable|integer|exists:sizes,id',
            'models.*.model_color.id' => 'nullable|integer|exists:model_colors,id',
            'models.*.quantity' => 'required|integer',
        ]);

        $order = Order::create([
            'name' => $request->name,
            'quantity' => $request->quantity,
            'status' => "inactive",
            'start_date' => $request->start_date ?? null,
            'end_date' => $request->end_date ?? null,
        ]);

        foreach ($request->models as $model) {
            if (!OrderModel::where('order_id', $order->id)->where('model_id', $model['id'])->exists()) {
                OrderModel::create([
                    'order_id' => $order->id,
                    'model_id' => $model['id'],
                ]);
            }

            $orderModel = OrderModel::where('order_id', $order->id)->where('model_id', $model['id'])->first();

            OrderSubModel::create([
                'order_model_id' => $orderModel->id,
                'submodel_id' => $model['submodel']['id'],
                'size_id' => $model['size']['id'],
                'model_color_id' => $model['model_color']['id'],
                'quantity' => $model['quantity'],
            ]);
        }

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order,
        ], 201);
    }

    public function update(Request $request, Order $order)
    {
        $order->update($request->all());

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order,
        ]);
    }

    public function delete(Order $order)
    {
        $order->delete();
        return response()->json([
            'message' => 'Order deleted successfully',
        ]);
    }

    public function changeOrderStatus(Request $request)
    {
        $request->validate([
            'status' => 'required',
            'order_id' => 'required',
        ]);

        $order = Order::find($request->order_id);
        $order->status = $request->status;
        $order->save();

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order,
        ]);
    }
}
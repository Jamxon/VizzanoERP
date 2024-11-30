<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetOrdersResource;
use App\Models\Order;
use App\Models\OrderModel;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::all();
        $resource = GetOrdersResource::collection($orders);
        return response()->json($resource);
    }
    public function store(Request $request)
    {
        $order = Order::create([
            'name' => $request->name,
            'quantity' => $request->quantity,
            'status' => "active",
        ]);

        if (count($request->models) > 1) {
            foreach ($request->models as $model) {
                OrderModel::create([
                    'order_id' => $order->id,
                    'model_id' => $model['id'],
                    'quantity' => $model['quantity'],
                ]);
            }
        } else {
            OrderModel::create([
                'order_id' => $order->id,
                'model_id' => $request->model['id'],
                'quantity' => $request->model['quantity'],
            ]);
        }
            if ($order) {
                return response()->json([
                    'message' => 'Order created successfully',
                    'order' => $order,
                ]);
            }else{
                return response()->json([
                    'message' => 'Order not created',
                    'error' => $order->errors(),
                ]);
            }
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'name' => 'required',
            'quantity' => 'required',
            'status' => 'required',
        ]);

        $order->name = $request->name;
        $order->quantity = $request->quantity;
        $order->status = $request->status;
        $order->save();

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

<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShowOrderResource;
use App\Models\Models;
use App\Models\Order;
use App\Models\OrderModel;
use App\Models\OrderRecipes;
use App\Models\OrderSubModel;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::orderBy('updated_at', 'asc')->get();
        return response()->json($orders);
    }

    public function show(Order $order)
    {
        $resource = new ShowOrderResource($order);

        return response()->json($resource);
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

        $user = auth()->user();

        $order = Order::create([
            'name' => $request->name,
            'quantity' => $request->quantity,
            'status' => "inactive",
            'start_date' => $request->start_date ?? null,
            'end_date' => $request->end_date ?? null,
            'rasxod'  => $request->rasxod ?? 0,
            'branch_id' => $user->employee->branch_id,
        ]);

        foreach ($request->models as $model) {
            if (!OrderModel::where('order_id', $order->id)->where('model_id', $model['id'])->exists()) {
                $modelRasxod = Models::find($model['id']);
                OrderModel::create([
                    'order_id' => $order->id,
                    'model_id' => $model['id'],
                    'rasxod' => $modelRasxod->rasxod ?? 0,
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

           $recipes = Recipe::where('model_color_id', $model['model_color']['id'])
                        ->where('size_id', $model['size']['id'])
                        ->get();

           foreach ($recipes as $recipe) {
               OrderRecipes::create([
                   'order_id' => $order->id,
                   'item_id' => $recipe->item_id,
                   'model_color_id' => $recipe->model_color_id,
                   'quantity' => $recipe->quantity,
                   'size_id' => $recipe->size_id,
               ]);
           }

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

    public function getOrderWithPlan()
    {
        return now()->addDays(3);
        $orders = Order::where('status', 'active')
            ->where('start_date', '<=', now())
            ->orWhere(function ($query) {
                $query->where('status', 'active')
                ->whereBetween('start_date', [now(), now()->addDays(3)]);
            })
            ->orderBy('start_date', 'asc')
            ->get();

        return $orders;
    }

}
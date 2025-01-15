<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShowOrderResource;
use App\Models\Contragent;
use App\Models\Materials;
use App\Models\Models;
use App\Models\Order;
use App\Models\OrderInstruction;
use App\Models\OrderModel;
use App\Models\OrderRecipes;
use App\Models\OrderSubModel;
use App\Models\Recipe;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::orderBy('created_at', 'asc')->get();
        return response()->json($orders);
    }

    public function show(Order $order): \Illuminate\Http\JsonResponse
    {
        $resource = new ShowOrderResource($order);

        return response()->json($resource);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'quantity' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'rasxod' => 'nullable|numeric',
            'final_product_name' => 'nullable|string',
            'comment' => 'nullable|string',
            'material_id' => 'required|integer|exists:items,id',
            'models' => 'required|array',
            'models.*.id' => 'required|integer',
            'models.*.submodel.id' => 'required|integer',
            'models.*.size.id' => 'required|integer',
            'models.*.quantity' => 'required|integer',
            'contragent_id' => 'nullable|integer',
            'contragent_name' => 'nullable|string',
            'contragent_description' => 'nullable|string',
            'instructions' => 'nullable|array',
            'instructions.*.title' => 'required|string',
            'instructions.*.description' => 'required|string',
        ]);

        if ($request->contragent_id){
            $contragent = Contragent::find($request->contragent_id);
        } else {
            $contragent = Contragent::create([
                'name' => $request->contragent_name ?? null,
                'description' => $request->contragent_description ?? null,
            ]);
        }

        $user = auth()->user();

        $order = Order::create([
            'name' => $request->name,
            'quantity' => $request->quantity,
            'status' => "inactive",
            'start_date' => $request->start_date ?? null,
            'end_date' => $request->end_date ?? null,
            'rasxod'  => $request->rasxod ?? 0,
            'branch_id' => $user->employee->branch_id,
            'contragent_id' => $contragent->id ?? null,
            'final_product_name' => $request->final_product_name ?? null,
            'comment' => $request->comment ?? null,
        ]);

        foreach ($request->instructions as $instruction) {
            $orderInstruction = OrderInstruction::create([
                'order_id' => $order->id,
                'title' => $instruction['title'],
                'description' => $instruction['description'],
            ]);
        }

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

            $material = Materials::where('material_id', $request->material_id)
                ->where('model_id', $orderModel->model_id)
                ->first();

            if (!$material) {
                $material = Materials::create([
                    'material_id' => $request->material_id,
                    'model_id' => $orderModel->model_id,
                ]);
            }

            OrderSubModel::create([
                'order_model_id' => $orderModel->id,
                'submodel_id' => $model['submodel']['id'],
                'size_id' => $model['size']['id'],
                'materials_id' => $material->id,
                'quantity' => $model['quantity'],
            ]);

//           $recipes = Recipe::where('model_color_id', $model['model_color']['id'])
//                        ->where('size_id', $model['size']['id'])
//                        ->get();
//
//           if ($recipes) {
//               foreach ($recipes as $recipe) {
//                   OrderRecipes::create([
//                       'order_id' => $order->id,
//                       'item_id' => $recipe->item_id,
//                       'model_color_id' => $recipe->model_color_id,
//                       'quantity' => $recipe->quantity,
//                       'size_id' => $recipe->size_id,
//                   ]);
//               }
//           }
        }

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order,
        ], 201);
    }

    public function update(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        $order->update($request->all());

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order,
        ]);
    }

    public function delete(Order $order): \Illuminate\Http\JsonResponse
    {
        $order->delete();
        return response()->json([
            'message' => 'Order deleted successfully',
        ]);
    }

    public function changeOrderStatus(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'status' => 'required',
        ]);

        $order->status = $request->status;
        $order->save();

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order,
        ]);
    }
}
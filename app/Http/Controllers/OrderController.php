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
use App\Models\OrderSize;
use App\Models\OrderSubModel;
use App\Models\Recipe;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::orderBy('created_at', 'asc')
            ->with('orderModels', 'orderModels.model', 'orderModels.material', 'orderModels.sizes.size', 'orderModels.submodels.submodel', 'instructions', 'contragent')
            ->get();
        return response()->json($orders);
    }

    public function show(Order $order): \Illuminate\Http\JsonResponse
    {
        $order->load('orderModels', 'orderModels.model', 'orderModels.material', 'orderModels.sizes', 'orderModels.submodels', 'instructions', 'orderRecipes', 'contragent');
        return response()->json($order);
    }

    public function getContragents(): \Illuminate\Http\JsonResponse
    {
        $contragents = Contragent::all();
        return response()->json($contragents);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'quantity' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'rasxod' => 'nullable|numeric',
            'comment' => 'nullable|string',
            'model' => 'required|array',
            'model.id' => 'required|integer',
            'model.material_id' => 'required|integer|exists:items,id',
            'model.submodels' => 'required|array',
            'model.sizes' => 'required|array',
            'model.*.sizes.*.id' => 'required|integer',
            'model.*.sizes.*.quantity' => 'required|integer',
            'instructions' => 'nullable|array',
            'instructions.*.title' => 'required|string',
            'instructions.*.description' => 'required|string',
            'recipes' => 'nullable|array',
            'recipes.*.item_id' => 'required|integer',
            'recipes.*.quantity' => 'required|integer',
            'recipes.*.submodel_id' => 'required|integer',
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
            'comment' => $request->comment ?? null,
        ]);

        $modelRasxod = Models::find($request->model['id'])->rasxod;

        $orderModel = OrderModel::create([
            'order_id' => $order->id,
            'model_id' => $request->model['id'],
            'rasxod' => $modelRasxod ?? 0,
            'material_id' => $request->model['material_id'],
        ]);

        if(!empty($request->instructions)){
            foreach ($request->instructions as $instruction) {
                $orderInstruction = OrderInstruction::create([
                    'order_id' => $order->id,
                    'title' => $instruction['title'],
                    'description' => $instruction['description'],
                ]);
            }
        }

        if (!empty($request->model['submodels'])){
            foreach ($request->model['submodels'] as $submodel) {
                $orderSubModel = OrderSubModel::create([
                    'order_model_id' => $orderModel->id,
                    'submodel_id' => $submodel,
                ]);
            }
        }

        if (!empty($request->recipes)){
            foreach ($request->recipes as $recipe) {
                $orderRecipe = OrderRecipes::create([
                    'order_id' => $order->id,
                    'item_id' => $recipe['item_id'],
                    'quantity' => $recipe['quantity'],
                    'submodel_id' => $recipe['submodel_id'],
                ]);
            }
        }

        if (!empty($request->model['sizes'])){
            foreach ($request->model['sizes'] as $size) {
                $orderSize = OrderSize::create([
                    'order_model_id' => $orderModel->id,
                    'size_id' => $size['id'],
                    'quantity' => $size['quantity'],
                ]);
            }
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
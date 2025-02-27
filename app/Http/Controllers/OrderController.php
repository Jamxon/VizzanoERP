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
            ->with(
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.sizes.size',
                'orderModel.submodels.submodel',
                'instructions',
                'contragent',
                'orderModel.submodels.orderRecipes',
                'orderModel.submodels.orderRecipes.item'
            )
            ->get();
        return response()->json($orders);
    }

    public function show(Order $order): \Illuminate\Http\JsonResponse
    {
        $order = new ShowOrderResource($order);
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

        $orderSubModel = null;

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
                    'submodel_id' => $orderSubModel->id,
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
        $validatedData = $request->validate([
            'name' => 'sometimes|string',
            'quantity' => 'sometimes|integer',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'rasxod' => 'sometimes|numeric',
            'comment' => 'sometimes|string',
            'contragent_id' => 'sometimes|integer|exists:contragent,id',
            'contragent_name' => 'sometimes|string',
            'contragent_description' => 'sometimes|string',
            'model' => 'sometimes|array',
            'model.id' => 'sometimes|integer|exists:models,id',
            'model.material_id' => 'sometimes|integer|exists:items,id',
            'model.submodels' => 'sometimes|array',
            'model.sizes' => 'sometimes|array',
            'instructions' => 'sometimes|array',
            'recipes' => 'sometimes|array',
        ]);

        // **1. Kontragentni yangilash yoki yaratish**
        if ($request->has('contragent_id')) {
            $contragent = Contragent::find($request->contragent_id);
        } elseif ($request->hasAny(['contragent_name'])) {
            $contragent = Contragent::updateOrCreate(
                ['name' => $request->contragent_name],
                ['description' => $request->contragent_description]
            );
        }

        // **2. Orderni yangilash**
        $order->update([
            'name' => $request->input('name', $order->name),
            'quantity' => $request->input('quantity', $order->quantity),
            'start_date' => $request->input('start_date', $order->start_date),
            'end_date' => $request->input('end_date', $order->end_date),
            'rasxod' => $request->input('rasxod', $order->rasxod),
            'comment' => $request->input('comment', $order->comment),
            'contragent_id' => isset($contragent) ? $contragent->id : $order->contragent_id,
            'price' => $request->input('price', $order->price) ?? $order->price,
        ]);

        // **3. Modelni yangilash**
        if ($request->has('model')) {
            $modelData = $request->input('model');

            $orderModel = OrderModel::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'model_id'    => $modelData['id'] ?? $order->orderModel->model_id,
                    'material_id' => $modelData['material_id'] ?? $order->orderModel->material_id,
                    'rasxod'      => isset($modelData['id']) ? Models::find($modelData['id'])->rasxod : ($order->orderModel->rasxod ?? 0),
                ]
            );

            // **5. O'lchamlarni yangilash**
            if (isset($modelData['sizes'])) {
                // Yangilash yoki yaratish
                foreach ($modelData['sizes'] as $sizeData) {
                    $orderSize = OrderSize::where('size_id', $sizeData['id'])
                        ->where('order_model_id', $orderModel->id)
                        ->first();

                    if ($orderSize) {
                        $orderSize->update([
                            'quantity' => $sizeData['quantity'],
                        ]);
                    } else {
                        OrderSize::create([
                            'order_model_id' => $orderModel->id,
                            'size_id'        => $sizeData['id'],
                            'quantity'       => $sizeData['quantity'],
                        ]);
                    }
                }
            }
        }

        // **6. Instructions yangilash**
        if ($request->has('instructions')) {
            // 1. Requestdan kelgan IDlarni olish (null larni chiqarib tashlaymiz)
            $requestInstructionIds = collect($request->input('instructions'))
                ->where('id', '!=', null)
                ->pluck('id')
                ->filter() // null qiymatlarni chiqarib tashlaydi
                ->toArray();

            // 2. Bazadagi mavjud IDlarni olish
            $existingInstructionIds = $order->instructions->pluck('id')->toArray();

            // 3. O‘chirilishi kerak bo'lgan IDlarni aniqlash
            $instructionsToDelete = array_diff($existingInstructionIds, $requestInstructionIds);

            // 4. O‘chirish
            OrderInstruction::whereIn('id', $instructionsToDelete)->delete();

            // 5. Yangi yoki mavjud bo'lganlarni yangilash yoki yaratish
            foreach ($request->input('instructions') as $instructionData) {
                if (!isset($instructionData['id'])) {
                    // ID yo‘q bo‘lsa, yangi ma’lumot yaratamiz
                    OrderInstruction::create([
                        'order_id'    => $order->id,
                        'title'       => $instructionData['title'],
                        'description' => $instructionData['description'],
                    ]);
                } else {
                    // ID mavjud bo‘lsa, update yoki create
                    OrderInstruction::updateOrCreate(
                        ['id' => $instructionData['id']],
                        [
                            'order_id'    => $order->id,
                            'title'       => $instructionData['title'],
                            'description' => $instructionData['description'],
                        ]
                    );
                }
            }
        }


        // **7. Recipes yangilash**
        if ($request->has('recipes')) {
            $requestRecipeIds = collect($request->input('recipes'))
                ->where('id', '!=', null)
                ->pluck('id')
                ->filter()
                ->toArray();
            $existingRecipeIds = $order->recipes->pluck('id')->toArray();

            // O‘chirilishi kerak bo'lganlar
            $recipesToDelete = array_diff($existingRecipeIds, $requestRecipeIds);
            OrderRecipes::whereIn('id', $recipesToDelete)->delete();

            // Yangi yoki mavjudlarni yangilash
            foreach ($request->input('recipes') as $recipeData) {
                OrderRecipes::updateOrCreate(
                    ['id' => $recipeData['id'] ?? null],
                    [
                        'order_id'   => $order->id,
                        'item_id'    => $recipeData['item_id'],
                        'quantity'   => $recipeData['quantity'],
                        'submodel_id'=> $recipeData['submodel_id'],
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Order updated successfully',
            'order'   => $order->fresh(),
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
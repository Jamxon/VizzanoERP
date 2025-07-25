<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShowOrderResource;
use App\Models\Contragent;
use App\Models\Log;
use App\Models\Models;
use App\Models\Order;
use App\Models\OrderInstruction;
use App\Models\OrderModel;
use App\Models\OrderRecipes;
use App\Models\OrderSize;
use App\Models\OrderSubModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function getOrdersWithQuantity(): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id;

        $statuses = [
            'inactive', 'active', 'printing', 'cutting', 'pending',
            'tailoring', 'tailored', 'checking', 'checked', 'packaging', 'completed'
        ];

        $data = DB::table('orders')
            ->select('status', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(quantity) as total_quantity'))
            ->whereIn('status', $statuses)
            ->where('branch_id', $branchId)
            ->groupBy('status')
            ->get();

        $result = collect($statuses)->map(function ($status) use ($data) {
            $row = $data->firstWhere('status', $status);
            return [
                'status' => $status,
                'order_count' => $row->order_count ?? 0,
                'total_quantity' => $row->total_quantity ?? 0,
            ];
        });

        return response()->json($result);
    }

    public function getOrdersWithoutOrderGroups(Request $request): \Illuminate\Http\JsonResponse
    {
    $excludedStatuses = ['completed', 'checking', 'checked', 'packaging', 'packaged'];

    $orders = Order::where('branch_id', auth()->user()->employee->branch_id)
        ->whereNotIn('status', $excludedStatuses) // 🔍 bu yerda status filtr qo‘shildi
        ->whereDoesntHave('orderGroups')
        ->with([
            'orderModel',
            'orderModel.model',
            'orderModel.material',
            'orderModel.submodels.submodel',
        ])
        ->get();

    return response()->json($orders);
    }
    
    public function getLogs(Request $request): \Illuminate\Http\JsonResponse
    {
        $user_id = $request->input('user_id');

        $logs = Log::when($user_id, function ($query, $user_id) {
                return $query->where('user_id', $user_id);
            })
            ->orderBy('created_at', 'desc')
            ->with('user')
            ->paginate(100);
        return response()->json($logs);
    }

    public function generateOrderPdf($id): \Illuminate\Http\Response
    {
        ini_set('memory_limit', '-1');
        $order = Order::with([
            'orderModel.sizes',
            'orderModel.submodels.submodel.orderRecipes',
            'orderModel.submodels.group',
            'orderModel.submodels.qualityChecks.qualityCheckDescriptions.tarification',
            'orderModel.submodels.sewingOutputs',
            'orderModel.submodels.otkOrderGroup',
            'instructions',
            'orderPrintingTime.user',
            'orderCuts.category',
            'contragent',
            'packageOutcomes'
        ])->findOrFail($id);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $resource = new ShowOrderResource($order);
        $orderData = $resource->toArray(request());

        $pdf = PDF::loadView('order.order', ['order' => $orderData]);

        return $pdf->download('buyurtma-'.$id.'.pdf');
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::orderBy('created_at', 'asc')
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->orderBy('updated_at', 'desc')
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
        $contragents = Contragent::orderBy('id', 'asc')
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->get();
        return response()->json($contragents);
    }

    public function storeContragents(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:20480', // Maksimal fayl o'lchami 2MB
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('images');
            $fileName = time() . '_' . $image->getClientOriginalName();
            $image->storeAs('/public/contragent/', $fileName);
        }

        $contragent = Contragent::create([
            'name' => $request->name,
            'description' => $request->description,
            'branch_id' => auth()->user()->employee->branch_id,
            'image' => isset($fileName) ? '/storage/contragent/' . $fileName : null,
        ]);

        return response()->json($contragent, 201);
    }

    public function updateContragents(Request $request, Contragent $contragent): \Illuminate\Http\JsonResponse
    {
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = time() . '_' . $image->getClientOriginalName();
            $image->storeAs('/public/contragent/', $fileName);
        }

        $contragent->update([
            'name' => $request->name,
            'description' => $request->description,
            'image' => isset($fileName) ? '/storage/contragent/' . $fileName : $contragent->image,
        ]);

        return response()->json($contragent);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        try {
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
                'model.sizes.*.color_id' => 'sometimes|integer|exists:colors,id',
                'model.sizes.*.color_name' => 'sometimes|string',
                'instructions' => 'nullable|array',
                'instructions.*.title' => 'required|string',
                'instructions.*.description' => 'required|string',
                'recipes' => 'nullable|array',
                'recipes.*.item_id' => 'required|integer',
                'recipes.*.quantity' => 'required|integer',
                'recipes.*.submodel_id' => 'required|integer',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::add($user->id, 'Buyurtma validatsiyasida xatolik', 'error', $request->all(), ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            Log::add($user->id, 'Buyurtma yaratishga urinish', 'attempt', $request->all());

            if ($request->contragent_id) {
                $contragent = Contragent::where('id', $request->contragent_id)
                    ->where('branch_id', $user->employee->branch_id)
                    ->first();
            } else {
                $contragent = Contragent::create([
                    'name' => $request->contragent_name ?? null,
                    'description' => $request->contragent_description ?? null,
                    'branch_id' => $user->employee->branch_id,
                ]);
            }

            $order = Order::create([
                'name' => $request->name,
                'quantity' => $request->quantity,
                'status' => "inactive",
                'start_date' => $request->start_date ?? null,
                'end_date' => $request->end_date ?? null,
                'rasxod' => $request->rasxod ?? 0,
                'branch_id' => $user->employee->branch_id,
                'contragent_id' => $contragent->id ?? null,
                'comment' => $request->comment ?? null,
                'price' => $request->price ?? 0,
            ]);

            $modelRasxod = Models::find($request->model['id'])->rasxod;
            $minute = Models::find($request->model['id'])->minute ?? 0;

            $orderModel = OrderModel::create([
                'order_id' => $order->id,
                'model_id' => $request->model['id'],
                'rasxod' => $modelRasxod ?? 0,
                'material_id' => $request->model['material_id'],
                'minute' => $minute,
            ]);

            $instructions = [];
            if (!empty($request->instructions)) {
                foreach ($request->instructions as $instruction) {
                    $created = OrderInstruction::create([
                        'order_id' => $order->id,
                        'title' => $instruction['title'],
                        'description' => $instruction['description'],
                    ]);
                    $instructions[] = $created->toArray();
                }
            }

            $submodels = [];
            foreach ($request->model['submodels'] as $submodel) {
                $created = OrderSubModel::create([
                    'order_model_id' => $orderModel->id,
                    'submodel_id' => $submodel,
                ]);
                $submodels[] = $created->toArray();
            }

            $recipes = [];
            if (!empty($request->recipes)) {
                foreach ($request->recipes as $recipe) {
                    $created = OrderRecipes::create([
                        'order_id' => $order->id,
                        'item_id' => $recipe['item_id'],
                        'quantity' => $recipe['quantity'],
                        'submodel_id' => $recipe['submodel_id'],
                    ]);
                    $recipes[] = $created->toArray();
                }
            }

            $sizes = [];
            foreach ($request->model['sizes'] as $size) {

                if (!isset($size['color_id']) && isset($size['color_name'])) {
                    $color = \App\Models\Color::firstOrCreate(
                        ['name' => $size['color_name']]
                    );
                    $size['color_id'] = $color->id;
                }

                $created = OrderSize::create([
                    'order_model_id' => $orderModel->id,
                    'size_id' => $size['id'],
                    'quantity' => $size['quantity'],
                    'color_id' => $size['color_id'] ?? null,
                ]);
                $sizes[] = $created->toArray();
            }

            DB::commit();

            Log::add($user->id, 'Yangi buyurtma yaratildi', 'create', null, [
                'order' => $order->toArray(),
                'order_model' => $orderModel->toArray(),
                'instructions' => $instructions,
                'submodels' => $submodels,
                'recipes' => $recipes,
                'sizes' => $sizes,
                'contragent' => $contragent ? $contragent->toArray() : null,
            ]);

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::add($user->id, 'Buyurtma yaratishda xatolik yuz berdi', 'error', $request->all(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Order creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        try {
            Log::add(auth()->id(), "Buyurtma yangilashga urinish bo'lmoqda", 'attempt', $order->toArray(), null);

             $validatedData = $request->validate([
                'name' => 'sometimes|string',
                'quantity' => 'sometimes|integer',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date',
                'rasxod' => 'sometimes|numeric',
                'comment' => 'nullable|string',
                'contragent_id' => 'sometimes|integer|exists:contragent,id',
                'contragent_name' => 'sometimes|string',
                'contragent_description' => 'sometimes|string',
                'model' => 'sometimes|array',
                'model.id' => 'sometimes|integer|exists:models,id',
                'model.material_id' => 'sometimes|integer|exists:items,id',
                'model.submodels' => 'sometimes|array',
                'model.sizes' => 'sometimes|array',
                'model.sizes.*.id' => 'sometimes|integer|exists:order_sizes,id',
                'model.sizes.*.size_id' => 'sometimes|integer|exists:sizes,id',
                'model.sizes.*.quantity' => 'sometimes|integer',
                'model.sizes.*.color_id' => 'sometimes|integer|exists:colors,id',
                'model.sizes.*.color_name' => 'sometimes|string',
                'model.minute' => 'sometimes|integer',
                'instructions' => 'sometimes|array',
                'recipes' => 'sometimes|array',
            ]);

            $oldData = [
                'order' => $order->toArray(),
                'order_model' => optional($order->orderModel)->toArray() ?? [],
                'instructions' => $order->instructions->toArray() ?? [],
                'recipes' => $order->orderRecipes->toArray() ?? [],
                'sizes' => $order->orderModel ? $order->orderModel->sizes->toArray() : [],
                'contragent' => optional($order->contragent)->toArray() ?? [],
            ];

            if ($request->has('contragent_id')) {
                $contragent = Contragent::find($request->contragent_id);
            } elseif ($request->hasAny(['contragent_name'])) {
                $contragent = Contragent::updateOrCreate(
                    ['name' => $request->contragent_name],
                    ['description' => $request->contragent_description],
                    ['branch_id' => auth()->user()->employee->branch_id]
                );
            }

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

            if ($request->has('model')) {
                $modelData = $request->input('model');

                $orderModel = OrderModel::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'model_id'    => $modelData['id'] ?? optional($order->orderModel)->model_id,
                        'material_id' => $modelData['material_id'] ?? optional($order->orderModel)->material_id,
                        'rasxod'      => isset($modelData['id']) ? Models::find($modelData['id'])->rasxod : optional($order->orderModel)->rasxod,
                        'minute'      => $modelData['minute'] ?? optional($order->orderModel)->minute,
                    ]
                );

                if (isset($modelData['sizes'])) {
                    foreach ($modelData['sizes'] as $sizeData) {
                        if (!isset($sizeData['color_id']) && isset($sizeData['color_name'])) {
                            $color = \App\Models\Color::firstOrCreate(
                                ['name' => $sizeData['color_name'],]
                            );
                            $sizeData['color_id'] = $color->id;
                        }

                        if (isset($sizeData['id'])){
                            $orderSize = OrderSize::findOrFail($sizeData['id']);
                            $orderSize->update([
                                'quantity' => $sizeData['quantity'],
                                'color_id' => $sizeData['color_id'] ?? $orderSize->color_id,
                                'size_id' => $sizeData['size_id'],
                            ]);
                        }else{
                            OrderSize::create([
                                'order_model_id' => $orderModel->id,
                                'size_id'        => $sizeData['size_id'],
                                'quantity'       => $sizeData['quantity'],
                                'color_id'      => $sizeData['color_id'] ?? null,
                            ]);
                        }
                    }
                }
            }

            if ($request->has('instructions')) {
                $requestInstructionIds = collect($request->input('instructions'))->pluck('id')->filter()->toArray();
                $existingInstructionIds = $order->instructions->pluck('id')->toArray();

                $instructionsToDelete = array_diff($existingInstructionIds, $requestInstructionIds);
                OrderInstruction::whereIn('id', $instructionsToDelete)->delete();

                foreach ($request->input('instructions') as $instructionData) {
                    if (!isset($instructionData['id'])) {
                        OrderInstruction::create([
                            'order_id' => $order->id,
                            'title' => $instructionData['title'],
                            'description' => $instructionData['description'],
                        ]);
                    } else {
                        OrderInstruction::updateOrCreate(
                            ['id' => $instructionData['id']],
                            [
                                'order_id' => $order->id,
                                'title' => $instructionData['title'],
                                'description' => $instructionData['description'],
                            ]
                        );
                    }
                }
            }

            if ($request->has('recipes')) {
                $recipes = collect($request->input('recipes'));
                $requestRecipeIds = $recipes->pluck('id')->filter()->toArray();
                $existingRecipeIds = $order->orderRecipes->pluck('id')->toArray();

                $recipesToDelete = array_diff($existingRecipeIds, $requestRecipeIds);
                OrderRecipes::whereIn('id', $recipesToDelete)->delete();

                foreach ($recipes as $recipeData) {
                    $orderRecipe = OrderRecipes::find($recipeData['id']);
                    if ($orderRecipe) {
                        $orderRecipe->update([
                            'item_id' => $recipeData['item_id'],
                            'quantity' => $recipeData['quantity'],
                            'submodel_id' => $recipeData['submodel_id'],
                        ]);
                    } else {
                        OrderRecipes::create([
                            'order_id' => $order->id,
                            'item_id' => $recipeData['item_id'],
                            'quantity' => $recipeData['quantity'],
                            'submodel_id' => $recipeData['submodel_id'],
                        ]);
                    }
                }
            }

            $newData = [
                'order' => $order->fresh()->toArray(),
                'order_model' => optional($order->fresh()->orderModel)->toArray() ?? [],
                'instructions' => $order->fresh()->instructions->toArray() ?? [],
                'recipes' => $order->fresh()->orderRecipes->toArray() ?? [],
                'sizes' => $order->orderModel ? $order->orderModel->sizes->toArray() : [],
                'contragent' => isset($contragent) ? $contragent->toArray() : optional($order->contragent)->toArray() ?? [],
            ];

            Log::add(auth()->id(), 'Buyurtma yangilandi', 'edit', $oldData, $newData);

            return response()->json([
                'message' => 'Order updated successfully',
                'order'   => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::add(auth()->id(), "Buyurtma yangilashda hatolik yuz berdi", 'edit', $e->getMessage(), $e);

            return response()->json([
                'message' => 'Xatolik yuz berdi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Order $order): \Illuminate\Http\JsonResponse
    {
        try {
            Log::add(auth()->id(), 'Buyurtmani o‘chirishga urinish qilindi', 'attempt', $order->toArray(), null);

            $order->delete();

            Log::add(auth()->id(), 'Buyurtma muvaffaqiyatli o‘chirildi', 'delete', $order->toArray(), null);

            return response()->json([
                'message' => 'Order deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::add(auth()->id(), 'Buyurtmani o‘chirishda xatolik: ' . $e->getMessage(), 'attempt', $order->toArray(), null);

            return response()->json([
                'message' => 'Xatolik yuz berdi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function changeOrderStatus(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'status' => 'required',
        ]);

        try {
            Log::add(auth()->id(), 'Buyurtma holatini o‘zgartirishga urinish qilindi', 'attempt', $order->toArray(), ['new_status' => $request->status]);

            $oldStatus = $order->status;
            $order->status = $request->status;
            $order->save();

            Log::add(auth()->id(), 'Buyurtma holati yangilandi', 'edit', ['status' => $oldStatus], ['status' => $request->status]);

            return response()->json([
                'message' => 'Order status updated successfully',
                'order' => $order,
            ]);
        } catch (\Exception $e) {
            Log::add(auth()->id(), 'Buyurtma holatini o‘zgartirishda xatolik: ' . $e->getMessage(), 'attempt', $order->toArray(), ['new_status' => $request->status]);

            return response()->json([
                'message' => 'Xatolik yuz berdi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
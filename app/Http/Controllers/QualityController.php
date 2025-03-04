<?php

namespace App\Http\Controllers;

use App\Models\ModelImages;
use App\Models\Order;
use App\Models\OtkOrderGroup;
use App\Models\QualityCheck;
use App\Models\QualityCheckDescription;
use App\Models\QualityDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QualityController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $groupIds = optional($user->employee->group)->pluck('id') ?? collect([]); // Agar null bo'lsa, bo'sh array olish

        if ($groupIds->isEmpty()) {
            return response()->json(['message' => 'No groups found for user'], 404);
        }
        // Foydalanuvchining guruhiga tegishli order submodellari
        $otkOrderGroups = OtkOrderGroup::whereIn('group_id', $groupIds)
            ->with([
                'orderSubModel.orderModel.order',
                'orderSubModel.orderModel.model',
                'orderSubModel.orderModel.sizes.size',
                'orderSubModel.orderModel.material',
                'orderSubModel.submodel',
                'orderSubModel.group.group'
            ])
            ->get();

        // Orderlarni guruhlarga ajratib, har bir submodelni alohida order sifatida chiqarish
        $orders = $otkOrderGroups->map(function ($subModel) {
            $orderModel = $subModel->orderSubModel->orderModel;
            $order = $orderModel->order;

            return [
                'id' => $order->id,
                'name' => $order->name,
                'quantity' => $order->quantity,
                'status' => $order->status,
                'start_date' => $order->start_date,
                'end_date' => $order->end_date,
                'rasxod' => $order->rasxod,
                'comment' => $order->comment,
                'price' => $order->price,
                'order_model' => [
                    'id' => $orderModel->id,
                    'rasxod' => $orderModel->rasxod,
                    'status' => $orderModel->status,
                    'model' => [
                        'id' => $orderModel->model->id,
                        'name' => $orderModel->model->name,
                        'rasxod' => $orderModel->model->rasxod,
                    ],
                    'sizes' => $orderModel->sizes->map(function ($size) {
                        return [
                            'id' => $size->id,
                            'quantity' => $size->quantity,
                            'size' => [
                                'id' => $size->size->id,
                                'name' => $size->size->name,
                            ],
                        ];
                    })->values(),
                    'material' => $orderModel->material,
                    'submodels' => [[ // Submodelni alohida array ichida yuboramiz
                        'id' => $subModel->orderSubModel->id,
                        'submodel' => [
                            'id' => $subModel->orderSubModel->submodel->id,
                            'name' => $subModel->orderSubModel->submodel->name,
                        ],
                        'group' => [
                            'id' => $subModel->group->id ?? null,
                            'group' => [
                                'id' => $subModel->group->group->id ?? null,
                                'name' => $subModel->group->group->name ?? null,
                            ],
                        ],
                    ]],
                ],
            ];
        })->values();

        return response()->json($orders);
    }

    public function showOrder($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::find($id);
        $order->load(
            'orderModel',
            'orderModel.model',
            'orderModel.sizes.size',
            'orderModel.material',
            'orderModel.submodels.submodel',
            'orderModel.submodels.group.group',
        );

        return response()->json($order);
    }

    public function qualityDescriptionStore(Request $request): \Illuminate\Http\JsonResponse
    {

        $request->validate([
            'description' => 'required|string',
        ]);

        $qualityDescription = QualityDescription::create(
            [
                'description' => $request->description,
                'user_id' => auth()->user()->id
            ]
        );

        return response()->json($qualityDescription);
    }

    public function getQualityDescription(): \Illuminate\Http\JsonResponse
    {
        $qualityDescriptions = QualityDescription::where('user_id', auth()->id())
            ->whereDate('created_at', now()->toDateString())
            ->get();

        return response()->json($qualityDescriptions);

    }

    public function qualityCheckStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->input('data'), true);

        if (!$data) {
            return response()->json(['error' => 'Invalid JSON data'], 400);
        }

        $validatedData = Validator::make($data, [
            'order_sub_model_id' => 'required|integer|exists:order_sub_models,id',
            'status' => 'required|boolean',
            'comment' => 'nullable|string',
            'descriptions' => 'nullable|array',
        ])->validate();

        $imageName = null;
        if ($request->hasFile('image') && !empty($request->file('image'))) {
                $image = $request->file('image');
                $fileName = time() . '_' . $image->getClientOriginalName();
                 //full path to image
                $imageName = "/storage/images/" . $fileName;
                $image->storeAs("/images/" . $fileName);

        }

        $qualityCheck = QualityCheck::create([
            'order_sub_model_id' => $validatedData['order_sub_model_id'],
            'status' => $validatedData['status'],
            'image' => $imageName ?? null,
            'user_id' => auth()->user()->id,
            'comment' => $validatedData['comment'] ?? null,
        ]);

        if ($qualityCheck->status === false && !empty($validatedData['descriptions'])) {
            foreach ($validatedData['descriptions'] as $description) {
                QualityCheckDescription::create([
                    'quality_check_id' => $qualityCheck->id,
                    'quality_description_id' => $description,
                ]);
            }
        }

        return response()->json($qualityCheck);
    }

    public function getQualityChecks(Request $request): \Illuminate\Http\JsonResponse
    {
        $counts = QualityCheck::where('user_id', auth()->id())
            ->whereDate('created_at', now()->toDateString())
            ->where('order_sub_model_id', $request->order_sub_model_id)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as count')
            ->pluck('count', 'status');

        return response()->json([
            'qualityChecksTrue' => $counts[1] ?? 0,  // true status
            'qualityChecksFalse' => $counts[0] ?? 0, // false status
        ]);
    }
}

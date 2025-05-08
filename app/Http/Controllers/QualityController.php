<?php

namespace App\Http\Controllers;

use App\Models\ModelImages;
use App\Models\Order;
use App\Models\OrderSubModel;
use App\Models\OtkOrderGroup;
use App\Models\QualityCheck;
use App\Models\QualityCheckDescription;
use App\Models\QualityDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QualityController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $groupIds = optional($user->employee->group)->pluck('id') ?? collect([]);

        if ($groupIds->isEmpty()) {
            return response()->json(['message' => 'No groups found for user'], 404);
        }
        $otkOrderGroups = OtkOrderGroup::whereIn('group_id', $groupIds)
            ->whereHas('orderSubModel.orderModel.order', function ($query) {
                $query->whereIn('status', ['tailoring', 'tailored', 'checking'])
                    ->where('branch_id', auth()->user()->employee->branch_id);
            })
            ->with([
                'orderSubModel.orderModel.order',
                'orderSubModel.orderModel.model',
                'orderSubModel.orderModel.sizes.size',
                'orderSubModel.orderModel.material',
                'orderSubModel.submodel',
                'orderSubModel.group.group'
            ])
            ->get();

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
                    'submodels' => [[
                        'id' => $subModel->orderSubModel->id,
                        'submodel' => [
                            'id' => $subModel->orderSubModel->submodel->id,
                            'name' => $subModel->orderSubModel->submodel->name,
                        ],
                        'group' => [
                            'id' => $subModel->orderSubModel->group->group->id ?? null,
                            'name' => $subModel->orderSubModel->group->group->name ?? null,
                        ],
                    ]],
                ],
            ];
        })->values();

        return response()->json($orders);
    }

    public function showOrder($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::where('id', $id)
            ->whereIn('status', ['tailoring', 'tailored', 'checking'])
            ->with(
                'orderModel',
                'orderModel.model',
                'orderModel.sizes.size',
                'orderModel.material',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group.group',
                'orderModel.submodels.tarificationCategories',
                'orderModel.submodels.tarificationCategories.tarifications',
                'orderModel.submodels.tarificationCategories.tarifications.employee',
                'orderModel.submodels.tarificationCategories.tarifications.razryad',
                'orderModel.submodels.tarificationCategories.tarifications.typewriter',
            )
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Buyurtma topilmadi yoki ruxsat etilgan statuslarda emas.'
            ], 404);
        }

        return response()->json($order);
    }

    public function qualityCheckSuccessStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'order_sub_model_id' => 'required|exists:order_sub_models,id',
            'comment' => 'nullable|string',
        ]);

        // Submodel va unga tegishli orderni oldindan olish (1 query)
        $submodel = OrderSubModel::where('id', $validated['order_sub_model_id'])->with('orderModel.order')->first();
        $order = $submodel->orderModel->order;

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Buyurtma topilmadi.'
            ], 404);
        }

        // Hozirgi true statusdagi tekshiruvlar soni
        $trueChecksCount = QualityCheck::whereHas('order_sub_model', function ($query) use ($order) {
            $query->whereHas('orderModel', function ($query) use ($order) {
                $query->where('order_id', $order->id);
            });
        })->where('status', true)->count();

        // Limit tekshiruvi
        if ($trueChecksCount >= $order->quantity) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tekshiruvlar miqdori buyurtma miqdoridan oshmasligi kerak.'
            ], 422);
        }

        // QualityCheck yoziladi
        $qualityCheck = QualityCheck::create([
            'order_sub_model_id' => $submodel->id,
            'status' => true,
            'user_id' => auth()->id(),
            'comment' => $validated['comment'] ?? null,
            'image' => null,
        ]);

        // Agar yangi yozilganidan so‘ng quantity to‘lsa — order status 'checked' bo'ladi
        if (($trueChecksCount + 1) === $order->quantity) {
            $order->status = 'checked';
            $order->save();
        }

        return response()->json([
            'message' => 'Muvofaqiyatli saqlandi (status = true)',
            'data' => $qualityCheck
        ]);
    }

    public function qualityCheckFailureStore(Request $request): \Illuminate\Http\JsonResponse
    {
        // JSON formatda kelsa, descriptionsni arrayga aylantirib qo'yamiz
        $tarifications = $request->input('tarifications');
        if (is_string($tarifications)) {
            $tarifications = json_decode($tarifications, true);
        }

        $requestData = $request->all();
        $requestData['tarifications'] = $tarifications;

        $validated = validator($requestData, [
            'order_sub_model_id' => 'required|exists:order_sub_models,id',
            'comment' => 'nullable|string',
            'tarifications' => 'nullable|array',
            'tarifications.*' => 'exists:tarifications,id',
            'image' => 'nullable|image|max:20480',
        ])->validate();

        // Rasmni saqlash
        $imageName = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = time() . '_' . $image->getClientOriginalName();
            $image->storeAs('/images/', $fileName);
            $imageName = "images/" . $fileName;
        }

        // Bazaga yozish
        $qualityCheck = QualityCheck::create([
            'order_sub_model_id' => $validated['order_sub_model_id'],
            'status' => false,
            'user_id' => auth()->id(),
            'comment' => $validated['comment'] ?? null,
            'image' => $imageName,
        ]);

        // Descriptionlar kiritish
        if (!empty($validated['tarifications'])) {
            foreach ($validated['tarifications'] as $descriptionId) {
                QualityCheckDescription::create([
                    'quality_check_id' => $qualityCheck->id,
                    'quality_description_id' => $descriptionId,
                ]);
            }
        }

        return response()->json([
            'message' => 'Xatolik holati saqlandi (status = false)',
            'data' => $qualityCheck
        ]);
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

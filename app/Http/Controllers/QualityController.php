<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\QualityCheck;
use App\Models\QualityCheckDescription;
use App\Models\QualityDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QualityController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status' , $request->status)
            ->orderBy('updated_at', 'desc')
            ->with(
                'orderModel',
                'orderModel.model',
                'orderModel.sizes.size',
                'orderModel.material',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group.group',
            )
            ->get();

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
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images'), $imageName);
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
}

<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\QualityCheck;
use App\Models\QualityCheckDescription;
use App\Models\QualityDescription;
use Illuminate\Http\Request;

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

        $request->validate([
            'order_sub_model_id' => 'required|integer|exists:order_sub_models,id',
            'status' => 'required|boolean',
        ]);


        if ($request->has('image')) {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);
            $image = $request->file('image');
            $imageName = time() . '.' . $image->extension();
            $image->move(public_path('images'), $imageName);
        }

        $qualityCheck = QualityCheck::create(
            [
                'order_sub_model_id' => $request->order_sub_model_id,
                'status' => $request->status,
                'image' => $imageName ?? null,
                'user_id' => auth()->user()->id,
                'comment' => $request->comment ?? null,
            ]
        );


        if ($qualityCheck->status === false){
            foreach ($request->descriptions as $description) {
                QualityCheckDescription::create(
                    [
                        'quality_check_id' => $qualityCheck->id,
                        'quality_description_id' => $description['id'],
                    ]
                );
            }
        }

        return response()->json($qualityCheck);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ShipmentPlan;
use Illuminate\Http\Request;


class ShipmentPlanController extends Controller
{
    public function index(): \Illuminate\Database\Eloquent\Collection|array
    {
        return ShipmentPlan::with(
            'items.model',
            'items.details.order',
            'items.details.submodel',
        )->latest()->get();
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'comment' => 'nullable|string',
            'items' => 'required|array',
            'items.*.model_id' => 'required|exists:models,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.details' => 'nullable|array',
        ]);

        $plan = ShipmentPlan::create([
            'date' => $validated['date'],
            'comment' => $validated['comment'] ?? null,
            'created_by' => auth()->id(),
            'status' => 'draft',
        ]);

        foreach ($validated['items'] as $itemData) {
            $item = $plan->items()->create([
                'model_id' => $itemData['model_id'],
                'quantity' => $itemData['quantity'],
                'completed' => 0,
            ]);

            if (!empty($itemData['details'])) {
                foreach ($itemData['details'] as $detail) {
                    $item->details()->create([
                        'order_id' => $detail['order_id'],
                        'submodel_id' => $detail['submodel_id'],
                        'quantity' => $detail['quantity'],
                        'comment' => $detail['comment'] ?? null,
                    ]);
                }
            }
        }

        return response()->json($plan->load('items.details'), 201);
    }

    public function update(Request $request, ShipmentPlan $shipmentPlan): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'comment' => 'nullable|string',
            'status' => 'in:draft,active,completed',
            'items' => 'required|array',
            'items.*.model_id' => 'required|exists:models,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.details' => 'nullable|array',
        ]);

        // Plan ma'lumotlarini yangilash
        $shipmentPlan->update([
            'date' => $validated['date'],
            'comment' => $validated['comment'] ?? null,
            'status' => $validated['status'] ?? 'draft',
        ]);

        // Eski item va detail'larni o'chirish
        foreach ($shipmentPlan->items as $item) {
            $item->details()->delete(); // detail lar
        }
        $shipmentPlan->items()->delete(); // itemlar

        // Yangilarini yaratish
        foreach ($validated['items'] as $itemData) {
            $item = $shipmentPlan->items()->create([
                'model_id' => $itemData['model_id'],
                'quantity' => $itemData['quantity'],
                'completed' => 0,
            ]);

            if (!empty($itemData['details'])) {
                foreach ($itemData['details'] as $detail) {
                    $item->details()->create([
                        'order_id' => $detail['order_id'],
                        'submodel_id' => $detail['submodel_id'],
                        'quantity' => $detail['quantity'],
                        'comment' => $detail['comment'] ?? null,
                    ]);
                }
            }
        }

        return response()->json($shipmentPlan->load('items.details'));
    }


}
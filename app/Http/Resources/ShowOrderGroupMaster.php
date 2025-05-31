<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ShowOrderGroupMaster extends JsonResource
{
    public function toArray(Request $request): array
    {
        $orderQuantity = $this->orderModel->order->quantity ?? 0;

        $totalSewn = $this->orderModel->submodels->sum(function ($submodel) {
            return $submodel->sewingOutputs->sum('quantity');
        });

        $remainAmount = $orderQuantity - $totalSewn;

        // ✅ Bugungi tikilgan mahsulotlar soni
        $todaySewn = $this->orderModel->submodels->sum(function ($submodel) {
            return $submodel->sewingOutputs
                ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
                ->sum('quantity');
        });

        // ✅ Rasxod asosida bugun ishlab topilgan pul
        $todayEarned = $todaySewn * ($this->orderModel->rasxod ?? 0);

        // ✅ Bugungi attendances soni
        $attendanceCount = $this->orderModel->submodels->group->group
            ?->employees()
            ->whereHas('attendances', function ($query) {
                $query->whereDate('date', now()->toDateString());
            })
            ->count();

        // ✅ Har bir xodimga to‘g‘ri keladigan pul
        $perEmployeeEarning = $attendanceCount > 0
            ? round($todayEarned / $attendanceCount, 2)
            : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'rasxod' => $this->rasxod,
            'status' => $this->status,
            'comment' => $this->comment,
            'remainAmount' => $remainAmount,

            // 🔢 Yangi qo‘shilgan hisob-kitoblar
            'todaySewn' => $todaySewn,
            'todayEarned' => round($todayEarned, 2),
            'attendanceCount' => $attendanceCount,
            'perEmployeeEarning' => $perEmployeeEarning,

            'orderModel' => $this->orderModel ? [
                'id' => $this->orderModel->id,
                'model' => [
                    'id' => $this->orderModel->model?->id,
                    'name' => $this->orderModel->model?->name,
                ],
                'material' => [
                    'id' => $this->orderModel->material?->id,
                    'name' => $this->orderModel->material?->name,
                ],
                'sizes' => $this->orderModel->sizes?->map(fn($size) => [
                        'id' => $size->id,
                        'size' => $size->size,
                        'quantity' => $size->quantity,
                    ]) ?? [],
                'submodels' => $this->orderModel->submodels?->map(fn($submodel) => [
                        'id' => $submodel->id,
                        'submodel' => $submodel->submodel,
                        'tarificationCategories' => $submodel->tarificationCategories?->map(fn($category) => [
                                'id' => $category->id,
                                'name' => $category->name,
                                'tarifications' => $category->tarifications,
                            ]) ?? [],
                        'sewingOutputs' => $submodel->sewingOutputs
                                ?->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
                                ->map(fn($sewingOutput) => [
                                    'id' => $sewingOutput->id,
                                    'quantity' => $sewingOutput->quantity,
                                    'comment' => $sewingOutput->comment,
                                    'time' => [
                                        'id' => $sewingOutput->time?->id,
                                        'time' => $sewingOutput->time?->time,
                                    ],
                                ])
                                ->values()
                                ->toArray() ?? [],
                        'total_quantity' => $submodel->sewingOutputs->sum('quantity') ?? 0
                    ]) ?? [],
            ] : null,

            'instructions' => $this->instructions,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class GroupPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $monthStart = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $monthEnd = Carbon::create($this->year, $this->month, 1)->endOfMonth();

        $sewingOutputs = collect();

        foreach ($this->group->orders as $order) {
            foreach ($order->order->orderModel->submodels as $submodel) {
                $sewingOutputs = $sewingOutputs->merge($submodel->sewingOutPuts->filter(function ($output) use ($monthStart, $monthEnd) {
                    return $output->created_at >= $monthStart && $output->created_at <= $monthEnd;
                }));
            }
        }

        // Group by day
        $groupedByDay = $sewingOutputs->groupBy(function ($output) {
            return $output->created_at->format('Y-m-d');
        });

        $days = $groupedByDay->map(function ($outputs, $day) {
            return [
                'date' => $day,
                'total_count' => $outputs->sum('quantity'),
            ];
        })->values();

        return [
            'group_id' => $this->group_id,
            'group_name' => $this->group->name,
            'plan' => $this->quantity,
            'month' => $this->month,
            'year' => $this->year,
            'daily_outputs' => $days,
            'monthly_total' => $days->sum('total_count'),
        ];
    }
}

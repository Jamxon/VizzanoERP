<?php

namespace App\Http\Controllers;

use App\Models\SewingOutputs;
use Illuminate\Http\Request;

class VizzanoReportTvController extends Controller
{
    public function getSewingOutputs(Request $request): \Illuminate\Http\JsonResponse
    {
        $startDate = $request->get('start_date') ?? now()->format('Y-m-d');
        $endDate = $request->get('end_date');

        $query = SewingOutputs::whereDate('created_at', '>=', $startDate);

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $sewingOutputs = $query->orderBy('created_at', 'desc')->get();

        $resource = $sewingOutputs->map(function ($sewingOutput) {
            return [
                'id' => $sewingOutput->id,
                'model' => $sewingOutput->orderSubmodel->orderModel->model,
                'submodel' => $sewingOutput->orderSubmodel->submodel,
                'quantity' => $sewingOutput->quantity,
                'group' => $sewingOutput->orderSubmodel->group->group,
            ];
        });

        return response()->json($resource);
    }

}

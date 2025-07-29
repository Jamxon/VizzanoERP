<?php

namespace App\Http\Controllers;

class CuttingPlanController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $plans = \App\Models\CuttingPlan::with('department.responsibleUser.employee')->get();

        return response()->json($plans);
    }

    public function store(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'quantity' => 'required|integer|min:1',
        ]);

        $plan = \App\Models\CuttingPlan::create($data);

        return response()->json($plan, 201);
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        $plan = \App\Models\CuttingPlan::with('department.responsibleUser.employee')->findOrFail($id);

        return response()->json($plan);
    }

    public function update(\Illuminate\Http\Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'department_id' => 'sometimes|exists:departments,id',
            'month' => 'sometimes|integer|min:1|max:12',
            'year' => 'sometimes|integer|min:2000|max:2100',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        $plan = \App\Models\CuttingPlan::findOrFail($id);
        $plan->update($data);

        return response()->json($plan);
    }

    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $plan = \App\Models\CuttingPlan::findOrFail($id);
        $plan->delete();

        return response()->json(null, 204);
    }
}
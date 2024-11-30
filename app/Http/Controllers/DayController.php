<?php

namespace App\Http\Controllers;

use App\Models\Day;
use App\Models\Group;
use App\Models\Models;
use App\Models\StandartWork;
use App\Models\TechnicNorma;
use Illuminate\Http\Request;

class DayController extends Controller
{
    public function getDaily(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if (empty($validated['end_date'])) {
            $daily = Day::whereDate('created_at', $validated['start_date'])->get();
        } else {
            $daily = Day::whereBetween('created_at', [$validated['start_date'], $validated['end_date']])->get();
        }

        return response()->json([
            'message' => 'Data retrieved successfully',
            'data' => $daily,
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'worker_count' => 'required|integer',
            'group_id' => 'required|integer',
            'real_model' => 'required|integer',
        ]);

        $group = Group::find($request->group_id);
        $model = $group->models->id;
        $technicNorma = TechnicNorma::where('model_id', $model)->first();
        $modelNormalTime = $technicNorma->sekund;
        $totalWorkTime = $request->worker_count * StandartWork::find(1)->work_time * 60;
        $expectedModel = floor($totalWorkTime / $modelNormalTime);

        $day = Day::create([
            'worker_count' => $request->worker_count,
            'total_work_time' => $totalWorkTime,
            'group_id' => $request->group_id,
            'expected_model' => $expectedModel,
            'real_model' => $request->real_model,
            'diff_model' => $request->real_model - $expectedModel,
        ]);

        if ($day) {
            return response()->json([
                'message' => 'Data saved successfully',
                'data' => $day,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Data not saved',
            ], 400);
        }
    }
}

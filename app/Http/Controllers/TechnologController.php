<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\SubModel;
use Illuminate\Http\Request;

class TechnologController extends Controller
{
    public function getByModelId($model_id)
    {
        $submodel = SubModel::where('model_id', $model_id)
            ->with('liningPreparations.liningApplications')
            ->firstOrFail();

        $preparations = $submodel->liningPreparations->map(function ($preparation) {
            $liningApplications = $preparation->liningApplications->map(function ($liningApplication) {
                return [
                    'name' => $liningApplication->name,
                    'time' => $liningApplication->second,
                    'sum' => $liningApplication->summa
                ];
            });

            $preparationTotalTime = $liningApplications->sum('time');
            $preparationTotalSum = $liningApplications->sum('sum');

            return [
                'name' => $preparation->name,
                'application' => [
                    'id' => $preparation->application->id,
                    'name' => $preparation->application->name,
                ],
                'lining_applications' => $liningApplications,
                'total_time' => $preparationTotalTime,
                'total_sum' => $preparationTotalSum,
            ];
        });

        $applications = $submodel->liningPreparations
            ->groupBy('application.id')
            ->map(function ($group) {
                $applicationName = $group->first()->application->name;

                $applicationTotalTime = $group->reduce(function ($carry, $preparation) {
                    return $carry + $preparation->liningApplications->sum('second');
                }, 0);

                $applicationTotalSum = $group->reduce(function ($carry, $preparation) {
                    return $carry + $preparation->liningApplications->sum('summa');
                }, 0);

                return [
                    'id' => $group->first()->application->id,
                    'name' => $applicationName,
                    'total_time' => $applicationTotalTime,
                    'total_sum' => $applicationTotalSum,
                ];
            });

        $totalTime = $preparations->sum('total_time');
        $totalSum = $preparations->sum('total_sum');

        $response = [
            'submodel' => [
                'id' => $submodel->id,
                'name' => $submodel->name,
            ],
            'applications' => $applications->values(),
            'preparations' => $preparations,
            'total_time' => $totalTime,
            'total_sum' => $totalSum,
        ];

        return response()->json($response);
    }

    
}

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
            ->with('liningPreparations.liningApplications') // relationlarni yuklash
            ->firstOrFail(); // agar topilmasa, 404 xato qaytariladi

        $preparations = $submodel->liningPreparations->map(function ($preparation) {
            $liningApplications = $preparation->liningApplications->map(function ($liningApplication) {
                return [
                    'name' => $liningApplication->name,
                    'time' => $liningApplication->second, // vaqtni olamiz
                    'sum' => $liningApplication->summa,  // summani olamiz
                ];
            });

            // Har bir lining_preparation uchun total_time va total_sum
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

        // Submodelning barcha applications'ini yig'amiz
        $applications = $submodel->liningPreparations
            ->groupBy('application.id')
            ->map(function ($group) {
                $applicationName = $group->first()->application->name;

                // Application uchun total_time va total_sum
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

        // Submodel uchun umumiy total_time va total_sum
        $totalTime = $preparations->sum('total_time');
        $totalSum = $preparations->sum('total_sum');

        // Natijani tuzamiz
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

    public function getApplication()
    {
        $applications = Application::all();
        return response()->json($applications);
    }

    public function storeApplication(Request $request)
    {
        $application = Application::create($request->all());
        return response()->json($application, 201);
    }

    public function updateApplication(Request $request, Application $application)
    {
        $application->update($request->all());
        return response()->json($application, 200);
    }

    public function destroy(Application $application)
    {
        $application->delete();
        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\SpecificationCategory;
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

    //[
    //    {
    //        "id": 1,
    //        "name": "TIKAN 1",
    //        "specifications": [
    //            {
    //                "id": 1,
    //                "name": "Specification 1",
    //                "code": "S1",
    //                "quantity": 100,
    //                "comment": "This is a comment"
    //            },
    //            {
    //                "id": 2,
    //                "name": "Specification 2",
    //                "code": "S2",
    //                "quantity": 150,
    //                "comment": "This is a comment"
    //            }
    //        ]
    //    },
    //    {
    //        "id": 1,
    //        "name": "TIKAN 2",
    //        "specifications": [
    //            {
    //                "id": 3,
    //                "name": "Specification 3",
    //                "code": "S3",
    //                "quantity": 100,
    //                "comment": "This is a comment"
    //            },
    //            {
    //                "id": 4,
    //                "name": "Specification 4",
    //                "code": "S4",
    //                "quantity": 150,
    //                "comment": "This is a comment"
    //            }
    //        ]
    //    }
    //]

    public function storeSpecification(Request $request)
    {
        $request->validate([
            '*.id' => 'required|integer|distinct',
            '*.name' => 'required|string',
            '*.submodel_id' => 'required|integer',
            '*.specifications' => 'required|array',
            '*.specifications.*.id' => 'required|integer|distinct',
            '*.specifications.*.name' => 'required|string',
            '*.specifications.*.code' => 'required|string',
            '*.specifications.*.quantity' => 'required|integer|min:0',
            '*.specifications.*.comment' => 'nullable|string',
        ]);

        $data = $request->all();

        foreach ($data as $datum) {
            $specificationCategory = SpecificationCategory::create([
                'name' => $datum['name'],
                'submodel_id' => $datum['submodel_id'],
            ]);

            foreach ($datum['specifications'] as $specification) {
                $specificationCategory->specifications()->create($specification);
            }
        }

        if ($specificationCategory && $specificationCategory->specifications) {
            return response()->json([
                'message' => 'Specifications and SpecificationCategory created successfully'
            ], 201);
        }elseif (!$specificationCategory) {
            return response()->json([
                'message' => 'SpecificationCategory error'
            ], 404);
        }elseif (!$specificationCategory->specifications) {
            return response()->json([
                'message' => 'Specifications error'
            ], 404);
        }else {
            return response()->json([
                'message' => 'Something went wrong'
            ], 500);
        }
    }
}

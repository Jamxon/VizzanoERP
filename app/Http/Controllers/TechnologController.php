<?php

namespace App\Http\Controllers;

use App\Models\PartSpecification;
use App\Models\SpecificationCategory;
use App\Models\SubModel;
use Illuminate\Http\Request;

class TechnologController extends Controller
{
    
    public function storeSpecification(Request $request)
    {
        $request->validate([
            '*.name' => 'required|string',
            '*.submodel_id' => 'required|integer',
            '*.specifications' => 'required|array',
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
                $specifications = PartSpecification::create([
                    'specification_category_id' => $specificationCategory->id,
                    'name' => $specification['name'],
                    'code' => $specification['code'],
                    'quantity' => $specification['quantity'],
                    'comment' => $specification['comment'],
                ]);
            }
        }

        if ($specificationCategory && $specifications) {
            return response()->json([
                'message' => 'Specifications and SpecificationCategory created successfully'
            ], 201);
        }elseif (!$specificationCategory) {
            return response()->json([
                'message' => 'SpecificationCategory error'
            ], 404);
        }elseif (!$specifications) {
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

<?php

namespace App\Http\Controllers;

use App\Models\PartSpecification;
use App\Models\SpecificationCategory;
use Illuminate\Http\Request;

class TechnologController extends Controller
{
    public function getSpecificationBySubmodelId($submodelId): \Illuminate\Http\JsonResponse
    {
        $specifications = SpecificationCategory::where('submodel_id', $submodelId)->with('specifications')->get();

        if ($specifications) {
            return response()->json($specifications, 200);
        }else {
            return response()->json([
                'message' => 'Specifications not found'
            ], 404);
        }
    }
    public function storeSpecification(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (is_null($data)) {
            return response()->json([
                'message' => 'Invalid JSON format',
            ], 400);
        }

        $validatedData = validator($data, [
            'data' => 'required|array',
            'data.*.name' => 'required|string',
            'data.*.submodel_id' => 'required|integer',
            'data.*.specifications' => 'required|array',
            'data.*.specifications.*.name' => 'required|string',
            'data.*.specifications.*.code' => 'required|string',
            'data.*.specifications.*.quantity' => 'required|integer|min:0',
            'data.*.specifications.*.comment' => 'nullable|string',
        ])->validate();

        foreach ($validatedData['data'] as $datum) {
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

        if (isset($specificationCategory) && isset($specifications)) {
            return response()->json([
                'message' => 'Specifications and SpecificationCategory created successfully',
            ], 201);
        } elseif (!isset($specificationCategory)) {
            return response()->json([
                'message' => 'SpecificationCategory error',
            ], 404);
        } elseif (!isset($specifications)) {
            return response()->json([
                'message' => 'Specifications error',
            ], 404);
        } else {
            return response()->json([
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    public function updateSpecification(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'submodel_id' => 'required|integer',
        ]);

        $data = $request->all();

        $specificationCategory = SpecificationCategory::find($id);

        if ($specificationCategory) {
            $specificationCategory->update([
                'name' => $data['name'],
                'submodel_id' => $data['submodel_id'],
            ]);

            PartSpecification::where('specification_category_id', $specificationCategory->id)->delete();

            if (!empty($data['specifications'])) {
                foreach ($data['specifications'] as $specification) {
                    if (!empty($specification['name']) && !empty($specification['code']) && !empty($specification['quantity'])) {
                        PartSpecification::create([
                            'specification_category_id' => $specificationCategory->id,
                            'name' => $specification['name'],
                            'code' => $specification['code'],
                            'quantity' => $specification['quantity'],
                            'comment' => $specification['comment'] ?? null,
                        ]);
                    }
                }
            }

            return response()->json([
                'message' => 'Specifications updated successfully',
            ], 200);
        } else {
            return response()->json([
                'message' => 'SpecificationCategory not found',
            ], 404);
        }
    }

    public function destroySpecificationCategory($id): \Illuminate\Http\JsonResponse
    {
        $specificationCategory = SpecificationCategory::find($id);

        if ($specificationCategory) {
            $specifications = PartSpecification::where('specification_category_id', $id)->get();

            if ($specifications) {
                foreach ($specifications as $specification) {
                    $specification->delete();
                }

                $specificationCategory->delete();

                return response()->json([
                    'message' => 'Specifications and SpecificationCategory deleted successfully'
                ], 200);
            }else {
                return response()->json([
                    'message' => 'Specifications not found'
                ], 404);
            }
        }else {
            return response()->json([
                'message' => 'SpecificationCategory not found'
            ], 404);
        }
    }

    public function destroySpecification($id): \Illuminate\Http\JsonResponse
    {
        $specification = PartSpecification::find($id);

        if ($specification) {
            $specification->delete();

            return response()->json([
                'message' => 'Specification deleted successfully'
            ], 200);
        }else {
            return response()->json([
                'message' => 'Specification not found'
            ], 404);
        }
    }
}
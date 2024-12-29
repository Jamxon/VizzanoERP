<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\PartSpecification;
use App\Models\Razryad;
use App\Models\SpecificationCategory;
use App\Models\Tarification;
use App\Models\TarificationCategory;
use App\Models\TypeWriter;
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

    public function storeTarification(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (is_null($data)) {
            return response()->json([
                'message' => 'Invalid JSON format',
            ], 400);
        }

        // Validatsiya
        $validator = validator($data, [
            'data.*.name' => 'required|string|max:255',
            'data.*.submodel_id' => 'required|integer|exists:sub_models,id',
            'data.*.tarifications' => 'required|array',
            'data.*.tarifications.*.user_id' => 'required|integer|exists:users,id',
            'data.*.tarifications.*.name' => 'required|string|max:255',
            'data.*.tarifications.*.razryad_id' => 'required|integer|exists:razryads,id',
            'data.*.tarifications.*.typewriter_id' => 'required|integer|exists:type_writers,id',
            'data.*.tarifications.*.second' => 'required|numeric|min:0',
        ]);

        // Validatsiya xatolarini tekshirish
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validatsiya qilingan ma'lumotlar
        $validatedData = $validator->validated();

        // Ma'lumotlarni saqlash
        foreach ($validatedData['data'] as $datum) {
            $tarificationCategory = TarificationCategory::create([
                'name' => $datum['name'],
                'submodel_id' => $datum['submodel_id'],
            ]);

            foreach ($datum['tarifications'] as $tarification) {
                Tarification::create([
                    'tarification_category_id' => $tarificationCategory->id,
                    'user_id' => $tarification['user_id'],
                    'name' => $tarification['name'],
                    'razryad_id' => $tarification['razryad_id'],
                    'typewriter_id' => $tarification['typewriter_id'],
                    'second' => $tarification['second'],
                    'summa' => $tarification['second'] * Razryad::find($tarification['razryad_id'])->salary,
                ]);
            }
        }

        return response()->json([
            'message' => 'Tarifications and TarificationCategory created successfully',
        ], 201);
    }

    public function getTarificationBySubmodelId($submodelId): \Illuminate\Http\JsonResponse
    {
        $tarifications = TarificationCategory::where('submodel_id', $submodelId)->with('tarifications')->get();

        if ($tarifications) {
            return response()->json($tarifications, 200);
        }else {
            return response()->json([
                'message' => 'Tarifications not found'
            ], 404);
        }
    }

    public function getEmployerByDepartment()
    {
        $user = auth()->user();

        return Department::where('branch_id', $user->employee->branch_id)
            ->whereIn('id', [1, 2])
            ->with('groups.employees')
            ->get()
            ->flatMap(function ($department) {
                return $department->groups->flatMap(function ($group) {
                    return $group->employees;
                });
            });
    }

    public function getTypeWriter()
    {
        $typeWriters = TypeWriter::all();

        if ($typeWriters) {
            return response()->json($typeWriters, 200);
        }else {
            return response()->json([
                'message' => 'TypeWriters not found'
            ], 404);
        }
    }
}
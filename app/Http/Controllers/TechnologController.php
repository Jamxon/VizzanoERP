<?php

namespace App\Http\Controllers;

use App\Exports\SpecificationCategoryExport;
use App\Exports\TarificationCategoryExport;
use App\Imports\SpecificationCategoryImport;
use App\Imports\TarificationCategoryImport;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderSubModel;
use App\Models\PartSpecification;
use App\Models\Razryad;
use App\Models\SpecificationCategory;
use App\Models\SubmodelSpend;
use App\Models\Tarification;
use App\Models\TarificationCategory;
use App\Models\TypeWriter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

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

    /**
     * @throws ValidationException
     */
    public function storeTarification(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (is_null($data)) {
            return response()->json([
                'message' => 'Invalid JSON format',
            ], 400);
        }

        $validator = validator($data, [
            'data.*.name' => 'required|string|max:255',
            'data.*.submodel_id' => 'required|integer|exists:order_sub_models,id',
            'data.*.tarifications' => 'required|array',
            'data.*.tarifications.*.user_id' => 'nullable|integer|exists:employees,id',
            'data.*.tarifications.*.name' => 'required|string|max:255',
            'data.*.tarifications.*.razryad_id' => 'required|integer|exists:razryads,id',
            'data.*.tarifications.*.typewriter_id' => 'required|integer|exists:type_writers,id',
            'data.*.tarifications.*.second' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        foreach ($validatedData['data'] as $datum) {
            $totalSecond = 0;
            $totalSumma = 0;

            $submodelId = $datum['submodel_id'];

            $tarificationCategory = TarificationCategory::create([
                'name' => $datum['name'],
                'submodel_id' => $submodelId,
            ]);

            foreach ($datum['tarifications'] as $tarification) {
                $razryad = Razryad::find($tarification['razryad_id']);

                if (!$razryad) {
                    return response()->json([
                        'message' => 'Razryad not found',
                    ], 404);
                }

                $summa = $tarification['second'] * $razryad->salary;

                Tarification::create([
                    'tarification_category_id' => $tarificationCategory->id,
                    'name' => $tarification['name'],
                    'user_id' => $tarification['user_id'] ?? null,
                    'razryad_id' => $tarification['razryad_id'],
                    'typewriter_id' => $tarification['typewriter_id'],
                    'second' => $tarification['second'],
                    'summa' => $summa,
                    'code' => $this->generateSequentialCode(),
                ]);

                $totalSecond += $tarification['second'];
                $totalSumma += $summa;
            }

            SubmodelSpend::create([
                'submodel_id' => $submodelId,
                'seconds' => $totalSecond,
                'summa' => $totalSumma,
            ]);
        }

        return response()->json([
            'message' => 'Tarifications and TarificationCategory created successfully',
        ], 201);
    }

    public function updateTarification(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'submodel_id' => 'required|integer|exists:order_sub_models,id',
        ]);

        $data = $request->all();

        $tarificationCategory = TarificationCategory::find($id);

        if (!$tarificationCategory) {
            return response()->json([
                'message' => 'TarificationCategory not found',
            ], 404);
        }

        $tarificationCategory->update([
            'name' => $data['name'],
            'submodel_id' => $data['submodel_id'],
        ]);

        $totalSecond = 0;
        $totalSumma = 0;

        Tarification::where('tarification_category_id', $tarificationCategory->id)->delete();

        if (!empty($data['tarifications'])) {
            foreach ($data['tarifications'] as $tarification) {
                if (!empty($tarification['name']) && !empty($tarification['razryad_id']) && !empty($tarification['typewriter_id']) && !empty($tarification['second'])) {
                    $razryad = Razryad::find($tarification['razryad_id']);

                    if (!$razryad) {
                        return response()->json([
                            'message' => 'Razryad not found',
                        ], 404);
                    }

                    $summa = $tarification['second'] * $razryad->salary;

                    Tarification::create([
                        'tarification_category_id' => $tarificationCategory->id,
                        'name' => $tarification['name'],
                        'user_id' => $tarification['user_id'] ?? null,
                        'razryad_id' => $tarification['razryad_id'],
                        'typewriter_id' => $tarification['typewriter_id'],
                        'second' => $tarification['second'],
                        'summa' => $summa,
                        'code' => $this->generateSequentialCode(),
                    ]);

                    $totalSecond += $tarification['second'];
                    $totalSumma += $summa;
                }
            }
        }

        SubmodelSpend::updateOrCreate(
            ['submodel_id' => $tarificationCategory->submodel_id],
            ['seconds' => $totalSecond, 'summa' => $totalSumma]
        );

        return response()->json([
            'message' => 'Tarifications updated successfully',
        ], 200);
    }

    private function generateSequentialCode(): string
    {
        $lastTarification = Tarification::latest('id')->first();

        if (!$lastTarification) {
            return 'A1';
        }

        $lastCode = $lastTarification->code;

        preg_match('/([A-Z]+)(\d+)/', $lastCode, $matches);

        $letter = $matches[1] ?? 'A';
        $number = (int)($matches[2] ?? 0);

        $number++;

        if ($number > 99) {
            $number = 1;
            $letter = $this->incrementLetter($letter);
        }

        return $letter . $number;
    }

    private function incrementLetter(string $letter): string
    {
        $length = strlen($letter);
        $incremented = false;

        for ($i = $length - 1; $i >= 0; $i--) {
            if ($letter[$i] !== 'Z') {
                $letter[$i] = chr(ord($letter[$i]) + 1);
                $incremented = true;
                break;
            }
            $letter[$i] = 'A';
        }

        if (!$incremented) {
            $letter = 'A' . $letter;
        }

        return $letter;
    }

    public function getTarificationBySubmodelId($submodelId): \Illuminate\Http\JsonResponse
    {
        $tarificationCategories = TarificationCategory::where('submodel_id', $submodelId)
            ->with('tarifications')
            ->get()
            ->makeHidden(['created_at', 'updated_at', 'submodel_id']);
        return response()->json($tarificationCategories, 200);
    }

    public function getTarificationByOrderModelId($orderModelId): \Illuminate\Http\JsonResponse
    {
        $orderSubModel = OrderSubModel::where('order_model_id', $orderModelId)
            ->with('tarificationCategories', 'tarificationCategories.tarifications','submodel')
            ->get();
        return response()->json($orderSubModel, 200 );
    }

    public function getEmployerByDepartment(Request $request): \Illuminate\Http\JsonResponse
    {
        $groupIds = OrderGroup::where('submodel_id', $request->query('id'))
            ->pluck('group_id');

        $employees = Employee::whereIn('group_id', $groupIds)
            ->where('status', 'working')
            ->get();

        return response()->json($employees, 200);
    }

    public function getTypeWriter(): \Illuminate\Http\JsonResponse
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

    public function destroyTarificationCategory($id): \Illuminate\Http\JsonResponse
    {
        $tarificationCategory = TarificationCategory::find($id);

        if ($tarificationCategory) {
            $tarifications = Tarification::where('tarification_category_id', $id)->get();

            if ($tarifications) {
                foreach ($tarifications as $tarification) {
                    $tarification->delete();
                }

                $tarificationCategory->delete();

                return response()->json([
                    'message' => 'Tarifications and TarificationCategory deleted successfully'
                ], 200);
            }else {
                return response()->json([
                    'message' => 'Tarifications not found'
                ], 404);
            }
        }else {
            return response()->json([
                'message' => 'TarificationCategory not found'
            ], 404);
        }
    }

    public function deleteTarification($id): \Illuminate\Http\JsonResponse
    {
        $tarification = Tarification::find($id);

        if ($tarification) {
            $tarification->delete();

            return response()->json([
                'message' => 'Tarification deleted successfully'
            ], 200);
        }else {
            return response()->json([
                'message' => 'Tarification not found'
            ], 404);
        }
    }

    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        $orders = Order::where('branch_id', $user->employee->branch_id)
            ->with('orderModel', 'orderModel.model','orderModel.submodels.submodel')
            ->get();

        return response()->json($orders, 200);
    }

    public function showTarification($id): \Illuminate\Http\JsonResponse
    {
        $tarification = Tarification::find($id);

        if ($tarification) {
            return response()->json($tarification, 200);
        }else {
            return response()->json([
                'message' => 'Tarification not found'
            ], 404);
        }
    }

    /**
     * @throws ValidationException
     */
    public function fasteningToEmployee(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (is_null($data)) {
            return response()->json(['message' => 'Invalid JSON format'], 400);
        }

        $validator = validator($data, [
            'data' => 'required|array',
            'data.*.user_id' => 'required|integer|exists:employees,id',
            'data.*.tarifications' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();
        $userIds = collect($validatedData['data'])->pluck('user_id')->unique();

        Tarification::whereIn('user_id', $userIds)->update(['user_id' => null]);

        foreach ($validatedData['data'] as $datum) {
            $userId = $datum['user_id'];
            $tarifications = $datum['tarifications'];

            Tarification::whereIn('id', $tarifications)->update(['user_id' => $userId]);
        }

        return response()->json(['message' => 'Tarifications fastened to employees successfully'], 200);
    }

    public function exportTarification(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        $orderSubModelId = $request->get('orderSubModelId');

        if (!$orderSubModelId) {
            return response()->json([
                'error' => 'orderSubModelId talab qilinadi.'
            ], 400);
        }

        return Excel::download(new TarificationCategoryExport($orderSubModelId), 'tarification_export_' . $orderSubModelId . '.xlsx');
    }

    public function importTarification(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderSubModelId = $request->get('orderSubModelId');
        $file = $request->file('file');

        dd($request->all());

        if (!$orderSubModelId || !$file) {
            return response()->json([
                'error' => 'orderSubModelId va fayl majburiy.'
            ], 400);
        }

        try {
            Excel::import(new TarificationCategoryImport($orderSubModelId), $file);
            return response()->json([
                'message' => 'Import muvaffaqiyatli bajarildi.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Import jarayonida xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportSpecification(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        $orderSubmodelId = $request->get('orderSubmodelId');

        if (!$orderSubmodelId) {
            return response()->json([
                'error' => 'orderSubmodelId talab qilinadi.'
            ], 400);
        }

        return Excel::download(new SpecificationCategoryExport($orderSubmodelId), 'specification_export_' . $orderSubmodelId . '.xlsx');
    }

    public function importSpecification(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderSubmodelId = $request->get('orderSubmodelId');
        $file = $request->file('file');

        if (!$orderSubmodelId || !$file) {
            return response()->json([
                'error' => 'orderSubmodelId va fayl majburiy.'
            ], 400);
        }

        try {
            Excel::import(new SpecificationCategoryImport($orderSubmodelId), $file);
            return response()->json([
                'message' => 'Import muvaffaqiyatli bajarildi.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Import jarayonida xatolik: ' . $e->getMessage()
            ], 500);
        }
    }
}
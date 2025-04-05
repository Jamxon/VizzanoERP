<?php

namespace App\Http\Controllers;

use App\Exports\SpecificationCategoryExport;
use App\Exports\TarificationCategoryExport;
use App\Imports\SpecificationCategoryImport;
use App\Imports\TarificationCategoryImport;
use App\Models\Employee;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderModel;
use App\Models\OrderSubModel;
use App\Models\PartSpecification;
use App\Models\Razryad;
use App\Models\SpecificationCategory;
use App\Models\SubmodelSpend;
use App\Models\Tarification;
use App\Models\TarificationCategory;
use App\Models\TypeWriter;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TechnologController extends Controller
{
    public function showSpecificationCategory($id)
    {
return        $specificationCategory = SpecificationCategory::find($id)
            ->with('specifications');

        if ($specificationCategory) {
            return response()->json($specificationCategory, 200);
        } else {
            return response()->json([
                'message' => 'Specification category not found'
            ], 404);
        }
    }

    public function showTarificationCategory($id): \Illuminate\Http\JsonResponse
    {
        $tarificationCategory = TarificationCategory::find($id)
            ->with(
                'tarifications',
                'tarifications.employee',
                'tarifications.razryad',
                'tarifications.typewriter',
            );

        if ($tarificationCategory) {
            return response()->json($tarificationCategory, 200);
        } else {
            return response()->json([
                'message' => 'Tarification category not found'
            ], 404);
        }
    }

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

        // Kiruvchi so‘rovni loglash
        Log::add(
            auth()->id(),
            'Spetsifikatsiya saqlash so‘rovi qabul qilindi',
            'attempt',
            null,
            $data
        );

        if (is_null($data)) {
            Log::add(
                auth()->id(),
                'JSON formati noto‘g‘ri',
                'attempt',
                null,
                $request->getContent()
            );

            return response()->json([
                'message' => 'JSON formati noto‘g‘ri',
            ], 400);
        }

        try {
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::add(
                auth()->id(),
                'Spetsifikatsiya validatsiyasida xatolik',
                'attempt',
                null,
                $e->errors()
            );

            return response()->json([
                'message' => 'Validatsiya xatoligi',
                'errors' => $e->errors(),
            ], 422);
        }

        $createdData = [];

        foreach ($validatedData['data'] as $datum) {
            $specificationCategory = SpecificationCategory::create([
                'name' => $datum['name'],
                'submodel_id' => $datum['submodel_id'],
            ]);

            $specifications = [];
            foreach ($datum['specifications'] as $specification) {
                $spec = PartSpecification::create([
                    'specification_category_id' => $specificationCategory->id,
                    'name' => $specification['name'],
                    'code' => $specification['code'],
                    'quantity' => $specification['quantity'],
                    'comment' => $specification['comment'] ?? null,
                ]);
                $specifications[] = $spec;
            }

            $createdData[] = [
                'category' => $specificationCategory,
                'specifications' => $specifications
            ];
        }

        // Muvaffaqiyatli saqlash logi
        Log::add(
            auth()->id(),
            'Spetsifikatsiyalar muvaffaqiyatli saqlandi',
            'create',
            null,
            $createdData);

        if (isset($specificationCategory) && isset($specifications)) {
            return response()->json([
                'message' => 'Spetsifikatsiyalar va kategoriya muvaffaqiyatli yaratildi',
            ], 201);
        } elseif (!isset($specificationCategory)) {
            Log::add(
                auth()->id(),
                'Spetsifikatsiya kategoriyasi yaratishda xatolik',
                'attempt',
                null,
                null);

            return response()->json([
                'message' => 'Kategoriya yaratishda xatolik yuz berdi',
            ], 404);
        } elseif (!isset($specifications)) {
            Log::add(auth()->id(), 'Spetsifikatsiyalar yaratishda xatolik', 'attempt', null, null);

            return response()->json([
                'message' => 'Spetsifikatsiyalar yaratilmadi',
            ], 404);
        } else {
            Log::add(auth()->id(), 'Nomaʼlum xatolik yuz berdi', 'attempt', null, null);

            return response()->json([
                'message' => 'Nomaʼlum xatolik yuz berdi',
            ], 500);
        }
    }

    public function updateSpecification(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        // So‘rovni validatsiya qilish
        $request->validate([
            'name' => 'required|string',
            'submodel_id' => 'required|integer',
        ]);

        $data = $request->all();

        // Spetsifikatsiya kategoriyasini topish
        $specificationCategory = SpecificationCategory::find($id);

        // Log uchun eski maʼlumotlarni olish
        $oldData = null;
        if ($specificationCategory) {
            $oldSpecifications = $specificationCategory->specifications()->get()->toArray();
            $oldData = [
                'category' => $specificationCategory->toArray(),
                'specifications' => $oldSpecifications
            ];
        } else {
            Log::add(auth()->id(), "Spetsifikatsiya kategoriyasi topilmadi (ID: $id)", 'attempt', null, null);

            return response()->json([
                'message' => 'Spetsifikatsiya kategoriyasi topilmadi',
            ], 404);
        }

        // Yangilash jarayoni
        $specificationCategory->update([
            'name' => $data['name'],
            'submodel_id' => $data['submodel_id'],
        ]);

        // Eski spetsifikatsiyalarni o‘chirish
        PartSpecification::where('specification_category_id', $specificationCategory->id)->delete();

        // Yangi spetsifikatsiyalarni qo‘shish
        $newSpecifications = [];
        if (!empty($data['specifications'])) {
            foreach ($data['specifications'] as $specification) {
                if (!empty($specification['name']) && !empty($specification['code']) && !empty($specification['quantity'])) {
                    $spec = PartSpecification::create([
                        'specification_category_id' => $specificationCategory->id,
                        'name' => $specification['name'],
                        'code' => $specification['code'],
                        'quantity' => $specification['quantity'],
                        'comment' => $specification['comment'] ?? null,
                    ]);
                    $newSpecifications[] = $spec;
                }
            }
        }

        // Log yozish: eski va yangi holatlarni saqlash
        $newData = [
            'category' => $specificationCategory->fresh()->toArray(),
            'specifications' => $newSpecifications
        ];

        Log::add(auth()->id(), 'Spetsifikatsiya yangilandi', 'edit', $oldData, $newData);

        return response()->json([
            'message' => 'Spetsifikatsiya muvaffaqiyatli yangilandi',
        ], 200);
    }

    public function destroySpecificationCategory($id): \Illuminate\Http\JsonResponse
    {
        $specificationCategory = SpecificationCategory::find($id);

        if (!$specificationCategory) {
            Log::add(auth()->id(), "Spetsifikatsiya kategoriyasi topilmadi (ID: $id)", 'attempt',null, null);

            return response()->json([
                'message' => 'Spetsifikatsiya kategoriyasi topilmadi'
            ], 404);
        }

        $specifications = PartSpecification::where('specification_category_id', $id)->get();

        // Log uchun eski maʼlumotlar
        $oldData = [
            'category' => $specificationCategory->toArray(),
            'specifications' => $specifications->toArray()
        ];

        if ($specifications->isNotEmpty()) {
            foreach ($specifications as $specification) {
                $specification->delete();
            }
        }

        $specificationCategory->delete();

        // Log yozish: o‘chirish amaliyoti
        Log::add(auth()->id(), 'Spetsifikatsiya kategoriyasi va unga tegishli maʼlumotlar o‘chirildi', 'delete', $oldData, null);

        return response()->json([
            'message' => 'Spetsifikatsiya va u bilan bogʻliq maʼlumotlar muvaffaqiyatli o‘chirildi'
        ], 200);
    }

    public function destroySpecification($id): \Illuminate\Http\JsonResponse
    {
        $specification = PartSpecification::find($id);

        if ($specification) {
            // Log uchun eski maʼlumot
            $oldData = $specification->toArray();

            $specification->delete();

            // Log yozish
            Log::add(auth()->id(), "Spetsifikatsiya o‘chirildi (ID: $id)", 'delete', $oldData, null);

            return response()->json([
                'message' => 'Spetsifikatsiya muvaffaqiyatli o‘chirildi'
            ], 200);
        } else {
            Log::add(auth()->id(), "Spetsifikatsiya topilmadi (ID: $id)", 'attempt',null, null);

            return response()->json([
                'message' => 'Spetsifikatsiya topilmadi'
            ], 404);
        }
    }

    public function storeTarification(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Kiruvchi so‘rovni loglash
        Log::add(
            auth()->id(),
            'Tarifikatsiya saqlash so‘rovi qabul qilindi',
            'attempt',
            null,
            $data
        );

        if (is_null($data)) {
            Log::add(auth()->id(), 'Tarifikatsiya yaratishda JSON formati noto‘g‘ri', 'attempt', null, null);

            return response()->json([
                'message' => 'JSON formati noto‘g‘ri',
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
            Log::add(auth()->id(), 'Tarifikatsiya yaratishda validatsiya xatoliklari', 'attempt', null, $validator->errors()->toArray());

            return response()->json([
                'message' => 'Validatsiya xatolari mavjud',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();
        $createdData = [];

        foreach ($validatedData['data'] as $datum) {
            $totalSecond = 0;
            $totalSumma = 0;

            $submodelId = $datum['submodel_id'];

            $tarificationCategory = TarificationCategory::create([
                'name' => $datum['name'],
                'submodel_id' => $submodelId,
            ]);

            $tarifications = [];
            foreach ($datum['tarifications'] as $tarification) {
                $razryad = Razryad::find($tarification['razryad_id']);

                if (!$razryad) {
                    Log::add(auth()->id(), 'Razryad topilmadi (ID: ' . $tarification['razryad_id'] . ')', 'attempt', null, null);

                    return response()->json([
                        'message' => 'Razryad topilmadi',
                    ], 404);
                }

                $summa = $tarification['second'] * $razryad->salary;

                $tarif = Tarification::create([
                    'tarification_category_id' => $tarificationCategory->id,
                    'name' => $tarification['name'],
                    'user_id' => $tarification['user_id'] ?? null,
                    'razryad_id' => $tarification['razryad_id'],
                    'typewriter_id' => $tarification['typewriter_id'],
                    'second' => $tarification['second'],
                    'summa' => $summa,
                    'code' => $this->generateSequentialCode(),
                ]);

                $tarifications[] = $tarif;

                $totalSecond += $tarification['second'];
                $totalSumma += $summa;
            }

            $submodelSpend = SubmodelSpend::create([
                'submodel_id' => $submodelId,
                'seconds' => $totalSecond,
                'summa' => $totalSumma,
            ]);

            $createdData[] = [
                'category' => $tarificationCategory,
                'tarifications' => $tarifications,
                'submodel_spend' => $submodelSpend
            ];
        }

        // Log yozish
        Log::add(auth()->id(), 'Tarifikatsiya muvaffaqiyatli yaratildi', 'create', null, $createdData);

        return response()->json([
            'message' => 'Tarifikatsiya va kategoriya muvaffaqiyatli yaratildi',
        ], 201);
    }

    public function updateTarification(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'submodel_id' => 'required|integer|exists:order_sub_models,id',
            ]);

            $data = $request->all();

            // Eski ma'lumotlarni olish (log uchun)
            $oldData = null;
            $tarificationCategory = $id ? TarificationCategory::find($id) : null;
            if ($tarificationCategory) {
                $oldTarifications = $tarificationCategory->tarifications()->get()->toArray();
                $oldSubmodelSpend = SubmodelSpend::where('submodel_id', $tarificationCategory->submodel_id)->first();
                $oldData = [
                    'category' => $tarificationCategory->toArray(),
                    'tarifications' => $oldTarifications,
                    'submodel_spend' => $oldSubmodelSpend ? $oldSubmodelSpend->toArray() : null
                ];
            }

            if (!$tarificationCategory) {
                $tarificationCategory = TarificationCategory::create([
                    'name' => $data['name'],
                    'submodel_id' => $data['submodel_id'],
                ]);
                Log::add(auth()->id(), 'Yangi tarifikatsiya kategoriyasi yaratildi', 'create', null, $tarificationCategory->toArray());
            } else {
                $tarificationCategory->update([
                    'name' => $data['name'],
                    'submodel_id' => $data['submodel_id'],
                ]);
            }

            $totalSecond = 0;
            $totalSumma = 0;
            $updatedTarifications = [];

            if (!empty($data['tarifications'])) {
                foreach ($data['tarifications'] as $tarification) {
                    if (!isset($tarification['name'], $tarification['razryad_id'], $tarification['typewriter_id'], $tarification['second'])) {
                        continue;
                    }

                    $razryad = Razryad::find($tarification['razryad_id']);
                    if (!$razryad) {
                        Log::add(auth()->id(), 'Tarifikatsiya yangilanishida razryad topilmadi (ID: ' . $tarification['razryad_id'] . ')', 'attempt',null, null);
                        return response()->json(['message' => 'Razryad topilmadi'], 404);
                    }

                    $summa = $tarification['second'] * $razryad->salary;

                    $tarificationRecord = Tarification::updateOrCreate(
                        ['id' => $tarification['id'] ?? null],
                        [
                            'tarification_category_id' => $tarificationCategory->id,
                            'name' => $tarification['name'],
                            'user_id' => $tarification['user_id'] ?? null,
                            'razryad_id' => $tarification['razryad_id'],
                            'typewriter_id' => $tarification['typewriter_id'],
                            'second' => $tarification['second'],
                            'summa' => $summa,
                            'code' => $this->generateSequentialCode(),
                        ]
                    );

                    $updatedTarifications[] = $tarificationRecord;

                    $totalSecond += $tarification['second'];
                    $totalSumma += $summa;
                }
            }

            $submodelSpend = SubmodelSpend::updateOrCreate(
                ['submodel_id' => $tarificationCategory->submodel_id],
                ['seconds' => $totalSecond, 'summa' => $totalSumma]
            );

            // Log yozish
            $newData = [
                'category' => $tarificationCategory->fresh()->toArray(),
                'tarifications' => $updatedTarifications,
                'submodel_spend' => $submodelSpend->fresh()->toArray()
            ];
            Log::add(auth()->id(), 'Tarifikatsiya muvaffaqiyatli yangilandi', 'edit', $oldData, $newData);

            return response()->json(['message' => 'Tarifikatsiyalar muvaffaqiyatli yangilandi'], 200);
        } catch (\Exception $e) {
            Log::add(auth()->id(), 'Tarifikatsiya yangilanishida xatolik: ' . $e->getMessage(), 'attempt',null, null);

            return response()->json(['message' => 'Xatolik yuz berdi: ' . $e->getMessage()], 500);
        }
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

    public function getEmployerByDepartment(Request $request): \Illuminate\Http\JsonResponse
    {
        $submodelId = $request->query('id');
        $groupIds = OrderGroup::where('submodel_id', $submodelId)
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

    public function storeTypeWriter(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (is_null($data)) {
            return response()->json(['message' => 'Noto‘g‘ri JSON format'], 400);
        }

        $validator = validator($data, [
            'name' => 'required|string|max:255',
            'comment' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validatsiya xatoliklari',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        $typeWriter = TypeWriter::create($validatedData);

        // Log yozish
        Log::add(auth()->id(), 'Tikuv mashina muvaffaqiyatli saqlandi', 'create', null, $typeWriter->toArray());

        return response()->json([
            'message' => 'TypeWriter muvaffaqiyatli saqlandi',
            'data' => $typeWriter
        ], 201);
    }

    public function updateTypeWriter(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (is_null($data)) {
            return response()->json(['message' => 'Noto‘g‘ri JSON format'], 400);
        }

        $validator = validator($data, [
            'id' => 'required|integer|exists:type_writers,id',
            'name' => 'required|string|max:255',
            'comment' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validatsiya xatoliklari',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        $typeWriter = TypeWriter::find($validatedData['id']);
        $typeWriter->update($validatedData);

        // Log yozish
        Log::add(auth()->id(), 'Tikuv mashina muvaffaqiyatli yangilandi', 'edit', null, $typeWriter->toArray());

        return response()->json([
            'message' => 'TypeWriter muvaffaqiyatli yangilandi',
            'data' => $typeWriter
        ], 200);
    }

    public function destroyTarificationCategory($id): \Illuminate\Http\JsonResponse
    {
        $tarificationCategory = TarificationCategory::find($id);

        if ($tarificationCategory) {
            $tarifications = Tarification::where('tarification_category_id', $id)->get();

            // Eski ma'lumotlarni log uchun olish
            $oldData = [
                'category' => $tarificationCategory->toArray(),
                'tarifications' => $tarifications->toArray()
            ];

            if ($tarifications) {
                foreach ($tarifications as $tarification) {
                    $tarification->delete();
                }

                $tarificationCategory->delete();

                // Log yozish
                Log::add(auth()->id(), 'Tarifikatsiya kategoriyasi va tarifikatsiyalar o‘chirildi', 'delete',$oldData, null);

                return response()->json([
                    'message' => 'Tarifikatsiyalar va kategoriya muvaffaqiyatli o‘chirildi'
                ], 200);
            } else {
                Log::add(auth()->id(), 'Tarifikatsiyalar topilmadi, lekin kategoriya mavjud', 'attempt', $oldData, null);

                return response()->json([
                    'message' => 'Tarifikatsiyalar topilmadi'
                ], 404);
            }
        } else {
            Log::add(auth()->id(), 'Tarifikatsiya kategoriyasi topilmadi (ID: ' . $id . ')', 'attempt',null, null);

            return response()->json([
                'message' => 'Tarifikatsiya kategoriyasi topilmadi'
            ], 404);
        }
    }

    public function deleteTarification($id): \Illuminate\Http\JsonResponse
    {
        $tarification = Tarification::find($id);

        if ($tarification) {
            // Eski ma'lumotlarni log uchun olish
            $oldData = $tarification->toArray();

            $tarification->delete();

            // Log yozish
            Log::add(auth()->id(), 'Tarifikatsiya o‘chirildi', 'delete', $oldData, null);

            return response()->json([
                'message' => 'Tarifikatsiya muvaffaqiyatli o‘chirildi'
            ], 200);
        } else {
            Log::add(auth()->id(), 'Tarifikatsiya topilmadi (ID: ' . $id . ')', 'attempt',null, null);

            return response()->json([
                'message' => 'Tarifikatsiya topilmadi'
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

    public function fasteningToEmployee(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (is_null($data)) {
            return response()->json(['message' => 'Noto‘g‘ri JSON format'], 400);
        }

        $validator = validator($data, [
            'data' => 'required|array',
            'data.*.user_id' => 'required|integer|exists:employees,id',
            'data.*.tarifications' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validatsiya xatoliklari',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();
        $userIds = collect($validatedData['data'])->pluck('user_id')->unique();

        // Eski ma'lumotlarni log uchun olish
        $oldData = [];
        foreach ($userIds as $userId) {
            $oldTarifications = Tarification::where('user_id', $userId)->get();
            if ($oldTarifications->count() > 0) {
                $oldData[$userId] = $oldTarifications->toArray();
            }
        }

        // Avvalgi bog‘lamalarni tozalash
        Tarification::whereIn('user_id', $userIds)->update(['user_id' => null]);

        $newData = [];
        foreach ($validatedData['data'] as $datum) {
            $userId = $datum['user_id'];
            $tarifications = $datum['tarifications'];

            Tarification::whereIn('id', $tarifications)->update(['user_id' => $userId]);

            $updatedTarifications = Tarification::whereIn('id', $tarifications)->get();
            if ($updatedTarifications->count() > 0) {
                $newData[$userId] = $updatedTarifications->toArray();
            }
        }

        // Log yozish
        Log::add(auth()->id(), 'Tarifikatsiyalar xodimga biriktirildi', 'assign', $oldData, $newData);

        return response()->json(['message' => 'Tarifikatsiyalar muvaffaqiyatli biriktirildi'], 200);
    }

    public function exportTarification(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $orderSubModelId = $request->get('orderSubModelId');

            if (!$orderSubModelId) {
                return response()->json(['error' => 'orderSubModelId talab qilinadi.'], 400);
            }

            $orderSubmodel = OrderSubModel::findOrFail($orderSubModelId);
            $orderModel = OrderModel::findOrFail($orderSubmodel->order_model_id);
            $order = Order::findOrFail($orderModel->order_id);

            Log::add(auth()->id(), 'Tarifikatsiyani eksport qilindi', 'export', null, [
                'orderSubModelId' => $orderSubModelId,
                'order_name' => $order->name,
                'submodel_name' => $orderSubmodel->submodel->name
            ]);

            return Excel::download(new TarificationCategoryExport($orderSubModelId),  $order->id . ' ' . $orderSubmodel->submodel->name .  '.xlsx');

        } catch (\Exception $e) {
            Log::add(auth()->id(), 'Xatolik: Tarifikatsiyani eksport qilishda', 'error', $e->getMessage());
            return response()->json(['error' => 'Xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function importTarification(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $orderSubModelId = $request->get('orderSubmodelId');
            $file = $request->file('file');

            if (!$orderSubModelId || !$file) {
                return response()->json(['error' => 'orderSubModelId va fayl majburiy.'], 400);
            }

            $orderSubmodel = OrderSubModel::findOrFail($orderSubModelId);
            $orderModel = OrderModel::findOrFail($orderSubmodel->order_model_id);
            $order = Order::findOrFail($orderModel->order_id);

            Excel::import(new TarificationCategoryImport($orderSubModelId), $file);

            Log::add(auth()->id(), 'Import tarification', 'import', null, [
                'orderSubModelId' => $orderSubModelId,
                'order_name' => $order->name,
                'submodel_name' => $orderSubmodel->submodel->name,
                'filename' => $file->getClientOriginalName()
            ]);

            return response()->json(['message' => 'Import muvaffaqiyatli bajarildi.'], 200);

        } catch (\Exception $e) {
            Log::add(auth()->id(), 'Xatolik: Tarifikatsiyani import qilishda', 'error', $e->getMessage());
            return response()->json(['error' => 'Import xatoligi: ' . $e->getMessage()], 500);
        }
    }

    public function exportSpecification(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $orderSubModelId = $request->get('orderSubModelId');

            if (!$orderSubModelId) {
                return response()->json(['error' => 'orderSubModelId talab qilinadi.'], 400);
            }

            $orderSubmodel = OrderSubModel::findOrFail($orderSubModelId);
            $orderModel = OrderModel::findOrFail($orderSubmodel->order_model_id);
            $order = Order::findOrFail($orderModel->order_id);

            Log::add(auth()->id(), 'Spesifikatsiya export qilindi', 'export', null, [
                'orderSubModelId' => $orderSubModelId,
                'order_name' => $order->name,
                'submodel_name' => $orderSubmodel->submodel->name
            ]);

            return Excel::download(new SpecificationCategoryExport($orderSubModelId), 'specification_export_' . $orderSubModelId . '.xlsx');

        } catch (\Exception $e) {
            Log::add(auth()->id(), 'Xatolik: Spesifikatsiyani eksport qilishda', 'error', $e->getMessage());
            return response()->json(['error' => 'Xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function importSpecification(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $orderSubmodelId = $request->get('orderSubmodelId');
            $file = $request->file('file');

            if (!$orderSubmodelId || !$file) {
                return response()->json(['error' => 'orderSubmodelId va fayl majburiy.'], 400);
            }

            $orderSubmodel = OrderSubModel::findOrFail($orderSubmodelId);
            $orderModel = OrderModel::findOrFail($orderSubmodel->order_model_id);
            $order = Order::findOrFail($orderModel->order_id);

            Excel::import(new SpecificationCategoryImport($orderSubmodelId), $file);

            Log::add(auth()->id(), 'Import specification', 'import', null, [
                'orderSubmodelId' => $orderSubmodelId,
                'order_name' => $order->name,
                'submodel_name' => $orderSubmodel->submodel->name,
                'filename' => $file->getClientOriginalName()
            ]);

            return response()->json(['message' => 'Import muvaffaqiyatli bajarildi.'], 200);

        } catch (\Exception $e) {
            Log::add(auth()->id(), 'Xatolik: Spesifikatsiyani import qilishda', 'error', $e->getMessage());
            return response()->json(['error' => 'Import xatoligi: ' . $e->getMessage()], 500);
        }
    }
}
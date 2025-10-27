<?php

namespace App\Http\Controllers;

use App\Exports\SpecificationCategoryExport;
use App\Exports\TarificationCategoryExport;
use App\Models\Employee;
use App\Models\EmployeeTarificationLog;
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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class TechnologController extends Controller
{
    public function getLastTarifications()
    {
        return Tarification::select('id', 'name', 'razryad_id', 'typewriter_id', 'second',)
            ->orderBy('id', 'desc')
            ->take(1000)
            ->get();
    }

    public function confirmOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderId = $request->input('order_id');

        if (!$orderId) {
            return response()->json(['message' => 'Order ID is required'], 400);
        }

        $orderModel = OrderModel::where('order_id', $orderId)->first();
        $orderModel->status = true;
        $orderModel->rasxod = $request->rasxod ?? $orderModel->rasxod;
        $orderModel->save();

        Log::add(
            auth()->id(),
            'Buyurtma narxi tasdiqlandi',
            'update',
            null,
            ['order_id' => $orderId]
        );

        return response()->json(['message' => 'Order confirmed successfully'], 200);
    }

    public function showSpecificationCategory($id): \Illuminate\Http\JsonResponse
    {
       $specificationCategory = SpecificationCategory::where('id', $id)
           ->with(
               'specifications',
           )->first();

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
        $tarificationCategory = TarificationCategory::where('id', $id)
            ->with(
                'tarifications',
                'tarifications.employee',
                'tarifications.razryad',
                'tarifications.typewriter',
            )->first();

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
                '*.name' => 'required|string',
                '*.submodel_id' => 'required|integer',
                '*.specifications' => 'required|array',
                '*.specifications.*.name' => 'required|string',
                '*.specifications.*.code' => 'required|string',
                '*.specifications.*.quantity' => 'required|integer|min:0',
                '*.specifications.*.comment' => 'nullable|string',
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

        foreach ($validatedData as $datum) {
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
        $request->validate([
            'name' => 'required|string',
            'submodel_id' => 'required|integer',
        ]);

        $data = $request->all();

        $specificationCategory = SpecificationCategory::find($id);

        if (!$specificationCategory) {
            Log::add(auth()->id(), "Spetsifikatsiya kategoriyasi topilmadi (ID: $id)", 'attempt', null, null);
            return response()->json([
                'message' => 'Spetsifikatsiya kategoriyasi topilmadi',
            ], 404);
        }

        // Kategoriya o‘zgarishlaridan oldingi holat
        $oldCategory = $specificationCategory->getOriginal();

        // Kategoriya ma’lumotlarini yangilash
        $specificationCategory->update([
            'name' => $data['name'],
            'submodel_id' => $data['submodel_id'],
        ]);

        $existingSpecs = $specificationCategory->specifications->keyBy('id');

        $oldSpecsChanged = [];
        $newSpecsChanged = [];

        if (!empty($data['specifications'])) {
            foreach ($data['specifications'] as $spec) {
                if (!empty($spec['name']) && !empty($spec['code']) && !empty($spec['quantity'])) {
                    if (!empty($spec['id']) && isset($existingSpecs[$spec['id']])) {
                        $existing = $existingSpecs[$spec['id']];

                        // Faqat haqiqatan o‘zgarganlarini tekshirish
                        $hasChanges = $existing->name !== $spec['name'] ||
                            $existing->code !== $spec['code'] ||
                            $existing->quantity != $spec['quantity'] ||
                            $existing->comment !== ($spec['comment'] ?? null);

                        if ($hasChanges) {
                            $oldSpecsChanged[] = $existing->toArray();

                            $existing->update([
                                'name' => $spec['name'],
                                'code' => $spec['code'],
                                'quantity' => $spec['quantity'],
                                'comment' => $spec['comment'] ?? null,
                            ]);

                            $newSpecsChanged[] = $existing->fresh()->toArray();
                        }
                    } else {
                        // Yangi spesifikatsiya yaratish
                        $created = PartSpecification::create([
                            'specification_category_id' => $specificationCategory->id,
                            'name' => $spec['name'],
                            'code' => $spec['code'],
                            'quantity' => $spec['quantity'],
                            'comment' => $spec['comment'] ?? null,
                        ]);
                        $newSpecsChanged[] = $created->toArray();
                    }
                }
            }
        }

        // Faqat o‘zgargan yoki yangi qo‘shilgan elementlar logga yoziladi
        $logOldData = [
            'category' => $oldCategory,
            'specifications' => $oldSpecsChanged,
        ];

        $logNewData = [
            'category' => $specificationCategory->fresh()->toArray(),
            'specifications' => $newSpecsChanged,
        ];

        Log::add(auth()->id(), 'Spetsifikatsiya yangilandi', 'edit', $logOldData, $logNewData);

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
            '*.name' => 'required|string|max:255',
            '*.submodel_id' => 'required|integer|exists:order_sub_models,id',
            '*.region' => 'nullable|string|max:255',
            '*.tarifications' => 'required|array',
            '*.tarifications.*.employee_id' => 'nullable|integer|exists:employees,id',
            '*.tarifications.*.name' => 'required|string|max:255',
            '*.tarifications.*.razryad_id' => 'required|integer|exists:razryads,id',
            '*.tarifications.*.typewriter_id' => 'nullable|integer|exists:type_writers,id',
            '*.tarifications.*.second' => 'required|numeric|min:0',
            '*.tarifications.*.video_id' => 'nullable|integer|exists:videos,id'
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

        foreach ($validatedData as $datum) {
            $totalSecond = 0;
            $totalSumma = 0;

            $submodelId = $datum['submodel_id'];

            $tarificationCategory = TarificationCategory::create([
                'name' => $datum['name'],
                'submodel_id' => $submodelId,
                'region' => $datum['region'] ?? null,
            ]);
            $oldSubmodelSpend = SubmodelSpend::where('submodel_id', $tarificationCategory->submodel_id)
                ->where('region', $data['region'] ?? null)
                ->first();

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
                    'user_id' => $tarification['employee_id'] ?? null,
                    'razryad_id' => $tarification['razryad_id'],
                    'typewriter_id' => $tarification['typewriter_id'] ?? null,
                    'second' => $tarification['second'],
                    'summa' => $summa,
                    'code' => $this->generateSequentialCode(),
                    'video_id' => $tarification['video_id']
                ]);

                $tarifications[] = $tarif;

                $totalSecond += $tarification['second'];
                $totalSumma += $summa;
            }

            $submodelSpend = SubmodelSpend::firstOrNew([
                'submodel_id' => $tarificationCategory->submodel_id,
                'region' => $datum['region'] ?? null,
            ]);

            $submodelSpend->seconds = ($submodelSpend->exists ? $submodelSpend->seconds : 0) + $totalSecond;
            $submodelSpend->summa = ($submodelSpend->exists ? $submodelSpend->summa : 0) + $totalSumma;

            $submodelSpend->save();


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
                'region' => 'nullable|string|max:255',
            ]);

            $data = $request->all();

            $tarificationCategory = TarificationCategory::find($id);
            $oldTarifications = [];
            $oldSubmodelSpend = null;
            $oldCategory = null;

            if ($tarificationCategory) {
                $oldCategory = $tarificationCategory->getOriginal();
                $oldTarifications = $tarificationCategory->tarifications()->get()->keyBy('id');
                $oldSubmodelSpend = SubmodelSpend::where('submodel_id', $tarificationCategory->submodel_id)
                    ->where('region', $data['region'] ?? null)
                    ->first();
            }

            if (!$tarificationCategory) {
                $tarificationCategory = TarificationCategory::create([
                    'name' => $data['name'],
                    'submodel_id' => $data['submodel_id'],
                    'region' => $data['region'] ?? null,
                ]);

                Log::add(auth()->id(), 'Yangi tarifikatsiya kategoriyasi yaratildi', 'create', null, $tarificationCategory->toArray());
            } else {
                $tarificationCategory->update([
                    'name' => $data['name'],
                    'submodel_id' => $data['submodel_id'],
                ]);
            }

            $oldTarificationsChanged = [];
            $newTarificationsChanged = [];

            if (!empty($data['tarifications'])) {
                foreach ($data['tarifications'] as $tarification) {
                    if (!isset($tarification['name'], $tarification['razryad_id'], $tarification['second'])) {
                        continue;
                    }

                    $razryad = Razryad::find($tarification['razryad_id']);
                    if (!$razryad) {
                        Log::add(auth()->id(), 'Tarifikatsiya yangilanishida razryad topilmadi (ID: ' . $tarification['razryad_id'] . ')', 'attempt', null, null);
                        return response()->json(['message' => 'Razryad topilmadi'], 404);
                    }

                    $summa = $tarification['second'] * $razryad->salary;

                    $existing = isset($tarification['id']) && $oldTarifications->has($tarification['id'])
                        ? $oldTarifications[$tarification['id']] : null;

                    if ($existing) {
                        $hasChanges = $existing->name !== $tarification['name'] ||
                            $existing->razryad_id != $tarification['razryad_id'] ||
                            $existing->typewriter_id != $tarification['typewriter_id'] ||
                            $existing->second != $tarification['second'] ||
                            $existing->video_id != $tarification['video_id'] ||
                            $existing->user_id != ($tarification['employee_id'] ?? null);

                        if ($hasChanges) {
                            $oldTarificationsChanged[] = $existing->toArray();

                            $existing->update([
                                'name' => $tarification['name'],
                                'user_id' => $tarification['employee_id'] ?? null,
                                'razryad_id' => $tarification['razryad_id'],
                                'typewriter_id' => $tarification['typewriter_id'] ?? null,
                                'second' => $tarification['second'],
                                'summa' => $summa,
                                'code' => $tarification['code'] ?? $this->generateSequentialCode(),
                                'video_id' => $tarification['video_id']
                            ]);

                            $newTarificationsChanged[] = $existing->fresh()->toArray();

                            // 🔄 EmployeeTarificationLogs dagi summani yangilash
                            EmployeeTarificationLog::where('tarification_id', $existing->id)
                                ->update([
                                    'amount_earned' => DB::raw("quantity * {$summa}")
                                ]);
                        }
                    } else {
                        $created = Tarification::create([
                            'tarification_category_id' => $tarificationCategory->id,
                            'name' => $tarification['name'],
                            'user_id' => $tarification['employee_id'] ?? null,
                            'razryad_id' => $tarification['razryad_id'],
                            'typewriter_id' => $tarification['typewriter_id'] ?? null,
                            'second' => $tarification['second'],
                            'summa' => $summa,
                            'code' => $tarification['code'] ?? $this->generateSequentialCode(),
                            'video_id' => $tarification['video_id']
                        ]);

                        $newTarificationsChanged[] = $created->toArray();
                    }
                }
            }

            // 🔁 SubmodelSpend qayta hisoblash — butun submodel bo‘yicha
            $allTarifications = Tarification::whereHas('tarificationCategory', function ($q) use ($tarificationCategory, $data) {
                $q->where('submodel_id', $tarificationCategory->submodel_id)
                    ->where('region', $data['region'] ?? null);
            })->with('razryad')->get();

            $totalSecond = $allTarifications->sum('second');
            $totalSumma = $allTarifications->sum(function ($t) {
                return $t->second * ($t->razryad->salary ?? 0);
            });

            $submodelSpend = SubmodelSpend::updateOrCreate(
                [
                    'submodel_id' => $tarificationCategory->submodel_id,
                    'region' => $data['region'] ?? null,
                ],
                [
                    'seconds' => $totalSecond,
                    'summa' => $totalSumma,
                ]
            );

            $submodelSpendChanged = [];
            if ($oldSubmodelSpend) {
                if ($oldSubmodelSpend->seconds != $totalSecond || $oldSubmodelSpend->summa != $totalSumma) {
                    $submodelSpendChanged['old'] = $oldSubmodelSpend->toArray();
                    $submodelSpendChanged['new'] = $submodelSpend->fresh()->toArray();
                }
            } else {
                $submodelSpendChanged['new'] = $submodelSpend->fresh()->toArray();
            }

            $logOldData = [
                'category' => $oldCategory,
                'tarifications' => $oldTarificationsChanged,
                'submodel_spend' => $submodelSpendChanged['old'] ?? null
            ];

            $logNewData = [
                'category' => $tarificationCategory->fresh()->toArray(),
                'tarifications' => $newTarificationsChanged,
                'submodel_spend' => $submodelSpendChanged['new'] ?? null
            ];

            Log::add(auth()->id(), 'Tarifikatsiya muvaffaqiyatli yangilandi', 'edit', $logOldData, $logNewData);

            return response()->json(['message' => 'Tarifikatsiyalar muvaffaqiyatli yangilandi'], 200);
        } catch (\Exception $e) {
            Log::add(auth()->id(), 'Tarifikatsiya yangilanishida xatolik: ' . $e->getMessage(), 'attempt', null, null);

            return response()->json(['message' => 'Xatolik yuz berdi: ' . $e->getMessage()], 500);
        }
    }

    private function generateSequentialCode(): string
    {
        $lastTarification = Tarification::latest('id')->first();

        $letter = 'A';
        $number = 0;

        if ($lastTarification && preg_match('/^([A-Z]+)(\d+)$/', $lastTarification->code, $matches)) {
            $letter = $matches[1];
            $number = (int)$matches[2];
        }

        // Loop: takrorlanuvchi code bo'lsa, keyingisiga o't
        do {
            $number++;

            if ($number > 999) {
                $number = 1;
                $letter = $this->incrementLetter($letter);
            }

            $code = $letter . $number;

        } while (Tarification::where('code', $code)->exists());

        return $code;
    }

    private function incrementLetter(string $letter): string
    {
        $chars = str_split($letter);
        $i = count($chars) - 1;

        while ($i >= 0) {
            if ($chars[$i] !== 'Z') {
                $chars[$i] = chr(ord($chars[$i]) + 1);
                break;
            }

            $chars[$i] = 'A';
            $i--;
        }

        if ($i < 0) {
            array_unshift($chars, 'A');
        }

        return implode('', $chars);
    }

    public function getTarificationBySubmodelId($submodelId): \Illuminate\Http\JsonResponse
    {
        $tarificationCategories = TarificationCategory::where('submodel_id', $submodelId)
            ->with('tarifications')
            ->get()
            ->makeHidden(['created_at', 'updated_at', 'submodel_id']);

        return response()->json($tarificationCategories, 200);
    }

    public function getEmployerByDepartment(): \Illuminate\Http\JsonResponse
    {

        $employees = Employee::where('status', 'working')
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->with('group')
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

        $oldData = $typeWriter->toArray();
        $typeWriter->update($validatedData);

        // Log yozish
        Log::add(auth()->id(), 'Tikuv mashina muvaffaqiyatli yangilandi', 'edit', $oldData, $typeWriter->toArray());

        return response()->json([
            'message' => 'TypeWriter muvaffaqiyatli yangilandi',
            'data' => $typeWriter
        ], 200);
    }

    public function destroyTarificationCategory($id): \Illuminate\Http\JsonResponse
    {
        $tarificationCategory = TarificationCategory::find($id);

        if (!$tarificationCategory) {
            Log::add(auth()->id(), 'Tarifikatsiya kategoriyasi topilmadi (ID: ' . $id . ')', 'attempt', null, null);
            return response()->json(['message' => 'Tarifikatsiya kategoriyasi topilmadi'], 404);
        }

        $tarifications = Tarification::where('tarification_category_id', $id)->get();
        $oldData = [
            'category' => $tarificationCategory->toArray(),
            'tarifications' => $tarifications->toArray()
        ];

        if ($tarifications->isNotEmpty()) {
            $totalSeconds = $tarifications->sum('second');
            $totalSumma = $tarifications->sum('summa');

            $submodelId = $tarificationCategory->submodel_id;
            $region = $tarificationCategory->region;

            $spend = \App\Models\SubmodelSpend::where('submodel_id', $submodelId)->where('region', $region)->first();

            if ($spend) {
                $spend->decrement('seconds', $totalSeconds);
                $spend->decrement('summa', $totalSumma);
            }

            foreach ($tarifications as $tarification) {
                $tarification->delete();
            }

            $tarificationCategory->delete();

            Log::add(auth()->id(), 'Tarifikatsiya kategoriyasi va tarifikatsiyalar o‘chirildi', 'delete', $oldData, null);

            return response()->json(['message' => 'Tarifikatsiyalar va kategoriya muvaffaqiyatli o‘chirildi'], 200);
        } else {
            Log::add(auth()->id(), 'Tarifikatsiyalar topilmadi, lekin kategoriya mavjud', 'attempt', $oldData, null);
            return response()->json(['message' => 'Tarifikatsiyalar topilmadi'], 404);
        }
    }

    public function deleteTarification($id): \Illuminate\Http\JsonResponse
    {
        $tarification = Tarification::find($id);

        if (!$tarification) {
            Log::add(auth()->id(), 'Tarifikatsiya topilmadi (ID: ' . $id . ')', 'attempt', null, null);
            return response()->json(['message' => 'Tarifikatsiya topilmadi'], 404);
        }

        $oldData = $tarification->toArray();

        $category = $tarification->tarificationCategory;
        $submodelId = $category->submodel_id;
        $region = $category->region;

        $spend = \App\Models\SubmodelSpend::where('submodel_id', $submodelId)->where('region', $region)->first();

        if ($spend) {
            $spend->decrement('seconds', $tarification->second);
            $spend->decrement('summa', $tarification->summa);
        }

        $tarification->delete();

        Log::add(auth()->id(), 'Tarifikatsiya o‘chirildi', 'delete', $oldData, null);

        return response()->json(['message' => 'Tarifikatsiya muvaffaqiyatli o‘chirildi'], 200);
    }

    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        $orders = Order::where('branch_id', $user->employee->branch_id)
            ->with([
                'orderModel.model:id,name',
                'orderModel.submodels:id,order_model_id,submodel_id',
                'orderModel.submodels.submodel:id,name',
            ])
            ->with(['orderModel.submodels' => function ($q) {
                $q->withCount('tarifications'); // SQL darajasida hisoblaydi
            }])
            ->get();

        // Endi PHP’da loop shart emas, faqat arrayga qo‘shib qo‘yamiz
        $orders = $orders->map(function ($order) {
            $orderData = $order->toArray();

            foreach ($orderData['order_model']['submodels'] as &$submodelItem) {
                $submodelItem['has_tarifications'] = $submodelItem['tarifications_count'] > 0;
                unset($submodelItem['tarifications_count']); // keraksiz ustunni olib tashlaymiz
            }

            return $orderData;
        });

        return response()->json($orders, 200, [], JSON_UNESCAPED_UNICODE);
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
            $region = $request->get('region');

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

            return Excel::download(new TarificationCategoryExport($orderSubModelId, $region),  $order->id . ' ' . $orderSubmodel->submodel->name .  '.xlsx');

        } catch (\Exception $e) {
            Log::add(auth()->id(), 'Xatolik: Tarifikatsiyani eksport qilishda', 'error', $e->getMessage());
            return response()->json(['error' => 'Xatolik: ' . $e->getMessage()], 500);
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
        $file = $request->file('file');

        if (!$file) {
            return response()->json(['message' => 'Fayl topilmadi!'], 400);
        }

        $submodelId = $request->input('submodel_id');

        if (!$submodelId) {
            return response()->json(['message' => 'submodel_id kerak!'], 400);
        }

        $rows = Excel::toArray([], $file);
        $sheet = $rows[0];

        DB::beginTransaction();

        try {
            $currentCategory = null;
            $skipNext = false;

            foreach ($sheet as $row) {
                $row = array_map('trim', $row);

                if (empty($row) || count(array_filter($row)) === 0) {
                    continue;
                }

                // Agar 1-ustun bo‘sh emas va boshqa ustunlar bo‘sh bo‘lsa => bu category nomi
                if (!empty($row[0]) && empty($row[1]) && empty($row[2]) && empty($row[3])) {
                    $currentCategory = \App\Models\SpecificationCategory::create([
                        'name' => $row[0],
                        'submodel_id' => $submodelId,
                    ]);
                    $skipNext = true; // keyingi qatorda ustun nomlari bor, uni tashlab ketamiz
                    continue;
                }

                if ($skipNext) {
                    $skipNext = false;
                    continue; // ustun nomlari qatori
                }

                // "Итого" kabi yozuvlarni tashlab ketamiz
                if (isset($row[0]) && preg_match('/итого/ui', $row[0])) {
                    continue;
                }

                // Ma'lumotni saqlash
                if ($currentCategory && isset($row[0], $row[1], $row[2])) {
                    \App\Models\PartSpecification::create([
                        'specification_category_id' => $currentCategory->id,
                        'code' => $row[0],
                        'name' => $row[1],
                        'quantity' => isset($row[2]) && is_numeric($row[2]) ? (float) $row[2] : 0,
                        'comment' => $row[3] ?? null,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Import muvaffaqiyatli yakunlandi!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function importTarifications(Request $request): \Illuminate\Http\JsonResponse
    {
        ini_set('memory_limit', '512M');

        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            return response()->json(['message' => 'Fayl yuklashda xatolik'], 422);
        }

        $file = $request->file('file');
        $submodelId = $request->input('submodel_id');

        $region = $request->input('region');

        if (empty($submodelId)) {
            return response()->json(['message' => 'submodel_id ko\'rsatilmagan'], 422);
        }

        try {
            $extension = strtolower($file->getClientOriginalExtension());
            if ($extension === 'xlsx') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);
            } elseif ($extension === 'xls') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            } elseif ($extension === 'ods') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Ods();
            } else {
                return response()->json(['message' => 'Yaroqsiz fayl formati'], 422);
            }

            $spreadsheet = $reader->load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();

            $sheet = [];
            $highestRow = $worksheet->getHighestRow();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                $worksheet->getHighestColumn()
            );

            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cell = $worksheet->getCellByColumnAndRow($col, $row);
                    $cellValue = $cell->getCalculatedValue();
                    if ($cellValue === null) {
                        $cellValue = $cell->getValue();
                    }

                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $rowData[$colLetter] = $cellValue;
                }
                $sheet[$row] = $rowData;
            }

            if (empty($sheet) || count($sheet) < 3) {
                return response()->json(['message' => 'Faylda maʼlumot yoʻq yoki format noto‘g‘ri'], 422);
            }

            DB::beginTransaction();

            $maxRow = max(array_keys($sheet));
            $currentCategory = null;
            $sectionPrefix = null;
            $totalSecond = 0;
            $totalSumma = 0;

            // Iterate through all rows
            for ($rowNum = 1; $rowNum <= $maxRow; $rowNum++) {
                $row = $sheet[$rowNum] ?? [];

                // Check for new category
                if (!empty($row['C']) && empty($row['A']) && empty($row['B']) && empty($row['D'])) {
                    $categoryName = trim($row['C']);

                    $currentCategory = TarificationCategory::create([
                        'name' => $categoryName,
                        'submodel_id' => $submodelId,
                        'region' => $region,
                    ]);
                    $sectionPrefix = null;
                    continue;
                }

                // If new section prefix
                if (empty($row['A']) && empty($row['B']) && !empty($row['C']) && empty($row['D'])) {
                    $sectionPrefix = trim($row['C']);
                    continue;
                }

                // Tarification row
                if (!empty($row['C']) && $currentCategory) {
                    $rawA = $row['A'] ?? null;
                    $seconds = is_numeric(str_replace(',', '.', (string)$rawA)) ?
                        (float) str_replace(',', '.', (string)$rawA) : 0;

                    $description = trim((string)$row['C']);
                    if (!empty($sectionPrefix) && !str_contains($description, $sectionPrefix)) {
                        $description = "{$sectionPrefix} - {$description}";
                    }

                    $razryadName = $row['D'] ?? '1';
                    $razryad = Razryad::where('name', $razryadName)->first();

                    if (!$razryad) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Razryad topilmadi: '{$razryadName}' satrda: {$rowNum}",
                        ], 422);
                    }

                    $razryadId = $razryad?->id;

                    $costs = $seconds * ($razryad?->salary ?? 0);

                    Tarification::create([
                        'tarification_category_id' => $currentCategory->id,
                        'user_id' => null,
                        'name' => $description,
                        'razryad_id' => $razryadId,
                        'typewriter_id' => null,
                        'second' => $seconds,
                        'summa' => $costs,
                        'code' => $this->generateSequentialCode(),
                    ]);

                    $totalSecond += $seconds;
                    $totalSumma += $costs;
                }
            }


            // SubmodelSpend ni yangilash
            $submodelSpend = SubmodelSpend::where('submodel_id', $submodelId)
            ->where('region', $region)
            ->first();
            if ($submodelSpend) {
                $submodelSpend->update([
                    'seconds' => $submodelSpend->seconds + $totalSecond,
                    'summa' => $submodelSpend->summa + $totalSumma,
                ]);
            } else {
                SubmodelSpend::create([
                    'submodel_id' => $submodelId,
                    'seconds' => $totalSecond,
                    'summa' => $totalSumma,
                    'region' => $region,
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Tarifikatsiyalar muvaffaqiyatli import qilindi.']);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Xatolik yuz berdi!',
                'error' => $e->getMessage(),
                'trace' => collect($e->getTrace())->take(5)->toArray(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function exportTarificationsPdf(Request $request): \Illuminate\Http\Response
    {
        $request->validate([
            'submodel_id' => 'required|exists:order_sub_models,id',
            'region' => 'nullable|string|max:255',
        ]);

        $submodel = OrderSubmodel::findOrFail($request->submodel_id);

        // region bo'yicha tarificationCategories ni olish
        $filteredCategories = $submodel->tarificationCategories()
            ->when($request->filled('region'), function ($query) use ($request) {
                $query->where('region', $request->region);
            })
            ->with([
                'tarifications.razryad',
                'tarifications.typewriter',
                'tarifications.employee:id,name'
            ])
            ->get();

        $submodel->tarificationCategories = $filteredCategories;
        $model = $submodel->orderModel->model->name;

        $pdf = Pdf::loadView('pdf.tarifications', [
            'submodel' => $submodel,
            'model' => $model,
        ])
            ->setPaper('A4', 'portrait');

        // Render PDF to access all pages
        $dompdf = $pdf->getDomPDF();
        $dompdf->render(); // <-- ShART!

        // Get canvas and font
        $canvas = $dompdf->get_canvas();
        $font = $dompdf->getFontMetrics()->get_font("DejaVu Sans", "normal");

        // Add footer text to ALL pages
        $canvas->page_text(
            520, // X o‘qi (chapdan)
            820, // Y o‘qi (yuqoridan)
            "Sahifa {PAGE_NUM} / {PAGE_COUNT}",
            $font,
            10,
            [0, 0, 0] // qora rang
        );

        return $pdf->download("tarifikatsiya_ro'yxati.pdf");
    }

    public function exportPdf(Request $request): \Illuminate\Http\Response
    {
        $request->validate([
            'submodel_id' => 'required|exists:order_sub_models,id',
            'size' => 'required',
            'quantity' => 'required',
        ]);

        $submodel = OrderSubmodel::with([
            'tarificationCategories.tarifications.razryad',
            'tarificationCategories.tarifications.typewriter',
            'tarificationCategories.tarifications.employee:id,name'
        ])->findOrFail($request->submodel_id);

        $pdf = Pdf::loadView('pdf.tarifications-pdf', [
            'submodel' => $submodel
        ])->setPaper('A4', 'portrait');

        return $pdf->download("tarifikatsiya_ro'yxati.pdf");
    }

    public function storeVideo(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:mp4,mov,avi|max:512000', // o'lcham MB bilan (512000 KB = 500MB)
            'title' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('videos', $filename, 's3');
        Storage::disk('s3')->setVisibility($path, 'public');
        $url = Storage::disk('s3')->url($path);

        $video = \App\Models\Video::create([
            'title' => $request->title,
            'link' => $url,
            'branch_id' => auth()->user()->employee->branch_id
        ]);

        return response()->json($video, 201);
    }

    public function getVideos()
    {
        $videos = \App\Models\Video::where('branch_id', auth()->user()->employee->branch_id)
                                ->with('tarifications')
                                ->paginate(20);

        return response()->json($videos);
    }

}
<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransportResource;
use App\Http\Resources\TransportResourceCollection;
use App\Models\Transport;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TransportController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $query = Transport::where('branch_id', auth()->user()->employee->branch_id);

            if ($request->has('date')) {
                try {
                    $date = Carbon::createFromFormat('Y-m', $request->input('date'));
                    $year = $date->year;
                    $month = $date->month;

                    // payments
                    $query->with(['payments' => function ($q) use ($year, $month) {
                        $q->whereDate('date', '>=', Carbon::create($year, $month, 1)->startOfDay())
                            ->whereDate('date', '<=', Carbon::create($year, $month, 1)->endOfMonth());
                    },
                        'employees'
                    ]);

                } catch (\Exception $e) {
                    return response()->json(['error' => 'Noto‘g‘ri sana formati. To‘g‘ri format: YYYY-MM yoki YYYY-MM-DD'], 422);
                }
            }

            $transports = $query->orderBy('id', 'desc')->get();
            $resource = TransportResource::collection($transports);

            return response()->json($resource);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Ma\'lumotlarni olishda xatolik yuz berdi'], 500);
        }
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'state_number' => 'required|string|unique:transport,state_number',
            'driver_full_name' => 'required|string',
            'phone' => 'required|string',
            'phone_2' => 'nullable|string',
            'capacity' => 'required|numeric',
            'region_id' => 'nullable|exists:routes,id',
            'region_name' => 'nullable|string',
            'salary' => 'nullable|numeric',
            'fuel_bonus' => 'nullable|numeric',
            'distance' => 'nullable|numeric',
            'is_active' => 'boolean',
        ]);

        try {
            // Agar region_id yo‘q bo‘lsa, region_name orqali yangi Region yaratamiz
            if (empty($data['region_id']) && !empty($data['region_name'])) {
                $region = \App\Models\Region::firstOrCreate(
                    ['name' => $data['region_name']],
                );
                $data['region_id'] = $region->id;
            }

            unset($data['region_name']); // region_name kerak emas modelga

            $transport = Transport::create([
                'name' => $data['name'],
                'state_number' => $data['state_number'],
                'driver_full_name' => $data['driver_full_name'],
                'phone' => $data['phone'],
                'phone_2' => $data['phone_2'],
                'capacity' => $data['capacity'],
                'branch_id' => auth()->user()->employee->branch_id,
                'region_id' => $data['region_id'],
                'is_active' => $data['is_active'],
                'salary' => $data['salary'],
                'fuel_bonus' => $data['fuel_bonus'],
                'distance' => $data['distance'],
            ]);

            Log::add(Auth::id(), 'Transport qo‘shildi', 'create', null, $transport);

            return response()->json($transport, 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Saqlashda xatolik yuz berdi',
                'error' => $e
            ], 500);
        }
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        try {
            $transport = Transport::where('id', $id)
                ->where('branch_id', auth()->user()->employee->branch_id)
                ->with(['employees'])
                ->firstOrFail();

            return (new TransportResource($transport))->response();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Ma\'lumot topilmadi'], 404);
        }
    }

    public function transportShow(Transport $transport): \Illuminate\Http\JsonResponse
    {
        $transport->load(['region','transportAttendances','payments', 'employees.department', 'employees.group']);

        return response()->json($transport);
    }

    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        try {
            $transport = Transport::findOrFail($id);
            $oldData = $transport->toArray();

            $data = $request->validate([
                'name' => 'sometimes|string',
                'state_number' => 'sometimes|string|unique:transport,state_number,' . $id,
                'driver_full_name' => 'sometimes|string',
                'phone' => 'sometimes|string',
                'phone_2' => 'nullable|string',
                'capacity' => 'sometimes|numeric',
                'region_id' => 'nullable|exists:routes,id',
                'region_name' => 'nullable',
                'is_active' => 'boolean',
                'salary' => 'nullable|numeric',
                'fuel_bonus' => 'nullable|numeric',
            ]);

            if (empty($data['region_id']) && !empty($data['region_name'])) {
                $region = \App\Models\Region::firstOrCreate(
                    ['name' => $data['region_name']],
                );
                $data['region_id'] = $region->id;
            }

            unset($data['region_name']); // region_name kerak emas modelga

            $transport->update($data);

            Log::add(Auth::id(), 'Transport tahrirlandi', 'edit', $oldData, $transport);

            return response()->json($transport);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Tahrirlashda xatolik yuz berdi', 'error' => $e->getMessage()], 500);
        }
    }

    public function employeeTransportStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'transport_id' => 'required|exists:transport,id',
        ]);

        try {
            $transport = Transport::findOrFail($data['transport_id']);
            $employee = \App\Models\Employee::findOrFail($data['employee_id']);

            // ✅ Filialni tekshirish
            if ($employee->branch_id !== auth()->user()->employee->branch_id) {
                return response()->json([
                    'error' => 'Siz faqat o‘z filialingizdagi xodimlarni bog‘lashingiz mumkin'
                ], 403);
            }

            // ✅ Transport sig‘imini tekshirish
            $currentCount = $transport->employees()->count();
            if ($currentCount >= $transport->capacity) {
                return response()->json([
                    'error' => "Transport sig‘imi to‘ldi! ({$transport->capacity} o‘rin)"
                ], 409);
            }

            // ✅ Xodim allaqachon boshqa transportga bog‘langanmi?
            $currentTransport = $employee->transports()->first();
            if ($currentTransport) {
                if ($currentTransport->id === $transport->id) {
                    return response()->json([
                        'error' => 'Xodim allaqachon ushbu transportga bog‘langan'
                    ], 409);
                }

                return response()->json([
                    'error' => "Xodim allaqachon boshqa transportga biriktirilgan (ID: {$currentTransport->id})"
                ], 409);
            }

            // ✅ Yangi transportga biriktirish
            $transport->employees()->attach($employee->id);

            Log::add(
                Auth::id(),
                'Xodim transportga bog‘landi',
                'link',
                null,
                ['employee_id' => $employee->id, 'transport_id' => $transport->id]
            );

// Hozirgi band o‘rinlarni hisoblash
            $occupied = $currentCount + 1;
            $totalCapacity = $transport->capacity;

            return response()->json([
                'message' => "Xodim muvaffaqiyatli transportga bog‘landi. (Hozir {$occupied}/{$totalCapacity})"
            ], 200);


        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Xatolik yuz berdi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateEmployeeTransport(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'transport_id' => 'required|exists:transport,id',
        ]);

        try {
            $updated = \DB::table('employee_transport')
                ->where('employee_id', $data['employee_id'])
                ->update([
                    'transport_id' => $data['transport_id'],
                    'updated_at' => now(),
                ]);

            if (!$updated) {
                return response()->json(['error' => 'Xodim transportga bog‘lanmagan'], 404);
            }

            return response()->json(['message' => 'Transport muvaffaqiyatli yangilandi'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Xatolik yuz berdi: ' . $e->getMessage()], 500);
        }
    }

    public function deleteEmployeeTransport(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        try {
            $deleted = \DB::table('employee_transport')
                ->where('employee_id', $data['employee_id'])
                ->delete();

            if (!$deleted) {
                return response()->json(['error' => 'Xodim transportga bog‘lanmagan'], 404);
            }

            return response()->json(['message' => 'Xodimning transport bilan bog‘lanishi muvaffaqiyatli o‘chirildi'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Xatolik yuz berdi: ' . $e->getMessage()], 500);
        }
    }
}
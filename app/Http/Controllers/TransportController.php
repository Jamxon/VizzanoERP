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
                    }]);

                    // employee_transport_daily
                    if ($request->filled('day')) {
                        // agar aniq sana berilsa (masalan 2025-09-22)
                        $day = Carbon::createFromFormat('Y-m-d', $request->input('day'));
                        $query->with(['dailyEmployees' => function ($q) use ($day) {
                            $q->where('date', $day);
                        }]);
                    } else {
                        // agar faqat oy bo‘lsa, butun oydagi yozuvlar
                        $query->with(['dailyEmployees' => function ($q) use ($year, $month) {
                            $q->whereBetween('date', [
                                Carbon::create($year, $month, 1)->startOfDay(),
                                Carbon::create($year, $month, 1)->endOfMonth(),
                            ]);
                        }]);
                    }

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
                ->firstOrFail();

            return (new TransportResource($transport))->response();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Ma\'lumot topilmadi'], 404);
        }
    }

    public function transportShow(Transport $transport): \Illuminate\Http\JsonResponse
    {
        $transport->load(['region','transportAttendances','payments']);

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
}
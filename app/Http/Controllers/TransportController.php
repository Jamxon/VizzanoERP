<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransportResource;
use App\Http\Resources\TransportResourceCollection;
use App\Models\Transport;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransportController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        try {
            $transports = Transport::where('branch_id', auth()->user()->employee->branch_id)
                ->orderBy('id', 'desc')
                ->get();
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
            'is_active' => 'boolean',
            'vin_number' => 'nullable|string',
            'tech_passport_number' => 'nullable|string',
            'engine_number' => 'nullable|string',
            'year' => 'nullable|integer',
            'color' => 'nullable|string',
            'registration_date' => 'nullable|date',
            'insurance_expiry' => 'nullable|date',
            'inspection_expiry' => 'nullable|date',
            'driver_passport_number' => 'nullable|string',
            'driver_license_number' => 'nullable|string',
            'driver_experience_years' => 'nullable|integer',
            'salary' => 'nullable|numeric',
            'fuel_bonus' => 'nullable|numeric',
            'distance' => 'nullable|numeric',
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
                'vin_number' => $data['vin_number'],
                'tech_passport_number' => $data['tech_passport_number'],
                'engine_number' => $data['engine_number'],
                'year' => $data['year'],
                'color' => $data['color'],
                'registration_date' => $data['registration_date'],
                'insurance_expiry' => $data['insurance_expiry'],
                'inspection_expiry' => $data['inspection_expiry'],
                'driver_passport_number' => $data['driver_passport_number'],
                'driver_license_number' => $data['driver_license_number'],
                'driver_experience_years' => $data['driver_experience_years'],
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
                'region_id' => 'sometimes|exists:routes,id',
                'is_active' => 'boolean',
                'vin_number' => 'nullable|string',
                'tech_passport_number' => 'nullable|string',
                'engine_number' => 'nullable|string',
                'year' => 'nullable|integer',
                'color' => 'nullable|string',
                'registration_date' => 'nullable|date',
                'insurance_expiry' => 'nullable|date',
                'inspection_expiry' => 'nullable|date',
                'driver_passport_number' => 'nullable|string',
                'driver_license_number' => 'nullable|string',
                'driver_experience_years' => 'nullable|integer',
                'salary' => 'nullable|numeric',
                'fuel_bonus' => 'nullable|numeric',
            ]);

            $transport->update($data);

            Log::add(Auth::id(), 'Transport tahrirlandi', 'edit', $oldData, $transport);

            return response()->json($transport);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Tahrirlashda xatolik yuz berdi'], 500);
        }
    }
}
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
            'branch_id' => 'required|exists:branches,id',
            'region_id' => 'nullable|exists:regions,id',
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
        ]);

        try {
            // Agar region_id yo‘q bo‘lsa, region_name orqali yangi Region yaratamiz
            if (empty($data['region_id']) && !empty($data['region_name'])) {
                $region = \App\Models\Region::firstOrCreate(
                    ['name' => $data['region_name']],
                    ['branch_id' => auth()->user()->employee->branch_id]
                );
                $data['region_id'] = $region->id;
            }

            unset($data['region_name']); // region_name kerak emas modelga

            $transport = Transport::create($data);

            Log::add(Auth::id(), 'Transport qo‘shildi', 'create', null, $transport);

            return response()->json($transport, 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Saqlashda xatolik yuz berdi'], 500);
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
                'branch_id' => 'sometimes|exists:branches,id',
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
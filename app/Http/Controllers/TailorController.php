<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShowOrderForTailorResource;
use App\Models\Employee;
use App\Models\EmployeeTarificationLog;
use App\Models\Log;
use App\Models\OrderGroup;
use App\Models\Tarification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TailorController extends Controller
{
    public function searchTarifications(Request $request): \Illuminate\Http\JsonResponse
    {
        $code = $request->input('code');

        $tarifications = Tarification::where('code', $code)
            ->with([
                'employee',
                'razryad',
                'typewriter',
                'tarificationLogs' => function ($query) {
                    $query->whereDate('date', now()->format('Y-m-d'));
                }
            ])
            ->get();

        return response()->json($tarifications);
    }

    public function getDailyBalanceEmployee(): \Illuminate\Http\JsonResponse
    {
        $today = now()->format('Y-m-d');

        $employeeTarificationLogs = EmployeeTarificationLog::where('date', $today)
            ->where('employee_id', auth()->user()->employee->id)
            ->without(['tarification.tarificationCategory'])
            ->get();

        $resource = $employeeTarificationLogs->map(function ($log) {
            return [
                'id' => $log->id,
                'tarification' => [
                    'id' => $log->tarification->id,
                    'name' => $log->tarification->name,
                    'second' => $log->tarification->second,
                    'summa' => $log->tarification->summa,
                    'code' => $log->tarification->code,
                    'razryad' => $log->tarification->razryad ?? null,
                    'typewriter' => $log->tarification->typewriter ?? null,
                ],
                'employee' => $log->employee,
                'date' => $log->date,
                'is_own' => $log->is_own,
                'amount_earned' => $log->amount_earned,
                'quantity' => $log->quantity,
                'model' => $log->tarification->tarificationCategory->submodel->orderModel->model ?? null,
            ];
        });

        return response()->json($resource);
    }

    public function storeTarificationLog(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'tarification_id' => 'required|exists:tarifications,id',
        ]);

        $employee = auth()->user()->employee;
        $employeeId = $employee->id;
        $today = now()->toDateString();

        $tarification = Tarification::find($validated['tarification_id']);
        $amount = $tarification->summa;
        $isOwn = $tarification->employee_id === $employeeId;

        DB::beginTransaction();

        try {
            // Avval mavjud logni tekshiramiz
            $log = EmployeeTarificationLog::where('employee_id', $employeeId)
                ->where('tarification_id', $tarification->id)
                ->where('date', $today)
                ->first();

            if ($log) {
                // Old ma'lumotlar log uchun
                $oldData = $log->toArray();

                // Mavjud logni yangilaymiz (qo‘shamiz)
                $log->quantity += 1;
                $log->amount_earned += $amount;
                $log->save();

                // Employee balansini ham oshiramiz
                Employee::where('id', $employeeId)->increment('balance', $amount);

                // Yangi holat log uchun
                $newData = $log->toArray();

                // Umumiy logga yozamiz
                Log::add(auth()->id(), 'TarificationLog yangilandi', 'tarification_log', $oldData, $newData);
            } else {
                // Yangi log yoziladi
                $log = EmployeeTarificationLog::create([
                    'employee_id'     => $employeeId,
                    'tarification_id' => $tarification->id,
                    'date'            => $today,
                    'quantity'        => 1,
                    'is_own'          => $isOwn,
                    'amount_earned'   => $amount,
                ]);

                // Balansni oshirish
                Employee::where('id', $employeeId)->increment('balance', $amount);

                // Logga yozamiz
                Log::add(auth()->id(), 'TarificationLog qo\'shildi', 'tarification_log', null, $log->toArray());
            }

            DB::commit();

            return response()->json([
                'message' => 'Tarification log processed successfully.',
                'log' => $log,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getModelWithTarification(): \Illuminate\Http\JsonResponse
    {
        $group = auth()->user()->employee->group;

        $order = OrderGroup::where('group_id', $group->id)
            ->whereHas('order', function ($query) {
                $query->whereIn('status', ['tailoring', 'tailored', 'pending', 'cutting']);
            })
            ->with(['order.orderModel.model', 'order.orderModel.submodels.submodel'])
            ->get();

        $resource = ShowOrderForTailorResource::collection($order);

        return response()->json($resource);
    }

}
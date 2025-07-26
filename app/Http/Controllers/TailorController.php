<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShowOrderForTailorResource;
use App\Models\Employee;
use App\Models\EmployeeTarificationLog;
use App\Models\Log;
use App\Models\OrderGroup;
use App\Models\Tarification;
use App\Models\TarificationPacket;
use App\Models\TarificationPacketItem;
use Carbon\Carbon;
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

    public function getDailyBalanceEmployee(Request $request): \Illuminate\Http\JsonResponse
    {
        $today = $request->date ?? now()->format('Y-m-d');

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

        $orderQuantity = $tarification->tarificationCategory->submodel->orderModel->order->quantity ?? 0;

        $employeeIds = EmployeeTarificationLog::where('tarification_id', $tarification->id)
            ->whereDate('date', $today)
            ->pluck('employee_id')
            ->unique();

        if ($employeeIds->count() > 1 || ($employeeIds->count() === 1 && $employeeIds->first() !== $employee->id)) {

            // Umumiy bajarilgan miqdor
            $alreadyDone = EmployeeTarificationLog::where('tarification_id', $tarification->id)
                ->whereDate('date', $today)
                ->sum('quantity');

            if (($alreadyDone + 1) > $orderQuantity) {
                return response()->json([
                    'message' => "❌ [{$tarification->name}] uchun limitdan oshib ketdi. Ruxsat: $orderQuantity, bajarilgan: $alreadyDone, qo‘shilmoqchi: 1"
                ], 422);
            }
        }

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
        $group = auth()->user()->employee->group ?? null;

        $order = OrderGroup::where('group_id', $group->id ?? 0)
            ->whereHas('order', function ($query) {
                $query->whereIn('status', ['tailoring', 'tailored', 'pending', 'cutting']);
            })
            ->with(['order.orderModel.model', 'order.orderModel.submodels.submodel'])
            ->get();

        $resource = ShowOrderForTailorResource::collection($order);

        return response()->json($resource);
    }

    public function getTopEarners(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->date ?? Carbon::today()->toDateString();
        $branchId = auth()->user()->employee->branch_id;

        // 1. Har bir employee va tarification bo‘yicha grouping
        $logs = DB::table('employee_tarification_logs')
            ->select(
                'employee_id',
                'tarification_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(amount_earned) as total_earned')
            )
            ->whereDate('date', $date)
            ->whereIn('employee_id', Employee::where('branch_id', $branchId)->pluck('id'))
            ->groupBy('employee_id', 'tarification_id')
            ->get();

        // 2. Daromad bo‘yicha employee-larni guruhlab umumiy topganini hisoblash
        $grouped = $logs->groupBy('employee_id')->map(function ($items, $employeeId) {
            $totalEarned = $items->sum('total_earned');

            $details = $items->map(function ($item) {
                $tarification = Tarification::with('tarificationCategory.submodel')->find($item->tarification_id);
                return [
                    'tarification_id' => $item->tarification_id,
                    'operation' => $tarification?->name,
                    'second' => $tarification?->second,
                    'code' => $tarification?->code,
                    'quantity' => $item->total_quantity,
                    'earned' => $item->total_earned,
                ];
            });

            $employee = Employee::find($employeeId);

            return [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name ?? '---',
                'image' => $employee->img ?? null,
                'group' => $employee->group->name ?? '---',
                'total_earned' => $totalEarned,
                'works' => $details,
            ];
        });

        // 3. Eng ko‘p topgan 10 nafar xodimni olish
        $topEarners = $grouped->sortByDesc('total_earned')->values();

        return response()->json([
            'date' => $date,
            'top_earners' => $topEarners,
        ]);
    }

    public function getTarificationPackets(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->date ?? Carbon::today()->toDateString();
        $employeeId = auth()->user()->employee->id;

        $tarificationPackets = TarificationPacket::where('employee_id', $employeeId)
            ->where('date', $date)
            ->with([
                'tarificationPacketsItems.tarification',
                'tarificationPacketsItems.tarification.razryad',
            ])
            ->get();

       return response()->json($tarificationPackets);
    }

    public function storeTarificationPackets(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.tarification_id' => 'required|exists:tarifications,id',
        ]);

        $employeeId = auth()->user()->employee->id;

        $date = now()->toDateString();

        DB::beginTransaction();
        try {
            // TarificationPacket yaratamiz
            $tarificationPacket = TarificationPacket::create([
                'employee_id' => $employeeId,
                'date' => $date,
            ]);

            // Har bir item uchun TarificationPacketItem yaratamiz
            foreach ($request->items as $item) {
                $tarificationPacket->tarificationPacketsItems()->create([
                    'tarification_id' => $item['tarification_id'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Tarification packet created successfully.',
                'packet' => $tarificationPacket->load('tarificationPacketsItems.tarification'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    public function updateTarificationPacket(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.tarification_id' => 'required|exists:tarifications,id',
            'items.*.id' => 'nullable|integer|exists:tarification_packet_items,id',
        ]);

        $packet = TarificationPacket::findOrFail($id);

        // ✅ Avval duplikat tarification_id larni tekshiramiz
        $tarificationIds = [];

        foreach ($request->items as $item) {
            $tid = $item['tarification_id'];

            if (in_array($tid, $tarificationIds)) {
                return response()->json([
                    'message' => 'Har bir tarification_id faqat bir marta bo‘lishi kerak.',
                    'error_tarification_id' => $tid,
                ], 422);
            }

            $tarificationIds[] = $tid;
        }

        DB::beginTransaction();
        try {
            foreach ($request->items as $item) {
                if (!empty($item['id'])) {
                    $packetItem = $packet->tarificationPacketsItems()->find($item['id']);
                    if ($packetItem) {
                        $packetItem->update([
                            'tarification_id' => $item['tarification_id'],
                        ]);
                    }
                } else {
                    // 🔒 Yangi item yaratishdan oldin tekshiramiz: shu tarification_id allaqachon packetda bormi?
                    $exists = $packet->tarificationPacketsItems()
                        ->where('tarification_id', $item['tarification_id'])
                        ->exists();

                    if ($exists) {
                        return response()->json([
                            'message' => 'Yangi yozuv yaratilmaydi: bu tarification_id allaqachon mavjud.',
                            'error_tarification_id' => $item['tarification_id'],
                        ], 422);
                    }

                    $packet->tarificationPacketsItems()->create([
                        'tarification_id' => $item['tarification_id'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Tarification packet updated successfully.',
                'packet' => $packet->load('tarificationPacketsItems.tarification'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteTarificationPacket($id): \Illuminate\Http\JsonResponse
    {
        $packet = TarificationPacket::findOrFail($id);

        DB::beginTransaction();
        try {
            // TarificationPacketItems ni o'chiramiz
            $packet->tarificationPacketsItems()->delete();

            // TarificationPacket ni o'chiramiz
            $packet->delete();

            DB::commit();

            return response()->json([
                'message' => 'Tarification packet deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteTarificationPacketItem($id): \Illuminate\Http\JsonResponse
    {
        $item = TarificationPacketItem::findOrFail($id);

        DB::beginTransaction();
        try {
            // TarificationPacketItem ni o'chiramiz
            $item->delete();

            DB::commit();

            return response()->json([
                'message' => 'Tarification packet item deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



}
<?php

namespace App\Http\Controllers;

use App\Models\EmployeeTransportDaily;
use App\Models\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Support\Facades\Storage;

class HikvisionEventController extends Controller
{
    public function handleEvent(Request $request): \Illuminate\Http\JsonResponse
    {
        $contentType = $request->header('Content-Type');

        // Qurilma → filial mapping
        $deviceBranchMap = [
            255 => 4, // Branch 4
            105 => 5, // Branch 5
        ];

        $branchChatMap = [
            4 => -1001457275928, // masalan 4-branch uchun chat_id
            5 => -1001883536528, // 5-branch uchun chat_id
        ];

        if (str_contains($contentType, 'multipart/form-data')) {
            $eventLogRaw = $request->input('event_log');
            $outerEvent = null;

            if ($eventLogRaw) {
                // ESKI FORMAT
                $outerEvent = json_decode($eventLogRaw, true);
                $accessData = $outerEvent['AccessControllerEvent'] ?? [];
            } elseif ($request->has('AccessControllerEvent')) {
                // YANGI FORMAT
                $outerEvent = json_decode($request->input('AccessControllerEvent'), true);
                $accessData = $outerEvent['AccessControllerEvent'] ?? [];
            } else {
                Log::add(null, 'Hikvision event: format aniqlanmadi', 'error', null, [
                    'request_data' => $request->all(),
                ]);
                return response()->json(['status' => 'unknown_format']);
            }

            $employeeNo = $accessData['employeeNoString'] ?? null;
            $deviceId = isset($outerEvent['deviceID']) ? (int)$outerEvent['deviceID'] : null;
            $eventTime = $outerEvent['dateTime'] ?? now()->toDateTimeString();

            // Device ID mapping bo‘yicha branchni topamiz
            $branchFromDevice = $deviceBranchMap[$deviceId] ?? null;
            if (!$branchFromDevice) {
                return response()->json(['status' => 'unknown_device']);
            }


            $employee = Employee::with('transports')->find($employeeNo);

            // Hodim topilmasa yoki branch mos kelmasa
            if (!$employee || (int)$employee->branch_id !== (int)$branchFromDevice) {
                Log::add($employee->user_id ?? null, 'Branch mos kelmadi yoki employee topilmadi', 'branch_mismatch', null, [
                    'employee_id' => $employeeNo,
                    'employee_branch' => $employee->branch_id ?? null,
                    'device_branch' => $branchFromDevice,
                    'device_id' => $deviceId,
                ]);
                return response()->json(['status' => 'branch_mismatch']);
            }

            // Attendance logika
            $eventCarbon = Carbon::parse($eventTime);
            $today = $eventCarbon->toDateString();

            $attendance = Attendance::firstOrCreate(
                ['employee_id' => $employee->id, 'date' => $today],
                ['source_type' => 'device']
            );

            if ($deviceId === 255 || $deviceId === 105) {
                // Check In
                if (!$attendance->check_in) {
                    $image = $request->file('Picture');
                    $imagePath = null;
                    if ($image && $image->isValid()) {
//                        $filename = uniqid($employeeNo . '_') . '.' . $image->getClientOriginalExtension();
//                        $image->storeAs('/public/hikvision/', $filename);
//                        $imagePath = 'hikvision/' . $filename;
                        //if ($request->hasFile('img')) {
                        //                $file = $request->file('img');
                        //                $filename = time() . '.' . $file->getClientOriginalExtension();
                        //
                        //                // S3 ga yuklaymiz
                        //                $path = $file->storeAs('images', $filename, 's3');
                        //
                        //                Storage::disk('s3')->setVisibility($path, 'public');
                        //
                        //                $employee->img = Storage::disk('s3')->url($path);
                        //                $employee->save();
                        //            }

                        $filename = uniqid($employeeNo . '_') . '.' . $image->getClientOriginalExtension();
                        $path = $image->storeAs('images', $filename, 's3');
                        Storage::disk('s3')->setVisibility($path, 'public');
                        $imagePath = Storage::disk('s3')->url($path);
                    }
                    $attendance->check_in = $eventCarbon;
                    $attendance->check_in_image = $imagePath;
                    $attendance->status = 'present';
                    $attendance->save();

                    if (!$employee->transports->isEmpty()) {
                        $transport = $employee->transports->first();

                        $date = now();
                        $exists = EmployeeTransportDaily::where('employee_id', $employee->id)
                            ->where('transport_id', $transport->id)
                            ->whereDate('date', $date)
                            ->exists();

                        if (!$exists) {
                            EmployeeTransportDaily::create([
                                'employee_id' => $employee->id,
                                'transport_id' => $transport->id,
                                'date' => $date,
                            ]);
                        }
                    }

                    Log::add($employee->user_id ?? null, 'Hodim ishga keldi', 'Check In', null, [
                        'employee_id' => $employee->id,
                        'image_path' => $imagePath,
                        'device_id' => $deviceId,
                        'time' => $eventTime,
                    ]);

                    $branchId = $employee->branch_id;
                    $chatId = $branchChatMap[$branchId] ?? null;

                    if ($chatId) {
                        $employees = Employee::with(['department', 'group'])
                            ->where('branch_id', $branchId)
                            ->whereHas('attendances', function ($query) use ($today) {
                                $query->whereDate('check_in', $today);
                            })
                            ->get();

                        app(\App\Services\TelegramService::class)
                            ->updateDailyReport($branchId, $chatId, $employees);
                    }
                } else {
                    Log::add($employee->user_id ?? null, 'Qaytadan faceId aniqlandi', 'already_checkin', null, [
                        'employee_id' => $employee->id,
                        'device_id' => $deviceId,
                        'time' => $eventTime,
//                        'image_path' => $imagePath,
                    ]);
                }
            } elseif ($deviceId === 256) {
                // Check Out
                if (!$attendance->check_out) {
                    $attendance->check_out = $eventCarbon;
//                    $attendance->check_out_image = $imagePath;
                    $attendance->save();

                    Log::add($employee->user_id ?? null, 'Hodim ishdan ketdi', 'Check Out', null, [
                        'employee_id' => $employee->id,
//                        'image_path' => $imagePath,
                        'device_id' => $deviceId,
                        'time' => $eventTime,
                    ]);
                } else {
                    Log::add($employee->user_id ?? null, 'Qaytadan faceId aniqlandi', 'already_checkout', null, [
                        'employee_id' => $employee->id,
                        'device_id' => $deviceId,
                        'time' => $eventTime,
//                        'image_path' => $imagePath,
                    ]);
                }
            }
        }

        return response()->json(['status' => 'received']);
    }
}

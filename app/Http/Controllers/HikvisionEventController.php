<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Attendance;

class HikvisionEventController extends Controller
{
    public function handleEvent(Request $request): \Illuminate\Http\JsonResponse
    {
        $contentType = $request->header('Content-Type');

//        Log::add(
//            null,
//            'Hikvision event received',
//            'info',
//            null,
//            [
//                'content_type' => $contentType,
//                'request_data' => $request->all(),
//            ]
//        );

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
                // Hech qaysi formatga tushmaydi
                Log::add(null, 'Hikvision event: format aniqlanmadi', 'error', null, [
                    'request_data' => $request->all(),
                ]);
                return response()->json(['status' => 'unknown_format']);
            }

            $employeeNo = $accessData['employeeNoString'] ?? null;
            $deviceId = $outerEvent['deviceID'] ?? null;
            $eventTime = $outerEvent['dateTime'] ?? now()->toDateTimeString();

            $image = $request->file('Picture');
            $imagePath = null;
            if ($image && $image->isValid()) {
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $image->storeAs('/public/hikvision/', $filename);
                $imagePath = 'hikvision/' . $filename;
            }

            $employee = Employee::find($employeeNo);

            if ($employee) {
                $eventCarbon = Carbon::parse($eventTime);
                $today = $eventCarbon->toDateString();

                $attendance = Attendance::firstOrCreate(
                    ['employee_id' => $employee->id, 'date' => $today],
                    ['source_type' => 'device']
                );

                if ((int)$deviceId === 255 && !$attendance->check_in) {
                    $attendance->check_in = $eventCarbon;
                    $attendance->check_in_image = $imagePath;
                    $attendance->status = 'present';
                    $attendance->save();

                    Log::add($employee->user_id ?? null, 'Hodim ishga keldi', 'Check In', null, [
                        'employee_id' => $employee->id,
                        'image_path' => $imagePath,
                        'device_id' => $deviceId,
                        'time' => $eventTime,
                    ]);
                } elseif ((int)$deviceId === 256 && !$attendance->check_out) {
                    $attendance->check_out = $eventCarbon;
                    $attendance->check_out_image = $imagePath;
                    $attendance->save();

                    Log::add($employee->user_id ?? null, 'Hodim ishdan ketdi', 'Check Out', null, [
                        'employee_id' => $employee->id,
                        'image_path' => $imagePath,
                        'device_id' => $deviceId,
                        'time' => $eventTime,
                    ]);
                } else {
                    Log::add($employee->user_id ?? null, 'Qaytadan faceId aniqlandi', 'already', null, [
                        'employee_id' => $employee->id,
                        'device_id' => $deviceId,
                        'time' => $eventTime,
                        'image_path' => $imagePath,
                    ]);
                }
            }
        }

        return response()->json(['status' => 'received']);
    }
}

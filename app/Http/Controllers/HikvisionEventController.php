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

        if (str_contains($contentType, 'multipart/form-data')) {
            $eventLogRaw = $request->input('event_log');
            $image = $request->file('Picture');

            $eventData = json_decode($eventLogRaw, true);
            $accessData = $eventData['AccessControllerEvent'] ?? [];

            $employeeNo = $accessData['employeeNoString'] ?? null;
            $deviceId = $eventData['deviceID'] ?? null;
            $eventTime = $eventData['dateTime'] ?? now()->toDateTimeString();

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

                // CHECK IN — deviceId == 255
                if ((int)$deviceId === 255 && !$attendance->check_in) {
                    $attendance->check_in = $eventCarbon;
                    $attendance->check_in_image = $imagePath;
                    $attendance->status = 'present';
                    $attendance->save();

                    Log::add(
                        $employee->user_id ?? null,
                        'Hodim ishga keldi',
                        'Check In',
                        null,
                        [
                        'employee_id' => $employee->id,
                        'image_path' => $imagePath,
                        'device_id' => $deviceId,
                        'time' => $eventTime,
                        ]
                    );
                }

                // CHECK OUT — deviceId == 256
                elseif ((int)$deviceId === 256 && !$attendance->check_out) {
                    $attendance->check_out = $eventCarbon;
                    $attendance->check_out_image = $imagePath;
                    $attendance->save();

                    Log::add(
                        $employee->user_id ?? null,
                        'Hodim ishdan ketdi',
                        'Check Out',
                        null,
                        [
                            'employee_id' => $employee->id,
                            'image_path' => $imagePath,
                            'device_id' => $deviceId,
                            'time' => $eventTime,
                        ]
                    );
                }

                // Agar ikkalasi ham bor bo‘lsa — qayta yozmaymiz
                else {
                    Log::add(
                        $employee->user_id ?? null,
                        'Qaytadan faceId aniqlandi',
                        'already',
                        null,
                        [
                            'employee_id' => $employee->id,
                            'device_id' => $deviceId,
                            'time' => $eventTime,
                            'image_path' => $imagePath,
                        ]
                    );
                }

            }

        }

        return response()->json(['status' => 'received']);
    }
}

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
            $eventTime = $accessData['dateTime'] ?? now()->toDateTimeString(); // fallback

            // Rasmni saqlash
            $imagePath = null;
            if ($image && $image->isValid()) {
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $image->storeAs('public/hikvision', $filename);
                $imagePath = 'storage/hikvision/' . $filename;
            }

            // Hodimni topish
            $employee = Employee::where('id', $employeeNo)->first(); // yoki boshqa mapping maydon

            if ($employee && $imagePath) {
                $eventCarbon = Carbon::parse($eventTime);
                $today = $eventCarbon->toDateString();

                // Bor bo‘lsa - update, yo‘q bo‘lsa - create
                $attendance = Attendance::firstOrCreate(
                    ['employee_id' => $employee->id, 'date' => $today],
                    ['source_type' => 'device', 'check_in' => $eventCarbon, 'comment' => 'Face ID']
                );

                // Agar allaqachon bor bo‘lsa va check_in yo‘q bo‘lsa, uni yozamiz
                if (!$attendance->check_in) {
                    $attendance->check_in = $eventCarbon;
                    $attendance->save();
                }

                // Log yozish (muvoffaqiyatli holat)
                Log::add($employee->user_id ?? null, 'Hikvision Attendance', 'Check-in via face recognition', [
                    'employee_id' => $employee->id,
                    'image_path' => $imagePath,
                    'time' => $eventTime,
                ]);

            } else {
                // Xatolik log
                Log::add(null, 'Hikvision Attendance', 'No employee matched or image missing', [
                    'employee_no' => $employeeNo,
                    'has_image' => $imagePath ? true : false,
                ]);
            }

        } else {
            // Fallback – JSON yoki XML bo‘lishi mumkin
            $rawData = $request->getContent();
            Log::add(null, 'Hikvision Event', 'Unknown format', [
                'content_type' => $contentType,
                'raw_data' => $rawData,
            ]);
        }

        return response()->json(['status' => 'received']);
    }
}

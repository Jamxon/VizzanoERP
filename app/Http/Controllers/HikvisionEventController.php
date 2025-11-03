<?php

namespace App\Http\Controllers;

use App\Models\EmployeeTransportDaily;
use App\Models\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http; // <— ⚠️ BU YO‘Q EDI, Telegram POST uchun kerak

class HikvisionEventController extends Controller
{
    public function handleEvent(Request $request): \Illuminate\Http\JsonResponse
    {
        $contentType = $request->header('Content-Type');

        // Qurilma → filial mapping
        $deviceBranchMap = [
            255 => 4,
            105 => 5,
        ];

        $branchChatMap = [
            4 => -1003041140850,
            5 => -1001883536528,
        ];

        if (str_contains($contentType, 'multipart/form-data')) {
            $eventLogRaw = $request->input('event_log');
            $outerEvent = null;

            if ($eventLogRaw) {
                $outerEvent = json_decode($eventLogRaw, true);
                $accessData = $outerEvent['AccessControllerEvent'] ?? [];
            } elseif ($request->has('AccessControllerEvent')) {
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

            $branchFromDevice = $deviceBranchMap[$deviceId] ?? null;
            if (!$branchFromDevice) {
                return response()->json(['status' => 'unknown_device']);
            }

            $employee = Employee::with(['transports', 'department', 'group'])->find($employeeNo); // ⚠️ department/group ham kerak edi

            if (!$employee || (int)$employee->branch_id !== (int)$branchFromDevice) {
                Log::add($employee->user_id ?? null, 'Branch mos kelmadi yoki employee topilmadi', 'branch_mismatch', null, [
                    'employee_id' => $employeeNo,
                    'employee_branch' => $employee->branch_id ?? null,
                    'device_branch' => $branchFromDevice,
                    'device_id' => $deviceId,
                ]);
                return response()->json(['status' => 'branch_mismatch']);
            }

            $eventCarbon = Carbon::parse($eventTime)->setTimezone('Asia/Tashkent'); // ⚠️ vaqtni to‘g‘ri zona bilan olish
            $today = $eventCarbon->toDateString();

            $attendance = Attendance::firstOrCreate(
                ['employee_id' => $employee->id, 'date' => $today],
                ['source_type' => 'device']
            );

            // === CHECK-IN ===
            if ($deviceId === 255 || $deviceId === 105) {
                if (!$attendance->check_in) {
                    $image = $request->file('Picture');
                    $imagePath = null;

                    if ($image && $image->isValid()) {
                        $filename = uniqid($employeeNo . '_') . '.' . $image->getClientOriginalExtension();
                        $path = $image->storeAs('hikvisionImages', $filename, 's3');
                        Storage::disk('s3')->setVisibility($path, 'public');
                        $imagePath = Storage::disk('s3')->url($path);
                    }

                    $attendance->check_in = $eventCarbon;
                    $attendance->check_in_image = $imagePath;
                    $attendance->status = 'present';
                    $attendance->save();

                    // Transport davomat
                    if (!$employee->transports->isEmpty()) {
                        $transport = $employee->transports->first();

                        $exists = EmployeeTransportDaily::where('employee_id', $employee->id)
                            ->where('transport_id', $transport->id)
                            ->whereDate('date', now())
                            ->exists();

                        if (!$exists) {
                            EmployeeTransportDaily::create([
                                'employee_id' => $employee->id,
                                'transport_id' => $transport->id,
                                'date' => now(),
                            ]);
                        }
                    }

                    // === AUP kechikish tekshiruvi ===
                    if ($employee->type === 'aup') {
                        $lateTime = Carbon::createFromTime(7, 30, 0, 'Asia/Tashkent');

                        if ($eventCarbon->gt($lateTime)) {
                            $lateChatId = -4832517980;
                            $botToken = '8466233197:AAFpW34maMs_2y5-Ro_2FQNxniLBaWwLRD8';
                            $checkInTime = $eventCarbon; // ✅ Shu qatorda aniqlaymiz

                            $msg = sprintf(
                                "⚠️ *%s* (AUP) kechikib keldi.\n\n" .
                                "📱 *Telefon:* %s\n" .
                                "🕒 *Kirish vaqti:* %s\n" .
                                "🏢 *Bo‘lim:* %s\n" .
                                "👥 *Guruh:* %s",
                                $employee->name ?? '-',
                                $employee->phone ?? '-',
                                $checkInTime->format('H:i:s'),
                                $employee->department->name ?? '-',
                                $employee->group->name ?? '-'
                            );

                            // Default employee rasmi
                            $imageUrl = !empty($employee->img)
                                ? (str_starts_with($employee->img, 'http') ? $employee->img : url($employee->img))
                                : null;

                            // Hikvision eventdan kelgan rasm (S3 dan)
                            if (!empty($imagePath)) {
                                $imageUrl = $imagePath; // bu allaqachon to‘liq public URL
                            }

                            // Fon jarayon sifatida yuborish
                            dispatch(function () use ($botToken, $lateChatId, $msg, $imageUrl) {
                                try {
                                    if ($imageUrl) {
                                        // Telegramga rasm bilan yuborish
                                        \Http::post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                                            'chat_id' => $lateChatId,
                                            'photo' => $imageUrl,
                                            'caption' => $msg,
                                            'parse_mode' => 'Markdown',
                                        ]);
                                    } else {
                                        // Agar rasm yo‘q bo‘lsa, faqat matn yuborish
                                        \Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                            'chat_id' => $lateChatId,
                                            'text' => $msg,
                                            'parse_mode' => 'Markdown',
                                        ]);
                                    }
                                } catch (\Throwable $e) {
                                    \Log::error('Telegram kechikish xabar yuborilmadi: ' . $e->getMessage());
                                }
                            });
                        }
                    }




                    Log::add($employee->user_id ?? null, 'Hodim ishga keldi', 'Check In', null, [
                        'employee_id' => $employee->id,
                        'image_path' => $imagePath,
                        'device_id' => $deviceId,
                        'time' => $eventTime,
                    ]);

                    // Branchning umumiy hisobotini yangilash
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
                    ]);
                }
            }

            // === CHECK-OUT ===
            elseif ($deviceId === 256) {
                if (!$attendance->check_out) {
                    $attendance->check_out = $eventCarbon;
                    $attendance->save();

                    Log::add($employee->user_id ?? null, 'Hodim ishdan ketdi', 'Check Out', null, [
                        'employee_id' => $employee->id,
                        'device_id' => $deviceId,
                        'time' => $eventTime,
                    ]);
                } else {
                    Log::add($employee->user_id ?? null, 'Qaytadan faceId aniqlandi', 'already_checkout', null, [
                        'employee_id' => $employee->id,
                        'device_id' => $deviceId,
                        'time' => $eventTime,
                    ]);
                }
            }
        }

        return response()->json(['status' => 'received']);
    }
}
<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeAttendanceExport;
use App\Http\Resources\GetEmployeeResourceCollection;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\EmployeeHolidays;
use App\Models\Lid;
use App\Models\Log;
use App\Models\MainDepartment;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Exports\EmployeeExport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;


class SuperHRController extends Controller
{

    public function storeEmployeeAbsence(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'comment' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480',
        ]);

        try {
            DB::beginTransaction();

            $filename = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('/public/absences/', $filename);
            }

            $absence = EmployeeAbsence::create([
                'employee_id' => $request->employee_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'comment' => $request->comment,
                'image' => $filename ? 'absences/' . $filename : null,
            ]);

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Xodimning sababsiz kelmaganligi aniqlandi',
                'create',
                null,
                $absence
            );

            // 🟢 Telegramga yuborish
            $employee = \App\Models\Employee::find($request->employee_id);
            $messageText = "🚫 *Sababsiz:*\n\n"
                . "*Ismi:* {$employee->name}\n"
                . "*Telefon:* {$employee->phone}\n"
                . "*Sanalar:* {$request->start_date} - {$request->end_date}\n"
                . "*Izoh:* " . ($request->comment ?? 'Yo‘q');

// 🔽 Guruh va masʼul shaxs haqida qo‘shimcha
            if ($employee->group_id) {
                $group = \App\Models\Group::with('responsibleUser')->find($employee->group_id);
                if ($group) {
                    $messageText .= "\n*Guruh:* {$group->name}";
                    if ($group->responsibleUser) {
                        $messageText .= "\n*Masʼul:* {$group->responsibleUser->employee->name}";
                    }
                }
            }


            $telegramToken = "8055327076:AAEDwAlq1mvZiEbAi_ofnUwnJeIm4P6tE1A";
            $chatId = -1002655761088;

            if ($filename) {
                // Rasm bilan yuborish
                $imagePath = storage_path("app/public/absences/" . $filename);

                Http::attach('photo', file_get_contents($imagePath), $filename)
                    ->post("https://api.telegram.org/bot{$telegramToken}/sendPhoto", [
                        'chat_id' => $chatId,
                        'caption' => $messageText,
                        'parse_mode' => 'Markdown',
                    ]);
            } else {
                // Faqat matn
                Http::post("https://api.telegram.org/bot{$telegramToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $messageText,
                    'parse_mode' => 'Markdown',
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Xodimning yo‘qligi muvaffaqiyatli qo‘shildi',
                'absence' => $absence,
            ], 201);
        }
        catch (\Exception $e) {
            DB::rollBack();

            Log::add(
                auth()->user()->id,
                'Xodimning yo‘qligini qo‘shishda xatolik',
                'error',
                null,
                $e->getMessage()
            );

            return response()->json([
                'status' => 'error',
                'message' => 'Xodimning yo‘qligini qo‘shishda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPotentialAbsents(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $branchId = $user?->employee?->branch_id;

        if (!$branchId) {
            return response()->json(['message' => '❌ Foydalanuvchining filial (branch) aniqlanmadi.'], 422);
        }

        $today = Carbon::today();

        // 1. Bugungi attendance yozilgan hodimlar
        $presentIds = Attendance::whereDate('date', $today)
            ->pluck('employee_id')
            ->toArray();

        // 2. Bugungi ruxsatda (holiday) bo‘lgan employee_id lar
        $holidayIds = EmployeeHolidays::whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('employee_id')
            ->toArray();

        // 3. Bugungi ruxsatli yo'qlik (absence) bo'lganlar
        $absenceIds = EmployeeAbsence::whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('employee_id')
            ->toArray();

        // 4. Bugun attendance yozilmaganlar (lekin kicked bo‘lmaganlar)
        $notCheckedIds = Employee::where('branch_id', $branchId)
            ->whereNotIn('id', $presentIds)
            ->where('status', '!=', 'kicked')
            ->pluck('id')
            ->toArray();

        // 5. Holiday + Absence + Not checked larni birlashtiramiz
        $allIds = array_unique(array_merge($notCheckedIds, $holidayIds, $absenceIds));

        $employees = Employee::whereIn('id', $allIds)
            ->where('branch_id', $branchId)
            ->where('status', '!=', 'kicked')
            ->with(['department', 'group', 'position'])
            ->get();

        // 6. Natija
        $result = $employees->map(function ($employee) use ($today, $holidayIds, $absenceIds) {
            $wasOnHoliday = in_array($employee->id, $holidayIds);
            $wasOnAbsence = in_array($employee->id, $absenceIds);

            $comment = null;
            $image = null;

            if ($wasOnHoliday) {
                $holiday = EmployeeHolidays::where('employee_id', $employee->id)
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->first();

                $comment = $holiday?->comment;
                $image = $holiday?->image ?? null;
            } elseif ($wasOnAbsence) {
                $absence = EmployeeAbsence::where('employee_id', $employee->id)
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->first();

                $comment = $absence?->comment;
                $image = $absence?->image ?? null;
            }

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'department' => $employee->department->name ?? null,
                'group' => $employee->group->name ?? null,
                'position' => $employee->position->name ?? null,
                'absent_date' => $today->toDateString(),
                'was_on_holiday' => $wasOnHoliday,
                'was_on_absence' => $wasOnAbsence,
                'comment' => $comment,
                'image' => (!empty($image) && Str::contains($image, '/')) ? url('storage/' . $image) : null,
            ];
        });

        return response()->json($result);
    }

    public function getYesterdayAbsent(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $branchId = $user?->employee?->branch_id;

        if (!$branchId) {
            return response()->json(['message' => '❌ Foydalanuvchining filial (branch) aniqlanmadi.'], 422);
        }

        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        if ($yesterday->isSunday()) {
            $yesterday = $yesterday->copy()->subDay();
        }

        $todayPresentIds = Attendance::whereDate('date', $today)
            ->where('status', 'present')
            ->pluck('employee_id')
            ->toArray();

        $yesterdayAbsentIds = Attendance::whereDate('date', $yesterday)
            ->where('status', 'absent')
            ->pluck('employee_id')
            ->toArray();

        $filteredIds = array_intersect($yesterdayAbsentIds, $todayPresentIds);

        $holidayIds = \App\Models\EmployeeHolidays::whereDate('start_date', '<=', $yesterday)
            ->whereDate('end_date', '>=', $yesterday)
            ->pluck('employee_id')
            ->toArray();

        $absenceIds = \App\Models\EmployeeAbsence::whereDate('start_date', '<=', $yesterday)
            ->whereDate('end_date', '>=', $yesterday)
            ->pluck('employee_id')
            ->toArray();

        $allIds = array_unique(array_merge($filteredIds, $holidayIds, $absenceIds));

        $employees = \App\Models\Employee::whereIn('id', $allIds)
            ->where('branch_id', $branchId)
            ->with(['department', 'group', 'position'])
            ->get();

        $absentEmployees = $employees->map(function ($employee) use ($yesterday, $holidayIds, $absenceIds) {
            $wasOnHoliday = in_array($employee->id, $holidayIds);
            $wasOnAbsence = in_array($employee->id, $absenceIds);

            $comment = null;
            $image = null;

            if ($wasOnHoliday) {
                $holiday = \App\Models\EmployeeHolidays::where('employee_id', $employee->id)
                    ->whereDate('start_date', '<=', $yesterday)
                    ->whereDate('end_date', '>=', $yesterday)
                    ->first();

                $comment = $holiday?->comment;
                $image = $holiday?->image ?? null;
            } elseif ($wasOnAbsence) {
                $absence = \App\Models\EmployeeAbsence::where('employee_id', $employee->id)
                    ->whereDate('start_date', '<=', $yesterday)
                    ->whereDate('end_date', '>=', $yesterday)
                    ->first();

                $comment = $absence?->comment;
                $image = $absence?->image ?? null;
            }

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'department' => $employee->department->name ?? null,
                'group' => $employee->group->name ?? null,
                'position' => $employee->position->name ?? null,
                'absent_date' => $yesterday->toDateString(),
                'was_on_holiday' => $wasOnHoliday,
                'was_on_absence' => $wasOnAbsence,
                'comment' => $comment,
                'image' => (!empty($image) && Str::contains($image, '/')) ? url('storage/' . $image) : null,
            ];
        });

        return response()->json($absentEmployees);
    }

    public function getEmployeeHolidays(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $branchId = $user->employee->branch_id;
        $startDate = request()->get('start_date');
        $endDate = request()->get('end_date');
        $search = request()->get('search');

        $query = EmployeeHolidays::whereHas('employee', function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        });

        if ($startDate && $endDate) {
            $query->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate]);
        }

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });

            $query->orWhere('comment', 'like', '%' . $search . '%');
        }

        $holidays = $query->with('employee.department','employee.group')->orderBy('id', 'DESC')->paginate(10);

        $holidays->getCollection()->transform(function ($holiday) {
            $holiday->image = $holiday->image ? url('storage/' . $holiday->image) : null;
            return $holiday;
        });

        return response()->json($holidays, 200);
    }

    public function editEmployeeHoliday(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $holiday = EmployeeHolidays::findOrFail($id);

        if (!$holiday){
            return response()->json(['status' => 'error', 'message' => 'Xodim ta‘tili topilmadi'], 404);
        }

        $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'comment' => 'sometimes|nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $holiday->update([
                'start_date' => $request->start_date ?? $holiday->start_date,
                'end_date' => $request->end_date ?? $holiday->end_date,
                'comment' => $request->comment ?? $holiday->comment,
            ]);

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Xodim ta‘tili yangilandi',
                'edit',
                null,
                $holiday
            );

            return response()->json(['status' => 'success', 'message' => 'Xodim ta‘tili muvaffaqiyatli yangilandi', 'holiday' => $holiday], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::add(
                auth()->user()->id,
                "Xodim ta‘tilini yangilashda xatolik",
                "error",
                null,
                $e->getMessage()
            );
            return response()->json(['status' => 'error', 'message' => 'Xodim ta‘tilini yangilashda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function storeEmployeeHoliday(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'comment' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480',
        ]);

        try {
            DB::beginTransaction();

            $filename = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('/public/holidays/', $filename);
            }

            $holiday = \App\Models\EmployeeHolidays::create([
                'employee_id' => $request->employee_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'comment' => $request->comment,
                'image' => $filename ? 'holidays/' . $filename : null,
            ]);

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Xodim ta‘tili qo‘shildi',
                'create',
                null,
                $holiday
            );

            // 🟢 Telegramga yuborish
            $employee = \App\Models\Employee::find($request->employee_id);

            $messageText = "🌴 ️*Sababli:*\n\n"
                . "*Ismi:* {$employee->name}\n"
                . "*Telefon:* {$employee->phone}\n"
                . "*Sanalar:* {$request->start_date} - {$request->end_date}\n"
                . "*Izoh:* " . ($request->comment ?? 'Yo‘q');

// Guruh va masʼul shaxs haqida qo‘shimcha
            if ($employee->group_id) {
                $group = \App\Models\Group::with('responsibleUser')->find($employee->group_id);
                if ($group) {
                    $messageText .= "\n*Guruh:* {$group->name}";
                    if ($group->responsibleUser) {
                        $messageText .= "\n*Masʼul:* {$group->responsibleUser->employee->name}";
                    }
                }
            }

            $telegramToken = "8055327076:AAEDwAlq1mvZiEbAi_ofnUwnJeIm4P6tE1A";
            $chatId = -1002655761088;

            $photos = [];

            if ($filename) {
                $photos[] = 'holidays/' . $filename; // faqat nisbiy path
            }

                $attendance = \App\Models\Attendance::where('employee_id', $request->employee_id)
                    ->whereDate('date', $request->start_date)
                    ->first();

                if ($attendance && $attendance->check_in_image) {
                    $photos[] = $attendance->check_in_image;
                }

            if ($employee->img && (!$attendance || !$attendance->check_in_image)) {
                $photos[] = $employee->img;
            }

            function getPhotoContent($path) {
                if (filter_var($path, FILTER_VALIDATE_URL)) {
                    $response = Http::get($path);
                    return $response->successful() ? $response->body() : null;
                }

                $fullPath = storage_path("app/public/" . ltrim($path, '/'));
                if (file_exists($fullPath)) {
                    return file_get_contents($fullPath);
                }

                return null;
            }

            if (!empty($photos)) {
                $media = [];
                $files = [];

                foreach ($photos as $index => $photoPath) {
                    $photoContent = getPhotoContent($photoPath);
                    if ($photoContent) {
                        $tmpFile = tempnam(sys_get_temp_dir(), 'tg');
                        file_put_contents($tmpFile, $photoContent);

                        $fieldName = "photo{$index}";
                        $files[$fieldName] = new \CURLFile($tmpFile, null, basename($photoPath));

                        $media[] = [
                            'type' => 'photo',
                            'media' => "attach://{$fieldName}",
                            'caption' => $index == 0 ? $messageText : null,
                            'parse_mode' => 'Markdown'
                        ];
                    }
                }

                if (!empty($media)) {
                    $postFields = [
                        'chat_id' => $chatId,
                        'media'   => json_encode($media, JSON_UNESCAPED_UNICODE),
                    ];

                    $postFields = array_merge($postFields, $files);

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$telegramToken}/sendMediaGroup");
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

                    $response = curl_exec($ch);
                    curl_close($ch);

                    // Debug uchun
                    // dd($response);
                }
            } else {
                Http::post("https://api.telegram.org/bot{$telegramToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $messageText,
                    'parse_mode' => 'Markdown',
                ]);
            }


            return response()->json([
                'status' => 'success',
                'message' => 'Xodim ta‘tili muvaffaqiyatli qo‘shildi',
                'holiday' => $holiday,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::add(
                auth()->user()->id,
                "Xodim ta‘tilini qo‘shishda xatolik",
                "error",
                null,
                $e->getMessage()
            );

            return response()->json([
                'status' => 'error',
                'message' => 'Xodim ta‘tilini qo‘shishda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportAttendancePdf(Request $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $groupId = $request->get('group_id');
        $departmentId = $request->get('department_id');
        $statusFilter = $request->get('status'); // 'all' or 'absent'

        ini_set('memory_limit', '2G');
        set_time_limit(120);

        $branchId = auth()->user()?->employee?->branch_id;
        if (!$branchId) {
            return response()->json(['message' => '❌ Foydalanuvchining filial aniqlanmadi'], 422);
        }

        if (!$startDate || !$endDate) {
            return response()->json(['message' => '❌ Sana noto‘g‘ri yoki to‘liq emas'], 422);
        }

        $employees = \App\Models\Employee::where('branch_id', $branchId)
            ->where('status', '!=', 'kicked')
            ->when($groupId, fn($q) => $q->where('group_id', $groupId))
            ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
            ->select('id', 'name', 'department_id', 'group_id')
            ->get();

        $attendances = \App\Models\Attendance::whereBetween('date', [$startDate, $endDate])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id');

        $dateRange = collect();
        $current = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        while ($current->lte($end)) {
            $dateRange->push($current->toDateString());
            $current->addDay();
        }

        $result = $employees->map(function ($employee) use ($attendances, $dateRange) {
            $employeeAttendances = $attendances[$employee->id] ?? collect();
            $presentCount = 0;
            $absentCount = 0;
            $status = [];

            foreach ($dateRange as $date) {
                $att = $employeeAttendances->firstWhere('date', $date);
                if ($att && $att->status === 'present') {
                    $presentCount++;
                    $status[] = ['date' => $date, 'status' => 'Kelgan'];
                } else {
                    $absentCount++;
                    $status[] = ['date' => $date, 'status' => 'Kelmagan'];
                }
            }

            return [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'department' => $employee->department->name ?? null,
                'group' => $employee->group->name ?? null,
                'present_count' => $presentCount,
                'absent_count' => $absentCount,
                'status_detail' => $status,
            ];
        });

        // 🧠 Faqat to'liq "Kelmagan"lar uchun filtr
        if ($statusFilter === 'absent') {
            $result = $result->filter(fn($emp) => $emp['present_count'] === 0 && $emp['absent_count'] > 0)->values();
        }

        $pdf = PDF::loadView('pdf.attendance-report', [
            'employees' => $result,
            'date_range' => [$startDate, $endDate],
        ])->setPaper('a4', 'portrait');

        return $pdf->download("attendance_{$startDate}_{$endDate}.pdf");
    }

    public function filterAttendance(Request $request): \Illuminate\Http\JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $groupId = $request->get('group_id');
        $departmentId = $request->get('department_id');
        $statusFilter = $request->get('status');

        $branchId = auth()->user()?->employee?->branch_id;
        if (!$branchId) {
            return response()->json(['message' => '❌ Foydalanuvchining filial aniqlanmadi'], 422);
        }

        if (!$startDate || !$endDate) {
            return response()->json(['message' => '❌ Sana noto‘g‘ri yoki to‘liq emas'], 422);
        }

        $employees = \App\Models\Employee::where('branch_id', $branchId)
            ->where('status', '!=', 'kicked')
            ->when($groupId, fn($q) => $q->where('group_id', $groupId))
            ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
            ->select('id', 'name', 'department_id', 'group_id')
            ->get();

        $attendances = \App\Models\Attendance::whereBetween('date', [$startDate, $endDate])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id');

        $dateRange = collect();
        $current = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        while ($current->lte($end)) {
            $dateRange->push($current->toDateString());
            $current->addDay();
        }

        $result = $employees->map(function ($employee) use ($attendances, $dateRange, $startDate, $endDate) {
            $employeeAttendances = $attendances[$employee->id] ?? collect();

            $present = [];
            $absent = [];

            foreach ($dateRange as $date) {
                $att = $employeeAttendances->firstWhere('date', $date);

                if ($att && $att->status === 'present') {
                    $present[] = [
                        'date' => $att->date,
                        'status' => $att->status,
                        'check_in' => $att->check_in,
                        'check_out' => $att->check_out,
                        'check_in_image' => $att->check_in_image,
                    ];
                } elseif($att && $att->status === 'absent') {
                    $absent[] = [
                        'date' => $date,
                        'status' => 'absent',
                        'check_in' => null,
                        'check_out' => null,
                        'check_in_image' => null,
                    ];
                }
            }

            return [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'department' => $employee->department->name ?? null,
                'group' => $employee->group->name ?? null,
                'attendances' => [
                    'present' => $present,
                    'absent' => $absent,
                ],
                'holidays' => \App\Models\EmployeeHolidays::where('employee_id', $employee->id)
                    ->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->get()
                    ->map(function ($holiday) {
                        return [
                            'start_date' => $holiday->start_date,
                            'end_date' => $holiday->end_date,
                            'comment' => $holiday->comment,
                        ];
                    })->toArray(),
            ];
        });

        if ($statusFilter === 'absent') {
            $result = $result->filter(function ($emp) {
                return count($emp['attendances']['absent']) > 0 && count($emp['attendances']['present']) === 0;
            })->values();
        }

        return response()->json([
            'date_range' => [$startDate, $endDate],
            'data' => $result,
        ]);
    }

    public function getRegions(): \Illuminate\Http\JsonResponse
    {
        $regions = Region::all();
        return response()->json($regions);
    }

    public function exportToExcel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        ini_set('memory_limit', '-1');
        return Excel::download(new EmployeeExport($request), 'xodimlar.xlsx');
    }

    public function getWorkingEmployees(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $employees = Employee::where('branch_id', $user->employee->branch_id)
            ->where('status', '!=','kicked')
            ->with('position')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($employees);
    }

    public function getEmployees(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string',
            'department_id' => 'nullable|integer|exists:departments,id',
            'group_id' => 'nullable|integer|exists:groups,id',
            'status' => 'nullable|string|in:working,kicked,reserv',
            'role_id' => 'nullable|integer|exists:roles,id',
            'type' => 'nullable|string|in:simple,aup', // <-- type validatsiyasi
            'payment_type' => 'nullable|string', // <-- payment_type validatsiyasi
        ]);

        $filters = $request->only(['search', 'payment_type','department_id', 'group_id', 'status', 'role_id', 'type']);
        $user = auth()->user();
        $oneMonthAgo = Carbon::now()->subMonth();

        $query = Employee::with('user.role', 'position')
            ->where('employees.branch_id', $user->employee->branch_id)
            ->leftJoin('employee_absences as ea', function ($join) use ($oneMonthAgo) {
                $join->on('ea.employee_id', '=', 'employees.id')
                    ->where(function ($q) use ($oneMonthAgo) {
                        $q->whereDate('ea.start_date', '>=', $oneMonthAgo)
                            ->orWhereDate('ea.end_date', '>=', $oneMonthAgo);
                    });
            })
            ->leftJoin('employee_holidays as eh', function ($join) use ($oneMonthAgo) {
                $join->on('eh.employee_id', '=', 'employees.id')
                    ->where(function ($q) use ($oneMonthAgo) {
                        $q->whereDate('eh.start_date', '>=', $oneMonthAgo)
                            ->orWhereDate('eh.end_date', '>=', $oneMonthAgo);
                    });
            })
            ->leftJoin('attendance as a', function ($join) use ($oneMonthAgo) {
                $join->on('a.employee_id', '=', 'employees.id')
                    ->where('a.status', 'absent')
                    ->whereDate('a.date', '>=', $oneMonthAgo)
                    ->whereRaw('EXTRACT(DOW FROM a.date) != 0');
            })
            ->select('employees.*')
            ->selectRaw('COUNT(DISTINCT ea.id) as absence_count')
            ->selectRaw('COUNT(DISTINCT eh.id) as holidays_count')
            ->selectRaw('COUNT(DISTINCT a.id) as attendance_absent_count')
            ->groupBy('employees.id')
            ->orderByRaw('(COUNT(DISTINCT a.id) - COUNT(DISTINCT eh.id)) DESC');


        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $searchLatin = transliterate_to_latin($search);
            $searchCyrillic = transliterate_to_cyrillic($search);

            $query->where(function ($q) use ($search, $searchLatin, $searchCyrillic) {
                foreach ([$search, $searchLatin, $searchCyrillic] as $term) {
                    $q->orWhereRaw('LOWER(employees.name) LIKE ?', ["%$term%"])
                        ->orWhereRaw('CAST(employees.id AS TEXT) LIKE ?', ["%$term%"])
                        ->orWhereHas('position', function ($q) use ($term) {
                            $q->whereRaw('LOWER(positions.name) LIKE ?', ["%$term%"]);
                        })
                        ->orWhereHas('user', function ($q) use ($term) {
                            $q->whereRaw('LOWER(users.username) LIKE ?', ["%$term%"])
                                ->orWhereHas('role', function ($q) use ($term) {
                                    $q->whereRaw('LOWER(roles.description) LIKE ?', ["%$term%"]);
                                });
                        });
                }
            });
        }


        $query->when($filters['department_id'] ?? false, fn($q) => $q->where('employees.department_id', $filters['department_id']))
            ->when($filters['group_id'] ?? false, fn($q) => $q->where('group_id', $filters['group_id']))
            ->when($filters['status'] ?? false, fn($q) => $q->where('employees.status', $filters['status']))
            ->when($filters['type'] ?? false, fn($q) => $q->where('employees.type', $filters['type']))
            ->when($filters['role_id'] ?? false, function ($q) use ($filters) {
                $q->whereHas('user', fn($q) => $q->where('role_id', $filters['role_id']));
            })
            ->when($filters['payment_type'] ?? false, function ($q) use ($filters) {
                $q->where('employees.payment_type', $filters['payment_type']);
            });

        $employees = $query->orderBy('name')->paginate(10);

        return (new GetEmployeeResourceCollection($employees))->response();
    }

    public function showEmployee($id): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        $employee = Employee::with('user.role', 'position', 'department', 'group')
            ->where('id', $id)
            ->where('branch_id', $user->employee->branch_id)
            ->firstOrFail();

        return (new \App\Http\Resources\GetEmployeeResource($employee))->response();
    }

    public function storeEmployees(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string',
            'group_id' => 'nullable|integer|exists:groups,id',
            'position_id' => 'nullable|integer|exists:positions,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'hiring_date' => 'nullable|date',
            'address' => 'nullable|string',
            'passport_number' => 'nullable|string',
            'passport_code' => 'nullable|string',
            'payment_type' => 'nullable|string',
            'comment' => 'nullable|string',
            'type' => 'nullable|string',
            'birthday' => 'nullable|date',
            'role_id' => 'nullable|integer|exists:roles,id',
            'status' => 'nullable|string|in:working,kicked,reserv',
            'img' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480',
            'salary' => 'nullable|numeric',
            'gender'=> 'required|string',
            'salary_visible' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $username = $this->generateCodeWithBranch(auth()->user()->employee->branch_id);

            $userId = DB::table('users')->insertGetId([
                'username' => $username,
                'password' => $this->hashPassword($request->phone),
                'role_id' => $request->role_id ?? null,
            ]);

            if ($request->hasFile('img')) {
                $file = $request->file('img');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('/public/images/', $filename);

                $img = 'images/' . $filename;
            } else {
                $img = null;
            }

            $employee = DB::table('employees')->insert([
                'name' => $request->name,
                'phone' => $request->phone,
                'group_id' => $request->group_id ?? null,
                'position_id' => $request->position_id,
                'department_id' => $request->department_id,
                'hiring_date' => $request->hiring_date,
                'address' => $request->address,
                'passport_number' => $request->passport_number ?? null,
                'passport_code' => $request->passport_code ?? null,
                'payment_type' => $request->payment_type,
                'comment' => $request->comment ?? null,
                'type' => $request->type,
                'birthday' => $request->birthday ?? null,
                'branch_id' => auth()->user()->employee->branch_id,
                'user_id' => $userId, // <-- endi bu joyda xatolik bo‘lmaydi
                'status' => $request->status,
                'img' => $img,
                'salary' => $request->salary ?? null,
                'gender' => $request->gender,
                'salary_visible' => $request->salary_visible ?? true,
            ]);


            DB::commit();

            Log::add(
                auth()->user()->id,
                'Yangi xodim qo‘shildi',
                'create',
                null,
                $employee
            );

            return response()->json(['status' => 'success', 'message' => 'Xodim muvaffaqiyatli qo‘shildi', 'employee' => $employee], 201);
        } catch (\Exception $e) {
            DB::rollBack();

             Log::add(
                auth()->user()->id,
                'Xodim qo‘shishda xatolik',
                'error',
                null,
                $e->getMessage()
             );

            return response()->json(['status' => 'error', 'message' => 'Xodimni qo‘shishda xatolik: ' . $e->getMessage()], 500);
        }

    }

    public function storeFastEmployee(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'group_id' => 'nullable|integer|exists:groups,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'position_id' => 'nullable|integer|exists:positions,id',
        ]);

        try {
            DB::beginTransaction();

            $branchId = auth()->user()->employee->branch_id;

            // Doimiy ishlatiladigan telefon raqam yoki random
            $phone = '+998991111111'; // yoki uniq qilish uchun: '+99899' . rand(1000000, 9999999);

            // Username avtomatik
            $username = $this->generateCodeWithBranch($branchId);

            // Foydalanuvchi yaratish
            $userId = DB::table('users')->insertGetId([
                'username' => $username,
                'password' => $this->hashPassword($phone),
                'role_id' => null,
            ]);

            // Xodim yaratish
            $employee = DB::table('employees')->insert([
                'name' => $request->name,
                'phone' => $phone,
                'group_id' => $request->group_id,
                'position_id' => $request->position_id, // masalan, default pozitsiya ID
                'department_id' => $request->department_id,
                'hiring_date' => now(),
                'address' => null,
                'passport_number' => null,
                'passport_code' => null,
                'payment_type' => 'piece_work',
                'comment' => null,
                'type' => 'simple',
                'birthday' => null,
                'branch_id' => $branchId,
                'user_id' => $userId,
                'status' => 'working',
                'img' => null,
                'gender' => $request->gender ?? null,
                'salary_visible' => true,
            ]);

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Tezkor xodim qo‘shildi',
                'create',
                null,
                $employee
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Tezkor xodim muvaffaqiyatli qo‘shildi',
                'employee' => $employee
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::add(
                auth()->user()->id,
                'Tezkor xodim qo‘shishda xatolik',
                'error',
                null,
                $e->getMessage()
            );

            return response()->json([
                'status' => 'error',
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateEmployees(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string',
            'phone' => 'nullable|string',
            'group_id' => 'nullable|integer|exists:groups,id',
            'position_id' => 'nullable|integer|exists:positions,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'hiring_date' => 'nullable|date',
            'address' => 'nullable|string',
            'passport_number' => 'nullable|string',
            'passport_code' => 'nullable|string',
            'payment_type' => 'nullable|string',
            'comment' => 'nullable|string',
            'type' => 'nullable|string',
            'birthday' => 'nullable|date',
            'role_id' => 'nullable',
            'status' => 'nullable|string|in:working,kicked,reserv',
            'salary' => 'nullable|numeric',
            'gender'=> 'nullable|string',
            'img' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480',
            'salary_visible' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            if ($request->hasFile('img')) {
                $file = $request->file('img');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('/public/images/', $filename);

                $img = 'images/' . $filename;
            } else {
                $img = null;
            }

            $employee = Employee::findOrFail($id);
            $oldData = $employee->toArray();
            $user = User::findOrFail($employee->user_id);
            $user->update([
                'role_id' => $request->role_id ?? $user->role_id,
            ]);

            if ($request->status === 'kicked') {
                $employee->update([
                    'name' => $request->name ?? $employee->name,
                    'phone' => $request->phone ?? $employee->phone,
                    'group_id' => $request->group_id ?? $employee->group_id,
                    'position_id' => $request->position_id ?? $employee->position_id,
                    'department_id' => $request->department_id ?? $employee->department_id,
                    'hiring_date' => $request->hiring_date ?? $employee->hiring_date,
                    'address' => $request->address ?? $employee->address,
                    'passport_number' => $request->passport_number ?? $employee->passport_number,
                    'passport_code' => $request->passport_code ?? $employee->passport_code,
                    'payment_type' => $request->payment_type ?? $employee->payment_type,
                    'comment' => $request->comment ?? $employee->comment,
                    'type' => $request->type ?? $employee->type,
                    'birthday' => $request->birthday ?? $employee->birthday,
                    'img' => $img ?? $employee->img ?? null,
                    'status' => $request->status ?? 'kicked',
                    'kicked_date' => now(),
                    'salary' => $request->salary ?? $employee->salary,
                    'gender' => $request->gender ?? $employee->gender,
                    'bonus' => $request->bonus ?? $employee->bonus,
                    'salary_visible' => $request->salary_visible ?? $employee->salary_visible,
                ]);
            } else {
                $employee->update([
                    'name' => $request->name ?? $employee->name,
                    'phone' => $request->phone ?? $employee->phone,
                    'group_id' => $request->filled('group_id') ? (int) $request->group_id : null,
                    'position_id' => $request->position_id ?? $employee->position_id,
                    'department_id' => $request->department_id ?? $employee->department_id,
                    'hiring_date' => $request->hiring_date ?? $employee->hiring_date,
                    'address' => $request->address ?? $employee->address,
                    'passport_number' => $request->passport_number ?? $employee->passport_number,
                    'passport_code' => $request->passport_code ?? $employee->passport_code,
                    'payment_type' => $request->payment_type ?? $employee->payment_type,
                    'comment' => $request->comment ?? $employee->comment,
                    'type' => $request->type ?? $employee->type,
                    'birthday' => $request->birthday ?? $employee->birthday,
                    'img' => $img ?? $employee->img ?? null,
                    'salary' => $request->salary ?? $employee->salary,
                    'kicked_date' => null,
                    'gender' => $request->gender ?? $employee->gender,
                    'status' => $request->status ?? 'working',
                    'bonus' => $request->bonus ?? $employee->bonus,
                    'salary_visible' => $request->salary_visible ?? $employee->salary_visible,
                ]);
            }



            DB::commit();

             Log::add(
                auth()->user()->id,
                'Xodim yangilandi',
                'edit',
                $oldData,
                $employee
             );

            return response()->json(['status' => 'success', 'message' => 'Xodim muvaffaqiyatli yangilandi', 200]);
        } catch (\Exception $e) {
            DB::rollBack();
             Log::add(
                auth()->user()->id,
                "Xodimni yangilashda xatolik",
                "error",
                null,
                $e->getMessage()
             );
            return response()->json(['status' => 'error', 'message' => 'Xodimni yangilashda xatolik: ' . $e->getMessage()], 500);
        }
    }

    protected function hashPassword($password): string
    {
        $options = ['cost' => 12];
        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    private function generateCodeWithBranch(int $branchId): string
    {
        $baseCode = $branchId;

        // Oxirgi kodni topish
        $lastUser = User::where('username', 'like', $baseCode . '%')
            ->whereHas('employee', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->orderByDesc('id')
            ->first();

        if ($lastUser && preg_match('/^' . $baseCode . '(\d{4})$/', $lastUser->username, $matches)) {
            $lastCode = (int) $matches[1];
        } else {
            $lastCode = 999;
        }

        // Unique username topilguncha qayta urinish
        do {
            $lastCode++;
            $newUsername = $baseCode . str_pad($lastCode, 4, '0', STR_PAD_LEFT);
        } while (User::where('username', $newUsername)->exists());

        return $newUsername;
    }

    public function getRoles(): \Illuminate\Http\JsonResponse
    {
        $roles = Role::orderBy('name')
            ->get();

        return response()->json($roles, 200);
    }

    public function getAupEmployee(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $employees = Employee::where('branch_id', $user->employee->branch_id)
            ->where('type', 'aup')
            ->where('status', 'working')
            ->with('department', 'position', 'group')
            ->get();

        return response()->json($employees, 200);
    }

    public function getPositions(): \Illuminate\Http\JsonResponse
    {
        $positions = DB::table('positions')
            ->orderBy('name', 'desc')
            ->get();

        return response()->json($positions, 200);
    }

    public function storePositions(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => 'required|integer|exists:departments,id',
            'duties' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $position = DB::table('positions')->insert([
                'name' => $request->name,
                'department_id' => $request->department_id,
                'duties' => $request->duties ?? null,
            ]);

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Yangi lavozim qo‘shildi',
                'create',
                null,
                $position
            );

            return response()->json(['status' => 'success', 'message' => 'Lavozim muvaffaqiyatli qo‘shildi'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lavozimni qo‘shishda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function updatePositions(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'duties' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $position = DB::table('positions')->where('id', $id)->update([
                'name' => $request->name,
                'department_id' => $request->department_id ?? null,
                'duties' => $request->duties ?? null,
            ]);

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Lavozim yangilandi',
                'edit',
                null,
                $position
            );

            return response()->json(['status' => 'success', 'message' => 'Lavozim muvaffaqiyatli yangilandi'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lavozimni yangilashda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function getDepartments(): \Illuminate\Http\JsonResponse
    {
        try {
            $departments = MainDepartment::where('branch_id', auth()->user()->employee->branch_id)
                ->with(
                    'departments',
                    'departments.groups',
                    'departments.groups.responsibleUser',
                )
                ->get();
            return response()->json($departments, 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Bo‘limlarni olishda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function storeDepartments(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'responsible_user_id' => 'nullable|integer|exists:users,id',
            'main_department_id' => 'nullable|integer|exists:main_department,id',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'break_time' => 'nullable|integer|min:0',
            'groups' => 'nullable|array',
            'groups.*.name' => 'required|string|max:255',
            'groups.*.responsible_user_id' => 'nullable|integer|exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            $department = Department::create([
                'name' => $request->name,
                'branch_id' => auth()->user()->employee->branch_id,
                'responsible_user_id' => $request->responsible_user_id ?? null,
                'main_department_id' => $request->main_department_id ?? null,
                'start_time' => $request->start_time ?? "07:30:00",
                'end_time' => $request->end_time ?? "17:30:00",
                'break_time' => $request->break_time ?? 0,
            ]);

            if ($request->filled('groups')) {
                foreach ($request->groups as $groupData) {
                    $department->groups()->create([
                        'name' => $groupData['name'],
                        'department_id' => $department->id,
                        'responsible_user_id' => $groupData['responsible_user_id'] ?? 1,
                    ]);
                }
            }

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Yangi bo‘lim va guruhlari qo‘shildi',
                'create',
                null,
                $department->load('groups')->toArray()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bo‘lim va guruhlari muvaffaqiyatli qo‘shildi',
                'department' => $department->load('groups')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Bo‘limni qo‘shishda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateDepartments(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'responsible_user_id' => 'sometimes|integer|exists:users,id',
            'main_department_id' => 'sometimes|integer|exists:main_department,id',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'break_time' => 'sometimes|integer|min:0',
            'groups' => 'nullable|array',
            'groups.*.name' => 'required|string|max:255',
            'groups.*.id' => 'nullable|integer|exists:groups,id',
            'groups.*.responsible_user_id' => 'nullable|integer|exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            $department = Department::findOrFail($id);
            $oldData = $department->load('groups')->toArray();

            $department->update([
                'name' => $request->name,
                'responsible_user_id' => $request->responsible_user_id ?? $department->responsible_user_id,
                'main_department_id' => $request->main_department_id ?? $department->main_department_id,
                'start_time' => $request->start_time ?? $department->start_time,
                'end_time' => $request->end_time ?? $department->end_time,
                'break_time' => $request->break_time ?? $department->break_time,
                'branch_id' => auth()->user()->employee->branch_id,
            ]);

            if ($request->filled('groups')) {
                foreach ($request->groups as $groupData) {
                    if (!empty($groupData['id'])) {
                        // Update existing group
                        $group = $department->groups()->where('id', $groupData['id'])->first();
                        if ($group) {
                            $group->update([
                                'name' => $groupData['name'],
                                'responsible_user_id' => $groupData['responsible_user_id'] ?? $group->responsible_user_id,
                            ]);
                        }
                    } else {
                        // Create new group
                        $department->groups()->create([
                            'name' => $groupData['name'],
                            'responsible_user_id' => $groupData['responsible_user_id'] ?? 1,
                        ]);
                    }
                }
            }

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Bo‘lim va guruhlari yangilandi',
                'edit',
                $oldData,
                $department->load('groups')->toArray()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bo‘lim muvaffaqiyatli yangilandi',
                'department' => $department->load('groups')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Bo‘limni yangilashda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword($id): \Illuminate\Http\JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);

            $user = User::findOrFail($employee->user_id);

            $user->password = $this->hashPassword($employee->phone);

            $user->save();

            Log::add(
                auth()->user()->id,
                'Parol tiklandi',
                'edit',
                null,
                $user
            );

            return response()->json(['status' => 'success', 'message' => 'Parol tiklandi'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Parolni tiklashda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function getLids(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $query = Lid::where('branch_id', auth()->user()->employee->branch_id);

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%$search%"])
                    ->orWhereRaw('LOWER(phone) LIKE ?', ["%$search%"])
                    ->orWhereRaw('LOWER(address) LIKE ?', ["%$search%"])
                    ->orWhereRaw('LOWER(comment) LIKE ?', ["%$search%"]);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $lids = $query->orderByDesc('id')->paginate(10);

        return response()->json($lids, 200);
    }

    public function storeLid(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:500',
            'birth_day' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480',
        ]);


        try {
            DB::beginTransaction();

            $lid = new Lid();
            $lid->name = $request->name;
            $lid->phone = $request->phone;
            $lid->address = $request->address;
            $lid->comment = $request->comment;
            $lid->birth_day = $request->birth_day;
            $lid->status = 'active';
            $lid->branch_id = auth()->user()->employee->branch_id;

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $fileName = time() . '_' . $image->getClientOriginalName();
                $image->storeAs('/public/images/', $fileName);
                $lid->image = 'images/' . $fileName;
            }

            $lid->save();

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Yangi lid qo‘shildi',
                'create',
                null,
                $lid
            );

            return response()->json(['status' => 'success', 'message' => 'Lid muvaffaqiyatli qo‘shildi', 'lid' => $lid], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lidni qo‘shishda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function updateLid(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        //PATH bo'lishi kerak
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:500',
            'birth_day' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480',
        ]);

        try {
            DB::beginTransaction();

            $lid = Lid::findOrFail($id);
            $oldData = $lid->toArray();

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $fileName = time() . '_' . $image->getClientOriginalName();
                $image->storeAs('/public/images/', $fileName);
                $lid->image = 'images/' . $fileName;
            }

            $lid->name = $request->name ?? $lid->name;
            $lid->phone = $request->phone ?? $lid->phone;
            $lid->address = $request->address ?? $lid->address;
            $lid->comment = $request->comment ?? $lid->comment;
            $lid->birth_day = $request->birth_day ?? $lid->birth_day;

            if ($request->has('status')) {
                if ($request->status === 'active') {
                    $lid->status = 'active';
                } elseif ($request->status === 'inactive') {
                    $lid->status = 'inactive';
                }
            }

            $lid->save();

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Lid yangilandi',
                'edit',
                $oldData,
                $lid
            );

            return response()->json(['status' => 'success', 'message' => 'Lid muvaffaqiyatli yangilandi', 'lid' => $lid], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lidni yangilashda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function showLid(Lid $lid): \Illuminate\Http\JsonResponse
    {
        return response()->json($lid);
    }

    public function exportEmployeeAttendance(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $fileName = 'employee_attendance_' . now()->format('Y_m_d_His') . '.xlsx';

        return Excel::download(
            new EmployeeAttendanceExport($request->start_date, $request->end_date),
            $fileName
        );
    }

}
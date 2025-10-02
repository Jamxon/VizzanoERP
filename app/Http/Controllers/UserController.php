<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetUserResource;
use App\Models\Employee;
use App\Models\Log;
use App\Models\User;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class UserController extends Controller
{
    public function getEmployeeEfficiency(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'department_id' => 'nullable|integer',
            'group_id' => 'nullable|integer',
        ]);

        try {
            $branchId = auth()->user()->employee->branch_id ?? null;

            // 1️⃣ Tarification loglari
            $logs = \DB::table('employee_tarification_logs as etl')
                ->join('employees as e', 'e.id', '=', 'etl.employee_id')
                ->join('tarifications as t', 't.id', '=', 'etl.tarification_id')
                ->select(
                    'etl.employee_id',
                    'e.name',
                    'e.img',
                    'e.salary_visible',
                    'e.branch_id',
                    'e.department_id',
                    'e.group_id',
                    'etl.date',
                    \DB::raw('SUM(etl.quantity * t.second) as total_seconds'),
                    \DB::raw('SUM(etl.amount_earned) as tarification_earned')
                )
                ->where('e.branch_id', $branchId)
//                ->where('payment_type', 'piece_work')
                ->when(
                    auth()->user()->role->name === 'groupMaster' && auth()->user()->employee->group_id,
                    fn($q) => $q->where('e.group_id', auth()->user()->employee->group_id)
                )
                ->when($request->department_id, fn($q) => $q->where('e.department_id', $request->department_id))
                ->when($request->group_id, fn($q) => $q->where('e.group_id', $request->group_id))
                ->whereBetween('etl.date', [$request->start_date, $request->end_date])
                ->groupBy('etl.employee_id', 'e.name', 'e.img', 'e.salary_visible', 'e.branch_id', 'e.department_id', 'e.group_id', 'etl.date')
                ->get();

            // 2️⃣ Attendance kunlari va summasi
            $attendance = \DB::table('attendance as a')
                ->join('employees as e', 'e.id', '=', 'a.employee_id')
                ->leftJoin('attendance_salary as ats', function ($q) {
                    $q->on('ats.employee_id', '=', 'a.employee_id')
                        ->on('ats.date', '=', 'a.date');
                })
                ->select(
                    'a.employee_id',
                    \DB::raw('COUNT(DISTINCT a.date) as attended_days'),
                    \DB::raw('COALESCE(SUM(ats.amount),0) as attendance_earned')
                )
                ->where('e.branch_id', $branchId)
                ->where('a.status', 'present')
                ->when($request->department_id, fn($q) => $q->where('e.department_id', $request->department_id))
                ->when($request->group_id, fn($q) => $q->where('e.group_id', $request->group_id))
                ->whereBetween('a.date', [$request->start_date, $request->end_date])
                ->groupBy('a.employee_id')
                ->get()
                ->keyBy('employee_id');

            // 3️⃣ PHP da yig‘ish
            $results = $logs->groupBy('employee_id')->map(function ($rows, $employeeId) use ($attendance) {
                $first = $rows->first();

                $dailyResult = $rows->map(function ($r) {
                    $minutes = round($r->total_seconds / 60, 2);
                    $percent = round(($minutes / 500) * 100, 2);
                    return [
                        'date' => $r->date,
                        'worked_seconds' => (int) $r->total_seconds,
                        'worked_minutes' => $minutes,
                        'efficiency_percent' => $percent,
                        'tarification_earned' => (float) $r->tarification_earned,
                    ];
                })->sortBy('date')->values(); // 🔥 date bo‘yicha tartiblanadi


                $att = $attendance[$employeeId] ?? null;
                $attended_days = $att->attended_days ?? 0;
                $attendance_earned = (float) ($att->attendance_earned ?? 0);

                $tarification_total = $rows->sum('tarification_earned');
                $total_earned = $tarification_total + $attendance_earned;

                return [
                    'employee_id' => $employeeId,
                    'employee_name' => $first->name,
                    'salary_visible' => $first->salary_visible,
                    'image' => $first->img
                        ? (filter_var($first->img, FILTER_VALIDATE_URL)
                            ? $first->img
                            : url('storage/' . $first->img))
                        : null,
                    'branch_id' => $first->branch_id,
                    'department_id' => $first->department_id,
                    'group_id' => $first->group_id,
                    'attended_days' => $attended_days,
                    'tarification_earned' => $tarification_total,
                    'attendance_earned' => $attendance_earned,
                    'total_earned' => $total_earned,
                    'avg_per_day' => $attended_days > 0 ? round($total_earned / $attended_days, 2) : 0,
                    'days' => $dailyResult->values(),
                ];
            })->values();

            return response()->json($results);

        } catch (\Throwable $e) {
            \Log::error("getEmployeeEfficiency error", ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getProfile(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('id', $user->employee->id)->first();

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $start = $startDate ? Carbon::parse($startDate) : null;
        $end = $endDate ? Carbon::parse($endDate) : null;

        if ($startDate && $endDate) {
            $employee->load([
                'attendanceSalaries' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                },
                'employeeTarificationLogs' => function ($query) use ($startDate, $endDate) {
                    $query->whereHas('tarification.tarificationCategory.submodel.orderModel.order', function ($q) use ($startDate) {
                        // Oyning birinchi kuni qilib olish
                        $monthStart = Carbon::parse($startDate)->startOfMonth()->toDateString(); // masalan: 2025-09-01
                        $q->whereIn('id', function ($subQuery) use ($monthStart) {
                            $subQuery->select('order_id')
                                ->from('monthly_selected_orders')
                                ->where('month', $monthStart);
                        });
                    })
                    ->select('id', 'employee_id', 'date', 'tarification_id', 'quantity', 'is_own', 'amount_earned')
                        ->with(['tarification' => function ($q) {
                            $q->select('id', 'name', 'code', 'second', 'summa', 'tarification_category_id')
                                ->with([
                                    'tarificationCategory' => function ($q2) {
                                        $q2->select('id', 'submodel_id')
                                            ->with([
                                                'submodel' => function ($q3) {
                                                    $q3->select('id', 'order_model_id', 'submodel_id')
                                                        ->with([
                                                            'orderModel' => function ($q4) {
                                                                $q4->select('id', 'model_id')
                                                                    ->with('model:id,name');
                                                            },
                                                            'submodel' => function ($q5) {
                                                                $q5->select('id', 'name');
                                                            }
                                                        ]);
                                                }
                                            ]);
                                    }
                                ]);
                        }]);
                },
                'attendances' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                },
                'employeeHolidays' => function ($query) use ($startDate, $endDate) {
                    $query->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate])
                            ->orWhere(function ($q2) use ($startDate, $endDate) {
                                $q2->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                            });
                    });
                },
                'employeeSalaries' => function ($query) use ($start, $end) {
                    if ($start && $end) {
                        $startYM = $start->year * 100 + $start->month;
                        $endYM = $end->year * 100 + $end->month;
                        // filter where (year, month) between start and end inclusive
                        $query->whereRaw('(year * 100 + month) BETWEEN ? AND ?', [$startYM, $endYM]);
                    }
                },
                'employeeAbsences' => function ($query) use ($startDate, $endDate) {
                    $query->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate])
                            ->orWhere(function ($q2) use ($startDate, $endDate) {
                                $q2->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                            });
                    });
                },
                'salaryPayments' => function ($query) use ($startDate, $endDate, $start, $end) {
                    if ($start && $end) {
                        $query->whereBetween('month', [$startDate, $endDate]);
                    }
                }
            ]);
        } else {
            $employee->load([
                'attendanceSalaries',
                'employeeTarificationLogs' => function ($query) use ($startDate, $endDate) {
                    $query->whereHas('tarification.tarificationCategory.submodel.orderModel.order', function ($q) use ($startDate) {
                        // Oyning birinchi kuni qilib olish
                        $monthStart = Carbon::parse($startDate)->startOfMonth()->toDateString(); // masalan: 2025-09-01
                        $q->whereIn('id', function ($subQuery) use ($monthStart) {
                            $subQuery->select('order_id')
                                ->from('monthly_selected_orders')
                                ->where('month', $monthStart);
                        });
                    })
                    ->whereBetween('date', [$startDate, $endDate])
                        ->select('id', 'employee_id', 'date', 'tarification_id', 'quantity', 'is_own', 'amount_earned')
                        ->with(['tarification' => function ($q) {
                            $q->select('id', 'name', 'code', 'second', 'summa', 'tarification_category_id')
                                ->with([
                                    'tarificationCategory' => function ($q2) {
                                        $q2->select('id', 'submodel_id')
                                            ->with([
                                                'submodel' => function ($q3) {
                                                    $q3->select('id', 'order_model_id', 'submodel_id')
                                                        ->with([
                                                            'orderModel' => function ($q4) {
                                                                $q4->select('id', 'model_id')
                                                                    ->with('model:id,name');
                                                            },
                                                            'submodel' => function ($q5) {
                                                                $q5->select('id', 'name');
                                                            }
                                                        ]);
                                                }
                                            ]);
                                    }
                                ]);
                        }]);
                },
                'attendances',
                'employeeHolidays',
                'employeeAbsences',
                'employeeSalaries',
                'salaryPayments' => function ($query) use ($startDate, $endDate, $start, $end) {
                    if ($start && $end) {
                        $query->whereBetween('month', [$startDate, $endDate]);
                    }
                }

            ]);
        }

        $resource = new GetUserResource($employee);

        return response()->json($resource);
    }

    public function updateProfile(Request $request, Employee $employee): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'username' => 'required|string|max:255|unique:users,username,' . $employee->user_id,
                'password' => 'sometimes|nullable|string|min:6',
            ]);

            $user = User::where('id', $employee->user_id)->first();

            $oldUserData = $user->only(['username', 'password']);
            $oldEmployeeData = $employee->only(['img']);

            $updateData = [
                'username' => $request->username,
            ];

            // Password faqat kelsa va bo'sh bo'lmasa hash qilamiz
            if ($request->filled('password')) {
                $updateData['password'] = $this->hashPassword($request->password);
            }

            $user->update($updateData);

            if ($request->hasFile('img')) {
                $file = $request->file('img');
                $filename = time() . '.' . $file->getClientOriginalExtension();

                // S3 ga yuklaymiz
                $path = $file->storeAs('employees', $filename, 's3');

                Storage::disk('s3')->setVisibility($path, 'public');

                $employee->img = Storage::disk('s3')->url($path);
                $employee->save();
            }

            Log::add(
                auth()->id(),
                "Profil ma'lumotlari yangilandi",
                'edit',
                [
                    'username' => $oldUserData['username'],
                    'img' => $oldEmployeeData['img'],
                ],
                [
                    'username' => $user->username,
                    'img' => $employee->img,
                ]
            );

            return response()->json(['message' => 'Profile updated successfully']);
        } catch (\Exception $exception) {
            return response()->json(['error' => 'Failed to update profile: ' . $exception->getMessage()], 500);
        }
    }

    protected function hashPassword($password): string
    {
        $options = ['cost' => 12];
        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    public function show(User $user): \Illuminate\Http\JsonResponse
    {
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $resource = new GetUserResource($employee);

        return response()->json($resource);
    }

    public function storeIssue(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'description' => 'required|string|max:255',
                'image' => 'sometimes|nullable|image|max:20480',
                'for_admins' => ['required', 'in:true,false,1,0,yes,no'],
            ]);

            $imageUrl = null;

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = 'issues/' . time() . '.' . $file->getClientOriginalExtension();

                // 👉 Faylni S3 ga yuklash
                Storage::disk('s3')->put($filename, file_get_contents($file), 'public');

                // 👉 Public URL olish
                $imageUrl = Storage::disk('s3')->url($filename);
            }

            $issue = Issue::create([
                'user_id' => auth()->id(),
                'description' => $request->description,
                'image' => $imageUrl, // endi to‘g‘ridan-to‘g‘ri S3 url saqlanadi
            ]);

            Log::add(
                auth()->id(),
                "Yangi muammo qo'shildi",
                'create',
                [],
                [
                    'description' => $request->description,
                    'image' => $imageUrl,
                ]
            );

            // Foydalanuvchi va bog‘liq relationlarni yuklab olish
            $user = auth()->user()->load(['employee.group', 'role']);

            // Xabarni yig‘ish
            $messageLines = [
                "#muammo<b>🛠 Yangi muammo bildirildi!</b>",
                "",
                "👤 Foydalanuvchi: " . ($user->employee->name ?? 'Noma\'lum') . " (" . ($user->role?->name ?? '—') . ")",
            ];

            if (!empty($user->employee->group?->name)) {
                $messageLines[] = "👥 Guruh: " . $user->employee->group->name;
            }

            $messageLines[] = "📝 Filial: " . ($user->employee->branch?->name ?? 'Noma\'lum');
            $messageLines[] = "📝 Tavsif: " . $request->description;

            $message = implode("\n", $messageLines);

            // Default bot va chat
            $botToken = "8120915071:AAGVvrYz8WBfhABMJWtlDzdFgUELUUKTj5Q";
            $chatId = "-1002877502358";

            if ($request->for_admins === 'true') {
                $botToken = "8325344740:AAECc5ej6v0XVXcPUA5prQYo9HAli8VzkxI";
                $chatId = "-1002731783863";
            }

            // Telegramga yuborish
            if ($imageUrl) {
                $response = Http::post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                    'chat_id' => $chatId,
                    'photo' => $imageUrl, // 👈 S3 URL yuborilyapti
                    'caption' => $message,
                    'parse_mode' => 'HTML',
                ]);
            } else {
                $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]);
            }

            if ($response->successful()) {
                return response()->json([
                    'message' => 'Fikringiz uchun rahmat! Muammo yuborildi.',
                ], 201);
            } else {
                return response()->json([
                    'error' => 'Failed to send issue to Telegram: ' . $response->body()
                ], 500);
            }

        } catch (\Exception $exception) {
            return response()->json([
                'error' => 'Failed to report issue: ' . $exception->getMessage()
            ], 500);
        }
    }

    public function showEmployee(Employee $employee, Request $request): \Illuminate\Http\JsonResponse
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $relations = ['department', 'position', 'group'];

        // 🔹 KPI, avans va boshqa maosh ma'lumotlari uchun employeeSalaries
        $relations['employeeSalaries'] = function ($query) use ($start_date, $end_date) {
            if ($start_date && $end_date) {
                $query->whereBetween('created_at', [$start_date, $end_date]);
            }
        };

        $relations['salaryPayments'] = function ($query) use ($start_date, $end_date) {
            if ($start_date && $end_date) {
                $query->whereBetween('date', [$start_date, $end_date]);
            }
        };

        $relations['attendanceSalaries'] = function ($query) use ($start_date, $end_date) {
            if ($start_date && $end_date) {
                $query->whereBetween('date', [$start_date, $end_date]);
            }
        };

        if ($employee->payment_type === 'piece_work') {
            $relations['attendances'] = function ($query) use ($start_date, $end_date) {
                if ($start_date && $end_date) {
                    $query->whereBetween('date', [$start_date, $end_date]);
                }
            };

            $relations['employeeTarificationLogs'] = function ($query) use ($start_date, $end_date) {

                // 🔹 Oyni monthly_selected_orders bilan filterlash
                if ($start_date) {
                    $monthStart = Carbon::parse($start_date)->startOfMonth()->format('Y-m'); // faqat yil-oy formatida

                    $query->whereHas('tarification.tarificationCategory.submodel.orderModel.order', function ($q) use ($monthStart) {
                        $q->whereIn('id', function ($subQuery) use ($monthStart) {
                            $subQuery->select('order_id')
                                ->from('monthly_selected_orders')
                                ->whereRaw("to_char(month, 'YYYY-MM') = ?", [$monthStart]);
                        });
                    });
                }

                $query->select('id', 'employee_id', 'date', 'tarification_id', 'quantity', 'is_own', 'amount_earned')
                    ->with(['tarification' => function ($q) {
                        $q->select('id', 'name', 'code', 'second', 'summa', 'tarification_category_id')
                            ->with([
                                'tarificationCategory' => function ($q2) {
                                    $q2->select('id', 'submodel_id')
                                        ->with([
                                            'submodel' => function ($q3) {
                                                $q3->select('id', 'order_model_id', 'submodel_id')
                                                    ->with([
                                                        'orderModel' => function ($q4) {
                                                            $q4->select('id', 'model_id')
                                                                ->with('model:id,name');
                                                        },
                                                        'submodel' => function ($q5) {
                                                            $q5->select('id', 'name');
                                                        }
                                                    ]);
                                            }
                                        ]);
                                }
                            ]);
                    }]);
            };

        } else {
            $relations['employeeTarificationLogs'] = function ($query) use ($start_date, $end_date) {

                // 🔹 Oyni monthly_selected_orders bilan filterlash
                if ($start_date) {
                    $monthStart = Carbon::parse($start_date)->startOfMonth()->format('Y-m'); // faqat yil-oy formatida

                    $query->whereHas('tarification.tarificationCategory.submodel.orderModel.order', function ($q) use ($monthStart) {
                        $q->whereIn('id', function ($subQuery) use ($monthStart) {
                            $subQuery->select('order_id')
                                ->from('monthly_selected_orders')
                                ->whereRaw("to_char(month, 'YYYY-MM') = ?", [$monthStart]);
                        });
                    });
                }

                $query->select('id', 'employee_id', 'date', 'tarification_id', 'quantity', 'is_own', 'amount_earned')
                    ->with(['tarification' => function ($q) {
                        $q->select('id', 'name', 'code', 'second', 'summa', 'tarification_category_id')
                            ->with([
                                'tarificationCategory' => function ($q2) {
                                    $q2->select('id', 'submodel_id')
                                        ->with([
                                            'submodel' => function ($q3) {
                                                $q3->select('id', 'order_model_id', 'submodel_id')
                                                    ->with([
                                                        'orderModel' => function ($q4) {
                                                            $q4->select('id', 'model_id')
                                                                ->with('model:id,name');
                                                        },
                                                        'submodel' => function ($q5) {
                                                            $q5->select('id', 'name');
                                                        }
                                                    ]);
                                            }
                                        ]);
                                }
                            ]);
                    }]);
            };

            $relations['attendances'] = function ($query) use ($start_date, $end_date) {
                if ($start_date && $end_date) {
                    $query->whereBetween('date', [$start_date, $end_date]);
                }
            };
        }

        $employee->load($relations);

        // 🟨 Tatillar va yo‘qliklar:
        $absenceQuery = $employee->employeeAbsences();
        $holidayQuery = $employee->employeeHolidays();

        if ($start_date && $end_date) {
            $absenceQuery->where(function ($query) use ($start_date, $end_date) {
                $query->whereBetween('start_date', [$start_date, $end_date])
                    ->orWhereBetween('end_date', [$start_date, $end_date]);
            });

            $holidayQuery->where(function ($query) use ($start_date, $end_date) {
                $query->whereBetween('start_date', [$start_date, $end_date])
                    ->orWhereBetween('end_date', [$start_date, $end_date]);
            });
        }

        $absences = $absenceQuery->get(['id', 'start_date', 'end_date', 'comment', 'image']);
        $holidays = $holidayQuery->get(['id', 'start_date', 'end_date', 'comment', 'image']);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'gender' => $employee->gender,
                'phone' => $employee->phone,
                'address' => $employee->address,
                'birthday' => $employee->birthday,
                'payment_type' => $employee->payment_type,
                'status' => $employee->status,
                'img' => $employee->img,
                'hiring_date' => $employee->hiring_date,
                'kicked_date' => $employee->kicked_date,
                'passport_number' => $employee->passport_number,
                'passport_code' => $employee->passport_code,
                'comment' => $employee->comment,
                'salary' => $employee->salary,
                'bonus' => $employee->bonus,
                'balance' => $employee->balance,
                'type' => $employee->type,
                'created_at' => $employee->created_at,
                'updated_at' => $employee->updated_at,
                'user_id' => $employee->user_id,
                'branch_id' => $employee->branch_id,
                'position_id' => $employee->position_id,
                'department_id' => $employee->department_id,
                'group_id' => $employee->group_id,

                // Relations
                'position' => $employee->position,
                'department' => $employee->department,
                'group' => $employee->group,
                'attendances' => $employee->attendances,
                'attendance_salaries' => $employee->attendanceSalaries,
                'employee_salaries' => $employee->employeeSalaries,
                'salary_payments' => $employee->salaryPayments ?? null,
                'salary_visible' => $employee->salary_visible,
                'employee_tarification_logs' => $employee->employeeTarificationLogs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'employee_id' => $log->employee_id,
                        'date' => $log->date,
                        'tarification_id' => $log->tarification_id,
                        'quantity' => $log->quantity,
                        'amount_earned' => $log->amount_earned,
                        'is_own' => $log->is_own,
                        'model' => $log->tarification?->tarificationCategory?->submodel?->orderModel?->model ?? null,
                        'submodel' => $log->tarification?->tarificationCategory?->submodel?->submodel ?? null,
                        'tarification' => [
                            'id' => $log->tarification?->id,
                            'name' => $log->tarification?->name,
                            'code' => $log->tarification?->code,
                            'second' => $log->tarification?->second,
                            'summa' => $log->tarification?->summa,
                        ]
                    ];
                }),
            ],
            'absences' => $absences->map(function ($absence) {
                return [
                    'id' => $absence->id,
                    'start_date' => $absence->start_date,
                    'end_date' => $absence->end_date,
                    'comment' => $absence->comment,
                    'image' => $absence->image ? url('storage/' . $absence->image) : null,
                ];
            }),
            'holidays' => $holidays->map(function ($holiday) {
                return [
                    'id' => $holiday->id,
                    'start_date' => $holiday->start_date,
                    'end_date' => $holiday->end_date,
                    'comment' => $holiday->comment,
                    'image' => $holiday->image ? url('storage/' . $holiday->image) : null,
                ];
            }),
        ]);
    }

}
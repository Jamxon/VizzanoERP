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


class UserController extends Controller
{
    public function getProfile(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('id', $user->employee->id)->first();

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        if ($startDate && $endDate) {
            $employee->load([
                'attendanceSalaries' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                },
                'employeeTarificationLogs' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate])
                        ->select('id', 'employee_id', 'date', 'tarification_id', 'quantity', 'is_own', 'amount_earned')
                        ->with(['tarification' => function ($q) {
                            $q->select('id', 'name', 'code', 'second', 'summa', 'tarification_category_id')
                                ->with([
                                    'tarificationCategory' => function ($q2) {
                                        $q2->select('id', 'submodel_id')
                                            ->with([
                                                'submodel' => function ($q3) {
                                                    $q3->select('id', 'order_model_id')
                                                        ->with([
                                                            'orderModel' => function ($q4) {
                                                                $q4->select('id', 'model_id')
                                                                    ->with('model:id,name'); // faqat kerakli maydon
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
                'employeeSalaries' => function ($query) use ($startDate, $endDate) {
                    $query->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate])
                            ->orWhere(function ($q2) use ($startDate, $endDate) {
                                $q2->where('created_at', '<=', $startDate)
                                    ->where('created_at', '>=', $endDate);
                            });
                    });
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
            ]);
        } else {
            $employee->load([
                'attendanceSalaries',
                'employeeTarificationLogs' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate])
                        ->select('id', 'employee_id', 'date', 'tarification_id', 'quantity', 'is_own', 'amount_earned')
                        ->with(['tarification' => function ($q) {
                            $q->select('id', 'name', 'code', 'second', 'summa', 'tarification_category_id')
                                ->with([
                                    'tarificationCategory' => function ($q2) {
                                        $q2->select('id', 'submodel_id')
                                            ->with([
                                                'submodel' => function ($q3) {
                                                    $q3->select('id', 'order_model_id')
                                                        ->with([
                                                            'orderModel' => function ($q4) {
                                                                $q4->select('id', 'model_id')
                                                                    ->with('model:id,name'); // faqat kerakli maydon
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
                $file->storeAs('/public/images/', $filename);
                $employee->img = 'images/' . $filename;
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
        ]);

        $filename = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('/public/issues/', $filename);
        }

        $issue = Issue::create([
            'user_id' => auth()->id(),
            'description' => $request->description,
            'image' => 'issues/' . ($filename ?? null),
        ]);

        Log::add(
            auth()->id(),
            "Yangi muammo qo'shildi",
            'create',
            [],
            [
                'description' => $request->description,
                'image' => $filename ? 'issues/' . $filename : null,
            ]
        );

        // Telegramga yuborish
        $user = auth()->user();
        $message = "#muammo<b>🛠 Yangi muammo bildirildi!</b>\n\n"
            . "👤 Foydalanuvchi: {$user->employee->name} ({$user->role?->name})\n"
            . "📝 Tavsif: {$request->description}";

        $botToken = "8120915071:AAGVvrYz8WBfhABMJWtlDzdFgUELUUKTj5Q";
        $chatId = "-1002523704322";

        if ($filename) {
            $photoPath = storage_path("app/public/issues/" . $filename);

            $response = Http::attach(
                'photo', file_get_contents($photoPath), $filename
            )->post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                'chat_id' => $chatId,
                'caption' => $message,
                'parse_mode' => 'HTML',
            ]);

        } else {
            // Agar rasm bo‘lmasa — oddiy xabar yuborish
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

        if ($employee->payment_type === 'piece_work') {
            $relations['attendances'] = function ($query) use ($start_date, $end_date) {
                if ($start_date && $end_date) {
                    $query->whereBetween('date', [$start_date, $end_date]);
                }
            };

            $relations['employeeTarificationLogs'] = function ($query) use ($start_date, $end_date) {
                if ($start_date && $end_date) {
                    $query->whereBetween('date', [$start_date, $end_date]);
                }

                $query->select('id', 'employee_id', 'date', 'tarification_id', 'quantity', 'amount_earned', 'is_own')
                    ->with(['tarification' => function ($q) {
                        $q->select('id', 'name', 'code', 'second', 'summa');
                    }]);
            };

        } else {
            $relations['attendanceSalaries'] = function ($query) use ($start_date, $end_date) {
                if ($start_date && $end_date) {
                    $query->whereBetween('date', [$start_date, $end_date]);
                }
            };

            $relations['employeeTarificationLogs'] = function ($query) use ($start_date, $end_date) {
                if ($start_date && $end_date) {
                    $query->whereBetween('date', [$start_date, $end_date]);
                }

                $query->select('id', 'employee_id', 'date', 'tarification_id', 'quantity')
                    ->with(['tarification' => function ($q) {
                        $q->select('id', 'name', 'code', 'second', 'summa');
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
            'employee' => $employee,
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
<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Bonus;
use App\Models\BoxTarification;
use App\Http\Resources\GetOrderCutResource;
use App\Http\Resources\GetSpecificationResource;
use App\Http\Resources\showOrderCuttingMasterResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderCut;
use App\Models\OrderModel;
use App\Models\OrderPrintingTimes;
use App\Models\OrderSize;
use App\Models\OrderSubModel;
use App\Models\Outcome;
use App\Models\OutcomeItemModelDistrubition;
use App\Models\Stok;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CuttingMasterController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $status = $request->input('status');
        $orders = Order::where('status', $status)
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->whereDate('start_date', '<=', now()->addDays(15)->toDateString())
            ->orderBy('start_date', 'asc')
            ->with(
                'instructions',
                'orderModel.model',
                'orderModel.material',
                'orderModel.submodels',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
                'orderModel.sizes.color',
                'orderPrintingTime',
                'orderPrintingTime.user'
            )
            ->get();

        return response()->json($orders);
    }

    public function sendToConstructor(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'planned_time' => 'required|date',
            'comment' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $order = Order::find($data['order_id']);
            $oldStatus = $order->status;

            $order->update([
                'status' => 'printing'
            ]);

            $orderPrintingTime = OrderPrintingTimes::create([
                'order_id' => $data['order_id'],
                'planned_time' => $data['planned_time'],
                'status' => 'printing',
                'comment' => $data['comment'],
                'user_id' => auth()->user()->id
            ]);

            // Add log entry
            Log::add(
                auth()->user()->id,
                "Buyurtma konstruktorga yuborildi (Order ID: {$data['order_id']})",
                'send',
                ['old_data' => $oldStatus, 'order_id' => $data['order_id']],
                ['new_data' => 'printing', 'planned_time' => $data['planned_time'], 'comment' => $data['comment']]
            );

            DB::commit();
            return response()->json($orderPrintingTime);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getCompletedItems(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderId = $request->input('order_id');

        $orderModelIds = OrderModel::where('order_id', $orderId)->pluck('id')->toArray();

        $outcomeItemModelDistribution = OutcomeItemModelDistrubition::whereIn('model_id', $orderModelIds)
            ->whereHas('outcomeItem.outcome', function ($query) {
                $query->where('outcome_type', 'production')
                    ->whereHas('productionOutcome', function ($query) {
                        $query->where('received_by_id', auth()->id());
                    });
            })
            ->with([
                'outcomeItem.outcome.items.product:id,name',
                'orderModel:id,model_id,order_id',
                'orderModel.model:id,name',
                'orderModel.order:id,start_date',
                'orderModel.order'
            ])
            ->get();



        return response()->json($outcomeItemModelDistribution);
    }

    public function showOrder(Order $order): \Illuminate\Http\JsonResponse
    {
        $order->load([
            'orderModel.model',
            'orderModel.material',
            'orderModel.submodels',
            'orderModel.sizes.size',
            'orderModel.sizes.color',
            'orderModel.submodels.submodel',
            'orderModel.submodels.specificationCategories',
        ]);

        return response()->json($order);
    }

    public function getSpecificationByOrderId($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::find($id);

        $order->load([
            'orderModel.submodels.specificationCategories',
            'orderModel.submodels.specificationCategories.specifications'
        ]);

        $resource = new GetSpecificationResource($order);

        return response()->json($resource);
    }

    public function getCuts($id): \Illuminate\Http\JsonResponse
    {
        $cuts = OrderCut::where('order_id', $id)
            ->with([
                'user.employee',
                'submodel.submodel',
                'size.size'
            ])
            ->get();

        return response()->json($cuts);
    }

    public function finishCutting($id): \Illuminate\Http\JsonResponse
    {
        DB::beginTransaction();
        try {
            $order = Order::with(['orderModel.model', 'orderPrintingTime'])->find($id);

            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }

            $oldStatus = $order->status;
            $orderQuantity = $order->quantity;

            $totalCutQuantity = DB::table('order_cuts')
                ->where('order_id', $id)
                ->sum('quantity');

            $remaining = max(0, $orderQuantity - $totalCutQuantity);

            if ($remaining > 0) {
                DB::table('order_cuts')->insert([
                    'order_id' => $order->id,
                    'user_id' => auth()->id(),
                    'cut_at' => now(),
                    'quantity' => $remaining,
                    'status' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            $order->update(['status' => 'pending']);

            if ($order->orderPrintingTime) {
                $order->orderPrintingTime->update(['status' => 'completed']);
            }

            Log::add(
                auth()->id(),
                "Buyurtma kesish yakunlandi (Order ID: $id)",
                'cut',
                ['old' => $oldStatus],
                ['new' => 'pending']
            );


            /** 📌 Daily Payment Calculation */
            $departmentId = auth()->user()->employee->department_id ?? null;

            if (!$departmentId) {
                return response()->json([
                    'message' => 'Kesish yakunlandi (Ishchilar bo‘limi topilmadi)',
                    'remaining_cut_added' => $remaining
                ]);
            }

            $departmentBudget = DB::table('department_budgets')
                ->where('department_id', $departmentId)
                ->first();

            if (!$departmentBudget || $departmentBudget->type !== 'minute_based') {
                return response()->json([
                    'message' => 'Kesish yakunlandi',
                    'remaining_cut_added' => $remaining
                ]);
            }

            $modelMinute = $order->orderModel->model->minute ?? 0;
            if ($modelMinute <= 0) {
                return response()->json(['message' => 'Kesish yakunlandi (modelMinute yo‘q)']);
            }

            $totalMinutes = $modelMinute * $remaining;
            $totalEarned = $departmentBudget->quantity * $totalMinutes;

            $employees = Employee::where('department_id', $departmentId)
                ->whereHas('attendances', function ($q) {
                    $q->whereDate('date', Carbon::today())->where('status', 'present');
                })
                ->where('status', 'working')
                ->get();

            foreach ($employees as $emp) {
                $percentage = $emp->percentage ?? 0;

                $earned = round(($totalEarned * $percentage) / 100, 2);

                if ($earned == 0 || $percentage == 0 || $remaining == 0) {
                    continue;
                }

                //agar shu model shu order shu employee uchun oldin daily payment bo'lsa, edit qilib umumiy kesilganiga summa yozib qo'yamiz

                $existingPayment = DB::table('daily_payments')
                    ->where('employee_id', $emp->id)
                    ->where('order_id', $order->id)
                    ->where('model_id', $order->orderModel->model->id)
                    ->whereDate('payment_date', Carbon::today())
                    ->first();

                if ($existingPayment) {
                    DB::table('daily_payments')
                        ->where('id', $existingPayment->id)
                        ->update([
                            'quantity_produced' => $existingPayment->quantity_produced + $orderQuantity,
                            'calculated_amount' => $existingPayment->calculated_amount + $earned,
                            'updated_at' => now(),
                        ]);
                    continue;
                }

                DB::table('daily_payments')->insert([
                    'employee_id' => $emp->id,
                    'model_id' => $order->orderModel->model->id ?? null,
                    'order_id' => $order->id,
                    'department_id' => $departmentId,
                    'payment_date' => Carbon::today(),
                    'quantity_produced' => $orderQuantity,
                    'calculated_amount' => $earned,
                    'employee_percentage' => $percentage,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            /** ✅ Ombor Department uchun ham hisob-kitob */

            $authBranchId = auth()->user()->employee->branch_id ?? null;

//            if ($authBranchId) {
//
//                $warehouseDepartment = Department::where(function($q) {
//                        $q->where('name', 'ILIKE', '%омбор%')
//                            ->orWhere('name', 'ILIKE', '%ombor%');
//                    })
//                    ->whereHas('mainDepartment', function ($q) use ($authBranchId) {
//                        $q->where('branch_id', $authBranchId);
//                    })
//                    ->first();
//
//
//                if ($warehouseDepartment) {
//                    $warehouseBudget = DB::table('department_budgets')
//                        ->where('department_id', $warehouseDepartment->id)
//                        ->where('type', 'minute_based')
//                        ->first();
//
//                    if ($warehouseBudget) {
//                        $modelMinute = $order->orderModel->model->minute ?? 0;
//
//                        if ($modelMinute > 0 && $remaining > 0) {
//
//                            $totalMinutes = $modelMinute * $remaining;
//                            $totalEarnedWarehouse = $warehouseBudget->quantity * $totalMinutes;
//
//                            $warehouseEmployees = Employee::where('department_id', $warehouseDepartment->id)
//                                ->whereHas('attendances', function ($q) {
//                                    $q->whereDate('date', Carbon::today())
//                                        ->where('status', 'present');
//                                })
//                                ->where('status', 'working')
//                                ->get();
//
//                            foreach ($warehouseEmployees as $wEmp) {
//
//                                $percentage = $wEmp->percentage ?? 0;
//                                if ($percentage == 0) continue;
//
//                                $earned = round(($totalEarnedWarehouse * $percentage) / 100, 2);
//                                if ($earned == 0) continue;
//
//                                $existing = DB::table('daily_payments')
//                                    ->where('employee_id', $wEmp->id)
//                                    ->where('order_id', $order->id)
//                                    ->where('model_id', $order->orderModel->model->id)
//                                    ->whereDate('payment_date', Carbon::today())
//                                    ->first();
//
//                                if ($existing) {
//                                    DB::table('daily_payments')
//                                        ->where('id', $existing->id)
//                                        ->update([
//                                            'quantity_produced' => $existing->quantity_produced + $remaining,
//                                            'calculated_amount' => $existing->calculated_amount + $earned,
//                                            'updated_at' => now(),
//                                        ]);
//                                } else {
//                                    DB::table('daily_payments')->insert([
//                                        'employee_id' => $wEmp->id,
//                                        'model_id' => $order->orderModel->model->id,
//                                        'order_id' => $order->id,
//                                        'department_id' => $warehouseDepartment->id,
//                                        'payment_date' => Carbon::today(),
//                                        'quantity_produced' => $remaining,
//                                        'calculated_amount' => $earned,
//                                        'employee_percentage' => $percentage,
//                                        'created_at' => now(),
//                                        'updated_at' => now(),
//                                    ]);
//                                }
//                            }
//                        }
//                    }
//                }
//            }

            DB::commit();

            return response()->json([
                'message' => 'Kesish yakunlandi ✅',
                'remaining_cut_added' => $remaining
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function markAsCut(Request $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'quantity' => 'required|integer|min:1',
//            'submodel_id' => 'required|integer|exists:order_sub_models,id',
//            'size_id' => 'required|integer|exists:order_sizes,id',
//            'box_capacity' => 'required|integer|min:1',
//            'region' => 'required|string|max:255',
        ]);

        ini_set('memory_limit', '2G');
        set_time_limit(0);

        DB::beginTransaction();
        try {
            $order = Order::with(['orderModel.model'])->findOrFail($data['order_id']);

            $existingCutForSubmodel = OrderCut::where('order_id', $data['order_id'])
//                ->where('submodel_id', $data['submodel_id'])
                ->sum('quantity');

            $remainingForSubmodel = $order->quantity - $existingCutForSubmodel;

            if ($data['quantity'] > $remainingForSubmodel) {
                return response()->json([
                    'message' => 'Submodel uchun kesish miqdori ortiqcha. Qolgan: ' . $remainingForSubmodel
                ], 422);
            }

            OrderCut::create([
                'order_id' => $data['order_id'],
                'user_id' => auth()->user()->id,
                'cut_at' => now()->format('Y-m-d H:i:s'),
                'quantity' => $data['quantity'],
                'status' => false,
                'submodel_id' => $data['submodel_id'] ?? null,
                'size_id' => $data['size_id'] ?? null,
            ]);

            /** ✅ BONUS hisoblash (avvalgi qismi) */
            $minutesPerUnit = $order->orderModel->rasxod / 250;

            $employees = Employee::where('payment_type', 'fixed_cutted_bonus')
                ->where('status', '!=', 'kicked')
                ->where('branch_id', $order->branch_id)
                ->get();

            foreach ($employees as $employee) {
                $bonusAmount = $employee->bonus * $minutesPerUnit * $data['quantity'];
                $oldBalance = $employee->balance;
                $employee->balance += $bonusAmount;
                $employee->save();

                Log::add(
                    auth()->id(),
                    'Qadoqlovchiga bonus qo‘shildi',
                    'packaging_bonus',
                    $oldBalance,
                    $employee->balance,
                    request()->ip(),
                    request()->userAgent()
                );

                Bonus::create([
                    'employee_id' => $employee->id,
                    'amount' => $bonusAmount,
                    'type' => 'packaging',
                    'description' => 'Qadoqlash bonusi',
                    'date' => now(),
                    'order_id' => $order->id,
                ]);
            }

            /** 📌 DAILY PAYMENT hisoblash (finishCutting() dan keltirildi) */
            $departmentId = auth()->user()->employee->department_id ?? null;

            if ($departmentId) {
                $departmentBudget = DB::table('department_budgets')
                    ->where('department_id', $departmentId)
                    ->first();

                if ($departmentBudget && $departmentBudget->type === 'minute_based') {
                    $modelMinute = $order->orderModel->model->minute ?? 0;

                    if ($modelMinute > 0) {
                        $totalMinutes = $modelMinute * $data['quantity'];
                        $totalEarned = $departmentBudget->quantity * $totalMinutes;

                        $employees = Employee::where('department_id', $departmentId)
                            ->whereHas('attendances', function ($q) {
                                $q->whereDate('date', Carbon::today())->where('status', 'present');
                            })
                            ->where('status', 'working')
                            ->get();

                        foreach ($employees as $emp) {
                            $percentage = $emp->percentage ?? 0;
                            if ($percentage == 0) continue;

                            $earned = round(($totalEarned * $percentage) / 100, 2);
                            if ($earned == 0) continue;

                            $existingPayment = DB::table('daily_payments')
                                ->where('employee_id', $emp->id)
                                ->where('order_id', $order->id)
                                ->where('model_id', $order->orderModel->model->id)
                                ->whereDate('payment_date', Carbon::today())
                                ->first();

                            if ($existingPayment) {
                                DB::table('daily_payments')
                                    ->where('id', $existingPayment->id)
                                    ->update([
                                        'quantity_produced' => $existingPayment->quantity_produced + $data['quantity'],
                                        'calculated_amount' => $existingPayment->calculated_amount + $earned,
                                        'updated_at' => now(),
                                    ]);
                            } else {
                                DB::table('daily_payments')->insert([
                                    'employee_id' => $emp->id,
                                    'model_id' => $order->orderModel->model->id,
                                    'order_id' => $order->id,
                                    'department_id' => $departmentId,
                                    'payment_date' => Carbon::today(),
                                    'quantity_produced' => $data['quantity'],
                                    'calculated_amount' => $earned,
                                    'employee_percentage' => $percentage,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                }
            }

            /** ✅ Agar kesish to‘liq tugagan bo‘lsa */
            $totalCut = OrderCut::where('order_id', $order->id)->sum('quantity');
            if ($totalCut >= $order->quantity) {
                $order->update(['status' => 'pending']);
            }

            DB::commit();
            return response()->json(['message' => 'Kesish bajarildi va to‘lovlar hisoblandi ✅']);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    private function storeBoxTarifications($boxNumber, $quantity, $data, $submodel, $sizeName): array
    {
        $records = [];
        foreach ($submodel->tarificationCategories as $category) {
            foreach ($category->tarifications as $tarification) {
                $total = $tarification->summa * $quantity;

                $record = BoxTarification::create([
                    'order_id' => $data['order_id'],
                    'submodel_id' => $data['submodel_id'],
                    'tarification_id' => $tarification->id,
                    'size_id' => $data['size_id'],
                    'quantity' => $quantity,
                    'price' => $tarification->summa,
                    'total' => $total,
                    'status' => 'active',
                ]);

                $tarification->box_tarification_id = $record->id; // PDF uchun
                $records[] = $tarification;
            }
        }

        return [
            'box_number' => $boxNumber,
            'quantity' => $quantity,
            'submodel' => $submodel,
            'size' => $sizeName,
            'tarifications' => $records
        ];
    }

    public function updateBoxTarification(BoxTarification $boxTarification): \Illuminate\Http\Response
    {
        $data = $boxTarification->toArray();

        $box =  BoxTarification::create([
            'order_id' => $data['order_id'],
            'submodel_id' => $data['submodel_id'],
            'tarification_id' => $data['tarification_id'],
            'size_id' => $data['size_id'],
            'quantity' => $data['quantity'],
            'price' => $data['price'],
            'total' => $data['total'],
            'status' => 'active',
        ]);

        $boxTarification->update([
            'status' => 'inactive'
        ]);

        $pdf = Pdf::loadView('pdf.tarification-one', ['box' => $box])->setPaper('A4', 'portrait');

        return $pdf->download('kesish_tarifikatsiyasi_' . now()->format('Ymd_His') . '.pdf');
    }

    public function show(Request $request): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id ?? null;

        $department = Department::with('mainDepartment')->find(auth()->user()->employee->department_id);

        if ($department->mainDepartment->branch_id !== $branchId) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $department->load([
            'departmentBudget',
            'employees' => function ($q) {
                $q->where('status', 'working')
                    ->select('id', 'name', 'phone', 'department_id', 'percentage', 'position_id', 'img', 'branch_id', 'salary', 'payment_type')
                    ->with('position:id,name');
            }
        ]);
        $month = $request->month ? \Carbon\Carbon::parse($request->month)->format('m') : now()->format('m');
        $year = $request->month ? Carbon::parse($request->month)->format('Y') : now()->format('Y');

        $usdRate = getUsdRate();
        $seasonYear = 2026;
        $seasonType = 'summer';

        $departmentTotal = [
            'total_earned' => 0,
            'total_remaining' => 0,
            'total_possible' => 0,
            'total_possible_season' => 0,
        ];

        $startOfMonth = Carbon::create($year, $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $totalWorkingDays = 0;

        for ($dateIter = $startOfMonth->copy(); $dateIter <= $endOfMonth; $dateIter->addDay()) {
            if (!$dateIter->isSunday()) {
                $totalWorkingDays++;
            }
        }

        $employeesData = $department->employees->map(function ($employee) use ($totalWorkingDays, $year, $month, $request, $usdRate, $seasonYear, $seasonType, $departmentTotal) {
            $branchId = $employee->branch_id;
            $empPercent = floatval($employee->percentage ?? 0);

            // --- Monthly orders for this employee

            $presentDays = Attendance::where('employee_id', $employee->id)
                ->where('status', 'present')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->count();

            $data = DB::table('orders')
                ->select(
                    'orders.id as order_id',
                    'orders.name as order_name',
                    'orders.quantity as planned_quantity',
                    'order_models.model_id',
                    'models.name as model_name',
                    'models.minute as model_minute',
                    DB::raw("COALESCE(SUM(daily_payments.calculated_amount),0) as earned_amount"),
                    DB::raw("COALESCE(SUM(daily_payments.quantity_produced),0) as produced_quantity"),
                    DB::raw("MAX(daily_payments.department_id) as department_id"),
                    'orders.price'
                )
                ->join('order_models', 'order_models.order_id', '=', 'orders.id')
                ->join('models', 'models.id', '=', 'order_models.model_id')
                ->leftJoin('daily_payments', function ($q) use ($employee, $month, $year) {
                    $q->on('daily_payments.order_id', '=', 'orders.id')
                        ->where('daily_payments.employee_id', '=', $employee->id)
                        ->whereMonth('daily_payments.payment_date', $month)
                        ->whereYear('daily_payments.payment_date', $year);
                })
                ->leftJoin('monthly_selected_orders', function ($q) use ($month, $year) {
                    $q->on('monthly_selected_orders.order_id', '=', 'orders.id')
                        ->whereMonth('monthly_selected_orders.month', $month)
                        ->whereYear('monthly_selected_orders.month', $year);
                })
                ->where('orders.branch_id', $branchId)
                ->whereExists(function ($query) use ($month, $year) {
                    $query->select(DB::raw(1))
                        ->from('monthly_selected_orders')
                        ->whereColumn('monthly_selected_orders.order_id', 'orders.id')
                        ->whereMonth('monthly_selected_orders.month', $month)
                        ->whereYear('monthly_selected_orders.month', $year);
                })
                ->groupBy(
                    'orders.id',
                    'order_models.model_id',
                    'models.name',
                    'models.minute',
                    'orders.name',
                    'orders.quantity',
                    'orders.price'
                )
                ->get();

            $orders = $data->map(function ($row) use ($employee, $usdRate, $empPercent, $month, $year) {
                $departmentBudget = DB::table('department_budgets')->where('department_id', $employee->department_id)->first();

                $perPieceEarn = 0;
                if ($departmentBudget && $departmentBudget->quantity > 0) {
                    if ($departmentBudget->type === 'minute_based') {
                        $perPieceEarn = $row->model_minute * $departmentBudget->quantity / 100 * $empPercent;
                    } elseif ($departmentBudget->type === 'percentage_based') {
                        $priceUzs = ($row->price ?? 0) * $usdRate;
                        $perPieceEarn = (($priceUzs * $departmentBudget->quantity) / 100) * ($empPercent / 100);
                    }
                }

                $remainingQuantity = max($row->planned_quantity - $row->produced_quantity, 0);

                return [
                    "order" => [
                        "id" => $row->order_id,
                        "name" => $row->order_name,
                        'minute' => $row->model_minute,
                    ],
                    "planned_quantity" => $row->planned_quantity,
                    "produced_quantity" => $row->produced_quantity,
                    "remaining_quantity" => $remainingQuantity,
                    "earned_amount" => round($row->earned_amount, 2),
                    "remaining_earn_amount" => round($remainingQuantity * $perPieceEarn, 2),
                    "possible_full_earn_amount" => round($row->planned_quantity * $perPieceEarn, 2),
                ];
            });

            $monthlyTotal = [
                'total_planned_quantity' => $orders->sum('planned_quantity'),
                'total_earned' => round($orders->sum('earned_amount'), 2),
                'total_remaining' => round($orders->sum('remaining_earn_amount'), 2),
                'total_possible' => round($orders->sum('possible_full_earn_amount'), 2),
            ];

            // --- Season orders for this employee
            $seasonOrders = DB::table('orders')
                ->select('orders.id', 'orders.quantity', 'order_models.model_id', 'models.minute', 'orders.price')
                ->join('order_models', 'order_models.order_id', '=', 'orders.id')
                ->join('models', 'models.id', '=', 'order_models.model_id')
                ->where('orders.branch_id', $employee->branch_id)
                ->where('orders.season_year', $seasonYear)
                ->where('orders.season_type', $seasonType)
                ->get();

            $totalPossibleSeason = 0;
            foreach ($seasonOrders as $row) {
                $departmentBudget = DB::table('department_budgets')->where('department_id', $employee->department_id)->first();
                if (!$departmentBudget || $departmentBudget->quantity <= 0) continue;

                $perPieceEarn = 0;
                if ($departmentBudget->type === 'minute_based') {
                    $perPieceEarn = $row->minute * $departmentBudget->quantity / 100 * $empPercent;
                } elseif ($departmentBudget->type === 'percentage_based') {
                    $priceUzs = ($row->price ?? 0) * $usdRate;
                    $perPieceEarn = (($priceUzs * $departmentBudget->quantity) / 100) * ($empPercent / 100);
                }
                $totalPossibleSeason += $row->quantity * $perPieceEarn;
            }

            $monthlyTotal['total_possible_season'] = round($totalPossibleSeason, 2);

            $salary = match ($employee->payment_type) {
                'monthly' => $employee->salary,
                'daily' => $employee->salary * 26,
                'hourly' => $employee->salary * 260,
                default => $employee->salary,
            };

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'percentage' => $employee->percentage,
                'position' => $employee->position,
                'img' => $employee->img,
                'payment_type' => $employee->payment_type,
                'salary' => $salary,
                'attendance' => [
                    'present_days' => $presentDays,
                    'total_working_days' => $totalWorkingDays,
                ],
                'orders' => $orders,
                'totals' => $monthlyTotal,
            ];
        });

        // --- Department totals
        $departmentTotals = [
            'total_earned' => round($employeesData->sum(fn($e) => $e['totals']['total_earned']), 2),
            'total_remaining' => round($employeesData->sum(fn($e) => $e['totals']['total_remaining']), 2),
            'total_possible' => round($employeesData->sum(fn($e) => $e['totals']['total_possible']), 2),
            'total_possible_season' => round($employeesData->sum(fn($e) => $e['totals']['total_possible_season']), 2),
        ];

        return response()->json([
            'id' => $department->id,
            'name' => $department->name,
            'budget' => $department->departmentBudget ? [
                'id' => $department->departmentBudget->id,
                'quantity' => $department->departmentBudget->quantity,
                'type' => $department->departmentBudget->type,
            ] : null,
            'employee_count' => $department->employees->count(),
            'employees' => $employeesData,
            'department_totals' => $departmentTotals,
        ]);
    }

}
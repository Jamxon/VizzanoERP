<?php

namespace App\Http\Controllers;

use App\Exports\CashboxTransactionsExport;
use App\Exports\DepartmentGroupsExport;
use App\Exports\GroupsOrdersEarningsExport;
use App\Exports\MonthlyCostPdf;
use App\Http\Resources\GroupPlanResource;
use App\Models\Attendance;
use App\Models\Cashbox;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use App\Models\CashboxBalance;
use App\Models\CashboxTransaction;
use App\Models\Currency;
use App\Models\Log;
use App\Models\MonthlyExpense;
use App\Models\MonthlySelectedOrder;
use App\Models\Order;
use App\Models\OrderCut;
use App\Models\SalaryPayment;
use App\Models\SewingOutputs;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MonthlyCostExport;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Exports\TransactionsExport;
use Illuminate\Support\Facades\Http;

class CasherController extends Controller
{
    public function exportMonthlyCostPdf(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'dollar_rate' => 'nullable|numeric',
        ]);

        $start = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::now()->startOfMonth();

        $end = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfMonth();

        $dollarRate = $request->dollar_rate ?? 12700;

        // Sana oralig'i juda katta bo'lsa, ogohlantiramiz
        if ($start->diffInDays($end) > 366) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sana oralig\'i 1 yildan ko\'p bo\'lmasligi kerak'
            ], 400);
        }

        $period = CarbonPeriod::create($start, $end);

        // 1) Kunlik tafsilotlarni to'plash
        $dailyRows = [];
        $progressData = [
            'total_days' => $period->count(),
            'processed_days' => 0
        ];

        foreach ($period as $dateObj) {
            $date = $dateObj->toDateString();

            try {
                // getDailyCost methodini chaqirish
                $dailyResp = $this->getDailyCost(new Request([
                    'date' => $date,
                    'dollar_rate' => $dollarRate,
                ]));

                // Response ni array formatiga o'tkazish
                if (is_object($dailyResp) && method_exists($dailyResp, 'getData')) {
                    $daily = $dailyResp->getData(true);
                } elseif (is_array($dailyResp)) {
                    $daily = $dailyResp;
                } else {
                    $daily = [];
                }

                $dailyRows[] = [
                    'date' => $date,
                    'aup' => $daily['aup'] ?? 0,
                    'kpi' => $daily['kpi'] ?? 0,
                    'transport_attendance' => $daily['transport_attendance'] ?? 0,
                    'tarification' => $daily['tarification'] ?? 0,
                    'daily_expenses' => $daily['daily_expenses'] ?? 0,
                    'total_earned_uzs' => $daily['total_earned_uzs'] ?? 0,
                    'total_fixed_cost_uzs' => $daily['total_fixed_cost_uzs'] ?? 0,
                    'net_profit_uzs' => $daily['net_profit_uzs'] ?? 0,
                    'employee_count' => $daily['employee_count'] ?? 0,
                    'total_output_quantity' => $daily['total_output_quantity'] ?? 0,
                    'rasxod_limit_uzs' => $daily['rasxod_limit_uzs'] ?? 0,
                ];

                $progressData['processed_days']++;

            } catch (\Exception $e) {
                // Agar biron bir kun uchun ma'lumot olmasa, bo'sh qiymatlar qo'yamiz
                \Log::warning("Kunlik ma'lumot olishda xatolik [{$date}]: " . $e->getMessage());

                $dailyRows[] = [
                    'date' => $date,
                    'aup' => 0, 'kpi' => 0, 'transport_attendance' => 0,
                    'tarification' => 0, 'daily_expenses' => 0, 'total_earned_uzs' => 0,
                    'total_fixed_cost_uzs' => 0, 'net_profit_uzs' => 0,
                    'employee_count' => 0, 'total_output_quantity' => 0, 'rasxod_limit_uzs' => 0,
                ];
            }
        }

        // 2) Umumiy oylik hisob-kitoblarni olish
        try {
            $monthlyResp = $this->getMonthlyCost(new Request([
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'dollar_rate' => $dollarRate,
            ]));

            $monthly = $monthlyResp->getData(true);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Oylik hisobotni olishda xatolik: ' . $e->getMessage()
            ], 500);
        }

        // 3) Payload tayyorlash
        $payload = [
            'summary' => array_merge($monthly, [
                'start_date' => $start->format('d.m.Y'),
                'end_date' => $end->format('d.m.Y'),
                'dollar_rate' => $dollarRate,
                'days_in_period' => $start->diffInDays($end) + 1,
                'generated_at' => Carbon::now()->format('d.m.Y H:i:s'),
            ]),
            'daily' => $dailyRows,
            'orders' => $monthly['orders'] ?? [],
            'progress' => $progressData,
        ];

        // 4) PDF yaratish
        $pdf = new MonthlyCostPdf($payload);

        // Fayl nomini yaratish
        $fileName = sprintf(
            "Oylik_Hisobot_%s_%s.pdf",
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        // Memory limit ni oshirish katta hisobotlar uchun
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 300); // 5 minut

        // PDF yaratish va yuklash
        return $pdf->generate()->download($fileName);


    }

    public function exportMonthlyCostExcel(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'dollar_rate' => 'nullable|numeric',
        ]);

        try {
            $start = $request->start_date
                ? Carbon::parse($request->start_date)->startOfDay()
                : Carbon::now()->startOfMonth();

            $end = $request->end_date
                ? Carbon::parse($request->end_date)->endOfDay()
                : Carbon::now()->endOfMonth();

            $dollarRate = $request->dollar_rate ?? 12700;

            $period = CarbonPeriod::create($start, $end);

            // 1) Kunlik tafsilotlarni to'plash
            $dailyRows = [];
            foreach ($period as $dateObj) {
                $date = $dateObj->toDateString();

                // chaqiramiz getDailyCost — u JSON qaytaradi, shuning uchun getData(true)
                $dailyResp = $this->getDailyCost(new Request([
                    'date' => $date,
                    'dollar_rate' => $dollarRate,
                ]));

                $daily = is_object($dailyResp) && method_exists($dailyResp, 'getData')
                    ? $dailyResp->getData(true)
                    : (is_array($dailyResp) ? $dailyResp : []);

                $dailyRows[] = [
                    'date' => $date,
                    'aup' => $daily['aup'] ?? 0,
                    'kpi' => $daily['kpi'] ?? 0,
                    'transport_attendance' => $daily['transport_attendance'] ?? 0,
                    'tarification' => $daily['tarification'] ?? 0,
                    'daily_expenses' => $daily['daily_expenses'] ?? 0,
                    'total_earned_uzs' => $daily['total_earned_uzs'] ?? 0,
                    'total_fixed_cost_uzs' => $daily['total_fixed_cost_uzs'] ?? 0,
                    'net_profit_uzs' => $daily['net_profit_uzs'] ?? 0,
                    'employee_count' => $daily['employee_count'] ?? 0,
                    'total_output_quantity' => $daily['total_output_quantity'] ?? 0,
                    'rasxod_limit_uzs' => $daily['rasxod_limit_uzs'] ?? 0,
                ];
            }

            // 2) Umumiy oylik hisob-kitoblarni olish (sizdagi getMonthlyCost methodidan)
            $monthlyResp = $this->getMonthlyCost(new Request([
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'dollar_rate' => $dollarRate,
            ]));

            $monthly = $monthlyResp->getData(true);

            // 3) payload tayyorlash
            $payload = [
                'summary' => $monthly,
                'daily' => $dailyRows,
                'orders' => $monthly['orders'] ?? [],
            ];

            $fileName = sprintf("MonthlyReport_%s_%s.xlsx", $start->toDateString(), $end->toDateString());

            return Excel::download(new MonthlyCostExport($payload), $fileName);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getMonthlyCost(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'dollar_rate' => 'nullable|numeric',
        ]);

        try {
            $start = $request->start_date
                ? Carbon::parse($request->start_date)->startOfDay()
                : Carbon::now()->startOfMonth();

            $end = $request->end_date
                ? Carbon::parse($request->end_date)->endOfDay()
                : Carbon::now()->endOfMonth();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid date format. Please use YYYY-MM-DD'], 400);
        }

        $dollarRate = $request->dollar_rate ?? 12900;
        $branchId = auth()->user()->employee->branch_id;

        // Period related helpers
        $period = CarbonPeriod::create($start, $end);
        $daysInPeriod = collect($period)->count();

        // ====== 1) AGGREGATE: transport total and transport employees (sum of daily counts)
        $transportTotal = DB::table('transport_attendance')
            ->join('transport', 'transport_attendance.transport_id', '=', 'transport.id')
            ->whereBetween('transport_attendance.date', [$start, $end])
            ->where('transport.branch_id', $branchId)
            ->sum(DB::raw('(transport.salary + transport.fuel_bonus) * transport_attendance.attendance_type'));

        // Sum of daily transport employee counts (equivalent to summing transportEmployeesCount for each day)
        $transportEmployeesCountSum = DB::table('employee_transport_daily as etd')
            ->join('employees as e', 'etd.employee_id', '=', 'e.id')
            ->join('transport as t', 'etd.transport_id', '=', 't.id')
            ->whereBetween('etd.date', [$start, $end])
            ->where('e.branch_id', $branchId)
            ->where('t.branch_id', $branchId)
            ->count('etd.employee_id');

        $transportPerEmployeeSum = $transportEmployeesCountSum > 0 ? $transportTotal / $transportEmployeesCountSum : 0;

        // ====== 2) MONTHLY_EXPENSES: get all monthly_expenses in range, grouped by month (Y-m)
        $monthlyExpenses = DB::table('monthly_expenses')
            ->where('branch_id', $branchId)
            ->whereBetween('month', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(function ($r) {
                $r->month_key = Carbon::parse($r->month)->format('Y-m');
                return $r;
            })
            ->groupBy('month_key'); // collection keyed by 'YYYY-MM'

        // ====== 3) AUP and non-AUP sums across period
        $aupTotal = DB::table('attendance_salary')
            ->join('attendance', 'attendance_salary.attendance_id', '=', 'attendance.id')
            ->join('employees', 'attendance_salary.employee_id', '=', 'employees.id')
            ->where('employees.branch_id', $branchId)
            ->where('employees.type', 'aup')
            ->whereBetween('attendance.date', [$start, $end])
            ->sum('attendance_salary.amount');

        $isNotAupTotal = DB::table('attendance_salary')
            ->join('attendance', 'attendance_salary.attendance_id', '=', 'attendance.id')
            ->join('employees', 'attendance_salary.employee_id', '=', 'employees.id')
            ->where('employees.branch_id', $branchId)
            ->where('employees.type', '!=', 'aup')
            ->whereBetween('attendance.date', [$start, $end])
            ->sum('attendance_salary.amount');

        // ====== 4) Employee present counts - note: original monthlyStats summed daily employee counts,
        // so we count attendance.present rows in period (this equals sum of daily present counts)
        $employeeCountSum = Attendance::whereBetween('date', [$start, $end])
            ->where('status', 'present')
            ->whereHas('employee', function ($q) use ($branchId) {
                $q->where('status', '!=', 'kicked')->where('branch_id', $branchId);
            })
            ->count();

        // ====== 5) Outputs in period, group by order_id (same grouping as daily)
        $outputs = SewingOutputs::with([
            'orderSubmodel.orderModel.order',
            'orderSubmodel.orderModel.model',
            'orderSubmodel.orderModel.submodels.submodel'
        ])
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('orderSubmodel.orderModel.order', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->get();

        $grouped = $outputs->groupBy(fn($item) => optional($item->orderSubmodel->orderModel)->order_id);
        $totalOutputQty = $outputs->sum('quantity');

        // Preload some caches to avoid repeated DB calls inside loop
        $orderModelIds = $outputs->map(fn($o) => optional($o->orderSubmodel->orderModel)->id)->unique()->filter()->values();
        $orderIds = $grouped->keys()->filter()->values();

        // We'll compute per-order aggregates
        $orderSummaries = [];
        foreach ($grouped as $orderId => $items) {
            // $orderId here is the order_model->order_id, but grouped key maybe numeric or null
            // find first to extract orderModel and order
            $first = $items->first();
            $orderModel = optional(optional($first)->orderSubmodel)->orderModel;
            $order = optional($orderModel)->order;
            $orderId = $order->id ?? null;
            if (!$orderId) continue;

            $totalQty = $items->sum('quantity');
            $priceUSD = $order->price ?? 0;
            $priceUZS = $priceUSD * $dollarRate;

            // submodel spends (sum for the order_model id, region 'uz')
            $submodelSpendsSum = DB::table('order_sub_models as osm')
                ->join('submodel_spends as ss', 'ss.submodel_id', '=', 'osm.id')
                ->where('osm.order_model_id', $orderModel->id)
                ->where('ss.region', 'uz')
                ->sum('ss.summa');

            $remainder = $submodelSpendsSum * $totalQty;

            // Bonuses for this order in period (sum over period)
            $bonus = DB::table('bonuses')
                ->whereBetween('created_at', [$start, $end])
                ->where('order_id', $orderId)
                ->sum('amount');

            // Tarification for this order in period (sum over period)
            $tarification = DB::table('employee_tarification_logs')
                ->join('tarifications', 'employee_tarification_logs.tarification_id', '=', 'tarifications.id')
                ->join('tarification_categories', 'tarifications.tarification_category_id', '=', 'tarification_categories.id')
                ->join('order_sub_models', 'tarification_categories.submodel_id', '=', 'order_sub_models.id')
                ->join('order_models', 'order_sub_models.order_model_id', '=', 'order_models.id')
                ->join('orders', 'order_models.order_id', '=', 'orders.id')
                ->whereBetween('employee_tarification_logs.date', [$start, $end])
                ->where('orders.id', $orderId)
                ->sum('employee_tarification_logs.amount_earned');

            // === Now compute monthly-type allocations that in daily were computed per-day.
            // To match daily+foreach behaviour we must replicate sum of per-day values across the period.
            // For this we iterate per-month segment inside the requested range and consider how many days of that month are included.

            // Prepare accumulators for this order
            $allocatedMonthlyExpenseTotal = 0; // sum of allocatedMonthlyExpenseMonthly across period (was daily value summed)
            $incomePercentageExpenseTotal = 0;
            $amortizationExpenseTotal = 0;

            // Build month segments from start to end (YYYY-MM keys)
            $monthPeriod = CarbonPeriod::create($start->copy()->startOfMonth(), $end->copy()->startOfMonth())->month();

            foreach ($monthPeriod as $monthStart) {
                $monthKey = $monthStart->format('Y-m');
                $monthBegin = $monthStart->copy()->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();

                // overlap between requested $start..$end and this month
                $segStart = $start->greaterThan($monthBegin) ? $start->copy() : $monthBegin->copy();
                $segEnd = $end->lessThan($monthEnd) ? $end->copy() : $monthEnd->copy();

                if ($segStart->gt($segEnd)) continue;

                $daysInMonth = $monthStart->daysInMonth;
                // number of days from segStart to segEnd inclusive
                $daysIncluded = $segStart->diffInDays($segEnd) + 1;

                // monthly expenses entries for this month (if any)
                $expensesForMonth = $monthlyExpenses->get($monthKey, collect());

                // 1) monthly-type: daily portion in that month = monthly_amount / daysInMonth
                $monthlyTypeSumForMonth = $expensesForMonth->where('type', 'monthly')->sum('amount');
                if ($monthlyTypeSumForMonth > 0) {
                    $dailyMonthlyForMonth = $monthlyTypeSumForMonth / max($daysInMonth, 1);
                    // allocated per-day for this order = dailyMonthlyForMonth * orderShareRatio
                    // orderShareRatio needs totalOutputQty across the whole period
                    $orderShareRatio = $totalOutputQty > 0 ? ($totalQty / $totalOutputQty) : 0;
                    $allocatedMonthlyExpenseTotal += $dailyMonthlyForMonth * $orderShareRatio * $daysIncluded;
                }

                // 2) income_percentage: note in original daily() this was applied per day (full percent),
                // so to replicate we compute per-day income percentage amount and multiply by daysIncluded.
                $incomePercentageExpenses = $expensesForMonth->where('type', 'income_percentage');
                if ($incomePercentageExpenses->count() > 0) {
                    foreach ($incomePercentageExpenses as $exp) {
                        $incomePercentageExpenseTotal = ($priceUZS * $totalQty) * ($exp->amount / 100);
                    }
                }

                // 3) amortization: in daily() amortizationExpense = $totalQty * 0.10 * $dollarRate (if any amortization entries exist)
                $amortizationExpenses = $expensesForMonth->where('type', 'amortization');
                if ($amortizationExpenses->count() > 0) {
                    $amortizationExpenseTotal = $totalQty * 0.10 * $dollarRate;
                }
            } // end foreach month segment

            // Note: earlier we used variables $incomePercentageExpenseTotal, $amortizationExpenseTotal,
            // $allocatedMonthlyExpenseTotal — ensure we use correct variable names
            // (typo guard)
            $incomePercentageExpense = $incomePercentageExpenseTotal ?? 0;
            $amortizationExpense = $amortizationExpenseTotal ?? 0;

            // Allocations from global transport/aup/isNotAup proportional to orderShareRatio
            $orderShareRatio = $totalOutputQty > 0 ? ($totalQty / $totalOutputQty) : 0;
            $allocatedTransport = $transportTotal * $orderShareRatio;
            $allocatedAup = $aupTotal * $orderShareRatio;
            $allocatedIsNotAup = $isNotAupTotal * $orderShareRatio;

            // totalExtra (same as daily logic but using monthly sums)
            $totalExtra = $allocatedTransport + $allocatedAup + $allocatedMonthlyExpenseTotal + $incomePercentageExpense + $amortizationExpense;

            $fixedCost = $bonus + $remainder;
            $totalFixedCostForOrder = $fixedCost + $totalExtra;

            $perUnitCost = $totalQty > 0 ? ($totalFixedCostForOrder) / $totalQty : 0;
            $profitUZS = ($priceUZS * $totalQty) - ($totalFixedCostForOrder);

            // Responsible users and submodels
            $responsibleUsers = $orderModel->submodels->map(function ($submodel) {
                return $submodel->group?->group?->responsibleUser;
            })->filter()->unique('id')->values();

            $rasxodPercentOfPrice = $priceUZS > 0
                ? round((($submodelSpendsSum ?? 0) / $priceUZS) * 100, 2)
                : null;

            $orderSummaries[$orderId] = [
                'order' => $order,
                'responsibleUser' => $responsibleUsers,
                'model' => $orderModel->model ?? null,
                'submodels' => $orderModel->submodels->pluck('submodel')->filter()->values(),
                'price_usd' => $priceUSD,
                'price_uzs' => $priceUZS,
                'total_quantity' => $totalQty,
                'rasxod_limit_uzs' => $remainder,
                'rasxod_percent_of_price' => $rasxodPercentOfPrice,
                'bonus' => $bonus,
                'tarification' => $tarification,
                'total_output_cost_uzs' => $priceUSD * $totalQty * $dollarRate,
                'costs_uzs' => [
                    'bonus' => $bonus,
                    'remainder' => $remainder,
                    'tarification' => $tarification,
                    'allocatedTransport' => $allocatedTransport,
                    'allocatedAup' => $allocatedAup,
                    'allocatedMonthlyExpenseMonthly' => $allocatedMonthlyExpenseTotal,
                    'incomePercentageExpense' => $incomePercentageExpense,
                    'amortizationExpense' => $amortizationExpense,
                ],
                'total_fixed_cost_uzs' => $totalFixedCostForOrder,
                'net_profit_uzs' => $profitUZS,
                'cost_per_unit_uzs' => round($perUnitCost),
                'profit_per_unit_uzs' => round(($priceUZS - $perUnitCost)),
                'profitability_percent' => ($totalFixedCostForOrder) > 0
                    ? round(($profitUZS / ($totalFixedCostForOrder)) * 100, 2)
                    : null,
            ];
        } // end foreach grouped orders

        // Now aggregate overall totals similar to original monthlyStats (sum of per-order and global sums)
        $monthlyStats = [];
        $monthlyStats['aup'] = $aupTotal;
        // KPI (bonuses across period)
        $monthlyStats['kpi'] = DB::table('bonuses')->whereBetween('created_at', [$start, $end])->sum('amount');
        $monthlyStats['transport_attendance'] = $transportTotal;
        $monthlyStats['tarification'] = DB::table('employee_tarification_logs')
            ->whereBetween('employee_tarification_logs.date', [$start, $end])
            ->sum('employee_tarification_logs.amount_earned');
        // daily_expenses (sum across period): compute monthly 'monthly' part aggregated over days + all orders incomePercentage & amortization
        // monthly type totals across period:
        $monthlyTypeTotalAcrossPeriod = 0;
        foreach ($monthlyExpenses as $monthKey => $coll) {
            $monthlyTypeTotalAcrossPeriod += $coll->where('type', 'monthly')->sum('amount');
        }
        // BUT note: in daily() the monthlyType contributed per day as monthly/daysInMonth; summing across days will produce
        // monthlyTypeTotalAcrossPeriod * (daysIncluded/daysInMonth) over each month. We already accounted for this in per-order allocation
        // For global "daily_expenses" we should compute total of:
        // sum_over_months( monthly_type_sum / daysInMonth * days_in_segment ) + totalIncomePercentageExpense (sum over orders) + totalAmortizationExpense (sum over orders)
        $totalMonthlyTypeDailySum = 0;
        $monthPeriod = CarbonPeriod::create($start->copy()->startOfMonth(), $end->copy()->startOfMonth())->month();
        foreach ($monthPeriod as $monthStart) {
            $monthKey = $monthStart->format('Y-m');
            $monthBegin = $monthStart->copy()->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $segStart = $start->greaterThan($monthBegin) ? $start->copy() : $monthBegin->copy();
            $segEnd = $end->lessThan($monthEnd) ? $end->copy() : $monthEnd->copy();

            if ($segStart->gt($segEnd)) continue;

            $daysInMonth = $monthStart->daysInMonth;
            $daysIncluded = $segStart->diffInDays($segEnd) + 1;

            $expensesForMonth = $monthlyExpenses->get($monthKey, collect());
            $monthlyTypeSumForMonth = $expensesForMonth->where('type', 'monthly')->sum('amount');
            $totalMonthlyTypeDailySum += ($monthlyTypeSumForMonth / max($daysInMonth, 1)) * $daysIncluded;
        }

        // Sum incomePercentage and amortization across all orders in orderSummaries (we already computed per-order totals in 'costs_uzs' keys)
        $totalIncomePercentageExpense = collect($orderSummaries)->sum(fn($o) => $o['costs_uzs']['incomePercentageExpense'] ?? 0);
        $totalAmortizationExpense = collect($orderSummaries)->sum(fn($o) => $o['costs_uzs']['amortizationExpense'] ?? 0);

        $monthlyStats['daily_expenses'] = $totalMonthlyTypeDailySum + $totalIncomePercentageExpense + $totalAmortizationExpense;

        // total earned & totals from orders
        $monthlyStats['total_earned_uzs'] = collect($orderSummaries)->sum('total_output_cost_uzs');
        $monthlyStats['total_output_cost_uzs'] = collect($orderSummaries)->sum('total_output_cost_uzs');
        $monthlyStats['total_fixed_cost_uzs'] = collect($orderSummaries)->sum('total_fixed_cost_uzs');
        $monthlyStats['net_profit_uzs'] = collect($orderSummaries)->sum('net_profit_uzs') - 0; // orders' net profit is already net per order
        $monthlyStats['employee_count_sum'] = $employeeCountSum;
        $monthlyStats['total_output_quantity'] = $totalOutputQty;
        $monthlyStats['rasxod_limit_uzs'] = collect($orderSummaries)->sum('rasxod_limit_uzs');
        $monthlyStats['transport_employees_count'] = $transportEmployeesCountSum;
        $monthlyStats['transport_per_employee'] = round($transportPerEmployeeSum);

        // per-employee breakdown (mirror original calculation)
        $employeeCountForDivision = max($monthlyStats['employee_count_sum'], 1);
        $rasxodLimitPerEmployee = $monthlyStats['rasxod_limit_uzs'] / $employeeCountForDivision;
        $transportCostPerEmployee = $monthlyStats['transport_attendance'] / $employeeCountForDivision;
        $aupPerEmployee = $monthlyStats['aup'] / $employeeCountForDivision;
        $monthlyExpensePerEmployee = $monthlyStats['daily_expenses'] / $employeeCountForDivision;
        $incomePercentagePerEmployee = $totalIncomePercentageExpense / $employeeCountForDivision;
        $amortizationPerEmployee = $totalAmortizationExpense / $employeeCountForDivision;

        $totalPerEmployee = $rasxodLimitPerEmployee + $transportCostPerEmployee + $aupPerEmployee + $monthlyExpensePerEmployee + $incomePercentagePerEmployee + $amortizationPerEmployee;

        $perEmployeeCosts = [
            'rasxod_limit_uzs' => [
                'amount' => round($rasxodLimitPerEmployee),
                'percent' => $totalPerEmployee > 0 ? round(($rasxodLimitPerEmployee / $totalPerEmployee) * 100, 2) : 0
            ],
            'transport' => [
                'amount' => round($transportCostPerEmployee),
                'percent' => $totalPerEmployee > 0 ? round(($transportCostPerEmployee / $totalPerEmployee) * 100, 2) : 0
            ],
            'aup' => [
                'amount' => round($aupPerEmployee),
                'percent' => $totalPerEmployee > 0 ? round(($aupPerEmployee / $totalPerEmployee) * 100, 2) : 0
            ],
            'monthly_expense' => [
                'amount' => round($monthlyExpensePerEmployee),
                'percent' => $totalPerEmployee > 0 ? round(($monthlyExpensePerEmployee / $totalPerEmployee) * 100, 2) : 0
            ],
            'income_percentage_expense' => [
                'amount' => round($incomePercentagePerEmployee),
                'percent' => $totalPerEmployee > 0 ? round(($incomePercentagePerEmployee / $totalPerEmployee) * 100, 2) : 0
            ],
            'amortization_expense' => [
                'amount' => round($amortizationPerEmployee),
                'percent' => $totalPerEmployee > 0 ? round(($amortizationPerEmployee / $totalPerEmployee) * 100, 2) : 0
            ],
            'total' => round($totalPerEmployee)
        ];

        // cost per unit overall
        $costPerUnitOverall = $monthlyStats['total_output_quantity'] > 0
            ? ($monthlyStats['total_fixed_cost_uzs'] / $monthlyStats['total_output_quantity'])
            : 0;

        // convert orderSummaries to indexed array like original
        $ordersArray = array_values($orderSummaries);

        return response()->json([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days_in_period' => $daysInPeriod,
            'dollar_rate' => $dollarRate,
            'aup' => $monthlyStats['aup'],
            'kpi' => $monthlyStats['kpi'],
            'transport_attendance' => $monthlyStats['transport_attendance'],
            'tarification' => $monthlyStats['tarification'],
            'monthly_expenses' => $monthlyStats['daily_expenses'],
            'total_earned_uzs' => $monthlyStats['total_earned_uzs'],
            'total_output_cost_uzs' => $monthlyStats['total_output_cost_uzs'],
            'total_fixed_cost_uzs' => $monthlyStats['total_fixed_cost_uzs'],
            'net_profit_uzs' => $monthlyStats['net_profit_uzs'],
            'average_employee_count' => $employeeCountSum > 0 ? round($employeeCountSum / max(1, $daysInPeriod - collect($period)->filter(fn($d)=>$d->isSunday())->count())) : 0,
            'per_employee_cost_uzs' => $perEmployeeCosts,
            'orders' => $ordersArray,
            'rasxod_limit_uzs' => $monthlyStats['rasxod_limit_uzs'],
            'employee_count_sum' => $monthlyStats['employee_count_sum'],
            'transport_employees_count' => $monthlyStats['transport_employees_count'],
            'transport_per_employee' => $monthlyStats['transport_per_employee'],
            'total_output_quantity' => $monthlyStats['total_output_quantity'],
            'cost_per_unit_overall_uzs' => round($costPerUnitOverall, 2),
        ]);
    }

    public function getDailyCost(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->date ?? Carbon::today()->toDateString();
        $dollarRate = $request->dollar_rate ?? 12900;
        $branchId = auth()->user()->employee->branch_id;

        $carbonDate = Carbon::parse($date);
        $daysInMonth = $carbonDate->daysInMonth;

        // Shu sanadagi barcha ishchilarning IDlari
        $relatedEmployeeIds = DB::table('employee_tarification_logs')
            ->whereDate('date', $date)
            ->whereIn('employee_id', Employee::where('branch_id', $branchId)->pluck('id'))
            ->pluck('employee_id')
            ->unique()
            ->values();

        // Transport - har doim hisoblanadi
        $transport = DB::table('transport_attendance')
            ->join('transport', 'transport_attendance.transport_id', '=', 'transport.id')
            ->whereDate('transport_attendance.date', $date)
            ->where('transport.branch_id', $branchId)
            ->sum(DB::raw('(transport.salary + transport.fuel_bonus) * transport_attendance.attendance_type'));


        // 🚍 Shu kuni transportda kelgan odamlar soni
        $transportEmployeesCount = DB::table('employee_transport_daily as etd')
            ->join('employees as e', 'etd.employee_id', '=', 'e.id')
            ->join('transport as t', 'etd.transport_id', '=', 't.id')
            ->whereDate('etd.date', $date)
            ->where('t.branch_id', $branchId)   // transport shu filialdan bo‘lishi kerak
            ->where('e.branch_id', $branchId)   // xodim ham shu filialdan bo‘lishi kerak
            ->count('etd.employee_id');

        // 🚍 Bir kishi uchun transport xarajati
        $transportPerEmployee = $transportEmployeesCount > 0
            ? $transport / $transportEmployeesCount
            : 0;


        // Monthly expenses ni type bo'yicha ajratish - har doim hisoblanadi
        $monthlyExpenses = DB::table('monthly_expenses')
            ->whereMonth('month', $carbonDate->month)
            ->whereYear('month', $carbonDate->year)
            ->where('branch_id', $branchId)
            ->get();

        // Type = 'monthly' bo'lgan xarajatlarni kuniga bo'lib hisoblash
        $dailyExpenseMonthly = $monthlyExpenses
                ->where('type', 'monthly')
                ->sum('amount') / $daysInMonth;

        $thisBranchEmployeeIds = Employee::where('branch_id', $branchId)->pluck('id');

        // AUP xarajatlari - har doim hisoblanadi
        $aup = DB::table('attendance_salary')
            ->join('attendance', 'attendance_salary.attendance_id', '=', 'attendance.id')
            ->join('employees', 'attendance_salary.employee_id', '=', 'employees.id')
            ->where('employees.branch_id', $branchId)
            ->where('employees.type', 'aup')
            ->whereDate('attendance.date', $date)
            ->whereIn('attendance_salary.employee_id', $thisBranchEmployeeIds)
            ->sum('attendance_salary.amount');

        // AUP emas lekin oylikka ishlovchilar - har doim hisoblanadi
        $isNotAup = DB::table('attendance_salary')
            ->join('attendance', 'attendance_salary.attendance_id', '=', 'attendance.id')
            ->join('employees', 'attendance_salary.employee_id', '=', 'employees.id')
            ->where('employees.branch_id', $branchId)
            ->where('employees.type', '!=', 'aup')
            ->whereDate('attendance.date', $date)
            ->whereIn('attendance_salary.employee_id', $thisBranchEmployeeIds)
            ->sum('attendance_salary.amount');

        // Ishchilar soni - har doim hisoblanadi
        $employees = Attendance::whereDate('date', $date)
            ->where('status', 'present')
            ->whereHas('employee', function ($q) use ($branchId)  {
                $q->where('status', '!=', 'kicked');
                $q->where('branch_id', $branchId);
            })->count();

        $outputs = SewingOutputs::with([
            'orderSubmodel.orderModel.order',
            'orderSubmodel.orderModel.model',
            'orderSubmodel.orderModel.submodels.submodel'
        ])
            ->whereDate('created_at', $date)
            ->whereHas('orderSubmodel.orderModel.order', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->get();

        $grouped = $outputs->groupBy(fn($item) => optional($item->orderSubmodel->orderModel)->order_id);
        $totalOutputQty = $outputs->sum('quantity');

        // Agar orders mavjud bo'lsa, ularga xos harajatlarni hisoblash
        $orders = collect();
        $totalEarned = 0;
        $totalOrderSpecificCosts = 0;

        if ($grouped->count() > 0) {
            $orders = $grouped->map(function ($items) use (
                $dollarRate, $date, $relatedEmployeeIds,
                $dailyExpenseMonthly, $transport, $aup, $isNotAup, $totalOutputQty, $monthlyExpenses
            ) {
                $first = $items->first();

                $orderModel = optional(optional($first)->orderSubmodel)->orderModel;
                $order = optional($orderModel)->order;

                $orderId = $order->id ?? null;

                $totalQty = $items->sum('quantity');
                $priceUSD = $order->price ?? 0;
                $priceUZS = $priceUSD * $dollarRate;

                $submodelSpendsSum = \DB::table('order_sub_models as osm')
                    ->join('submodel_spends as ss', 'ss.submodel_id', '=', 'osm.id')
                    ->where('osm.order_model_id', $orderModel->id)
                    ->where('ss.region', 'uz')
                    ->sum('ss.summa');

                $remainder = $submodelSpendsSum * $totalQty;


                $bonus = DB::table('bonuses')
                    ->whereDate('created_at', $date)
                    ->where('order_id', $orderId)
                    ->sum('amount');

                $tarification = DB::table('employee_tarification_logs')
                    ->join('tarifications', 'employee_tarification_logs.tarification_id', '=', 'tarifications.id')
                    ->join('tarification_categories', 'tarifications.tarification_category_id', '=', 'tarification_categories.id')
                    ->join('order_sub_models', 'tarification_categories.submodel_id', '=', 'order_sub_models.id')
                    ->join('order_models', 'order_sub_models.order_model_id', '=', 'order_models.id')
                    ->join('orders', 'order_models.order_id', '=', 'orders.id')
                    ->whereDate('employee_tarification_logs.date', $date)
                    ->where('orders.id', $orderId)
                    ->sum('employee_tarification_logs.amount_earned');

                // Type bo'yicha xarajatlarni hisoblash
                $orderShareRatio = $totalOutputQty > 0 ? $totalQty / $totalOutputQty : 0;

                // Monthly type xarajat
                $allocatedMonthlyExpenseMonthly = $dailyExpenseMonthly * $orderShareRatio;

                // Income percentage type xarajat
                $incomePercentageExpense = 0;
                $incomePercentageExpenses = $monthlyExpenses->where('type', 'income_percentage');
                foreach ($incomePercentageExpenses as $expense) {
                    $percentageAmount = ($priceUZS * $totalQty) * ($expense->amount / 100);
                    $incomePercentageExpense += $percentageAmount;
                }

                // Amortization type xarajat (har bir mahsulot uchun 10 sent)
                $amortizationExpense = 0;
                $amortizationExpenses = $monthlyExpenses->where('type', 'amortization');
                if ($amortizationExpenses->count() > 0) {
                    $amortizationExpense = $totalQty * 0.10 * $dollarRate;
                }

                $fixedCost = $bonus + $remainder;

                $allocatedTransport = $transport * $orderShareRatio;
                $allocatedAup = $aup * $orderShareRatio;
                $allocatedIsNotAup = $isNotAup * $orderShareRatio;

                $totalExtra = $allocatedTransport + $allocatedAup + $allocatedMonthlyExpenseMonthly + $incomePercentageExpense + $amortizationExpense;

                $perUnitCost = $totalQty > 0 ? ($fixedCost + $totalExtra) / $totalQty : 0;
                $profitUZS = ($priceUZS * $totalQty) - ($fixedCost + $totalExtra);

                $responsibleUsers = $orderModel->submodels->map(function ($submodel) {
                    return $submodel->group?->group?->responsibleUser;
                })->filter()->unique('id')->values();

                $rasxodPercentOfPrice = $priceUZS > 0
                    ? round((($submodelSpendsSum ?? 0) / $priceUZS) * 100, 2)
                    : null;

                return [
                    'order' => $order,
                    'responsibleUser' => $responsibleUsers,
                    'model' => $orderModel->model ?? null,
                    'submodels' => $orderModel->submodels->pluck('submodel')->filter()->values(),
                    'price_usd' => $priceUSD,
                    'price_uzs' => $priceUZS,
                    'total_quantity' => $totalQty,
                    'rasxod_limit_uzs' => $remainder,
                    'rasxod_percent_of_price' => $rasxodPercentOfPrice,
                    'bonus' => $bonus,
                    'tarification' => $tarification,
                    'total_output_cost_uzs' => $priceUSD * $totalQty * $dollarRate,
                    'costs_uzs' => compact('bonus', 'remainder', 'tarification', 'allocatedTransport', 'allocatedAup', 'allocatedMonthlyExpenseMonthly', 'incomePercentageExpense', 'amortizationExpense'),
                    'total_fixed_cost_uzs' => $fixedCost + $totalExtra,
                    'net_profit_uzs' => $profitUZS,
                    'cost_per_unit_uzs' => round($perUnitCost),
                    'profit_per_unit_uzs' => round(($priceUZS - $perUnitCost)),
                    'profitability_percent' => ($fixedCost + $totalExtra) > 0
                        ? round(($profitUZS / ($fixedCost + $totalExtra)) * 100, 2)
                        : null,
                ];
            })->values();

            $totalEarned = $orders->sum('total_output_cost_uzs');
            $totalOrderSpecificCosts = $orders->sum('total_fixed_cost_uzs');
        }

        // Agar orders bo'sh bo'lsa ham harajatlarni hisoblash
        $totalIncomePercentageExpense = $orders->sum('costs_uzs.incomePercentageExpense');
        $totalAmortizationExpense = $orders->sum('costs_uzs.amortizationExpense');

        // Kunlik xarajat (orders bo'lmasa ham mavjud)
        $dailyExpense = $dailyExpenseMonthly + $totalIncomePercentageExpense + $totalAmortizationExpense;

        // Umumiy harajat (orders bo'lmasa ham o'zgarmas harajatlar mavjud)
        $totalFixedCost = $transport + $aup + $dailyExpense + $orders->sum('rasxod_limit_uzs') ;

        // Per employee cost calculation
        $perEmployeeCosts = [];
        $employeeCount = max($employees, 1);

        $rasxodLimit = $orders->sum('rasxod_limit_uzs') / $employeeCount;
        $transportCost = $transport / $employeeCount;
        $aupCost = $aup / $employeeCount;
        $isNotAupCost = $isNotAup / $employeeCount;
        $monthlyExpenseCost = $dailyExpenseMonthly / $employeeCount;
        $incomePercentageCost = $totalIncomePercentageExpense / $employeeCount;
        $amortizationCost = $totalAmortizationExpense / $employeeCount;

        $totalPerEmployee = $rasxodLimit + $transportCost + $aupCost + $monthlyExpenseCost + $incomePercentageCost + $amortizationCost;

        $perEmployeeCosts = [
            'rasxod_limit_uzs' => [
                'amount' => round($rasxodLimit),
                'percent' => $totalPerEmployee > 0 ? round(($rasxodLimit / $totalPerEmployee) * 100, 2) : 0
            ],
            'transport' => [
                'amount' => round($transportCost),
                'percent' => $totalPerEmployee > 0 ? round(($transportCost / $totalPerEmployee) * 100, 2) : 0
            ],
            'aup' => [
                'amount' => round($aupCost),
                'percent' => $totalPerEmployee > 0 ? round(($aupCost / $totalPerEmployee) * 100, 2) : 0
            ],
            'monthly_expense' => [
                'amount' => round($monthlyExpenseCost),
                'percent' => $totalPerEmployee > 0 ? round(($monthlyExpenseCost / $totalPerEmployee) * 100, 2) : 0
            ],
            'income_percentage_expense' => [
                'amount' => round($incomePercentageCost),
                'percent' => $totalPerEmployee > 0 ? round(($incomePercentageCost / $totalPerEmployee) * 100, 2) : 0
            ],
            'amortization_expense' => [
                'amount' => round($amortizationCost),
                'percent' => $totalPerEmployee > 0 ? round(($amortizationCost / $totalPerEmployee) * 100, 2) : 0
            ],
            'total' => round($totalPerEmployee)
        ];

        // Cost per unit calculation
        $costPerUnitOverall = $totalOutputQty > 0 ? $totalFixedCost / $totalOutputQty : 0;

        // Tarification calculation (orders bo'lmasa ham hisoblanishi kerak)
        $tarificationTotal = DB::table('employee_tarification_logs')
            ->join('tarifications', 'employee_tarification_logs.tarification_id', '=', 'tarifications.id')
            ->join('tarification_categories', 'tarifications.tarification_category_id', '=', 'tarification_categories.id')
            ->join('order_sub_models', 'tarification_categories.submodel_id', '=', 'order_sub_models.id')
            ->join('order_models', 'order_sub_models.order_model_id', '=', 'order_models.id')
            ->join('orders', 'order_models.order_id', '=', 'orders.id')
            ->whereDate('employee_tarification_logs.date', $date)
            ->whereIn('employee_tarification_logs.employee_id', $relatedEmployeeIds)
            ->sum('employee_tarification_logs.amount_earned');

        // Bonuses calculation (orders bo'lmasa ham hisoblanishi kerak)
        $kpiTotal = DB::table('bonuses')->whereDate('created_at', $date)->sum('amount');

        return response()->json([
            'date' => $date,
            'dollar_rate' => $dollarRate,
            'orders' => $orders,
            'transport_attendance' => $transport,
            'transport_employees_count' => $transportEmployeesCount,
            'transport_per_employee' => round($transportPerEmployee),
            'daily_expenses' => $dailyExpense,
            'aup' => $aup,
            'isNotAup' => $isNotAup,
            'total_earned_uzs' => $totalEarned,
            'total_fixed_cost_uzs' => $totalFixedCost,
            'employee_count' => $employees,
            'rasxod_limit_uzs' => $orders->sum('rasxod_limit_uzs'),
            'per_employee_cost_uzs' => $perEmployeeCosts,
            'net_profit_uzs' => $totalEarned - $totalFixedCost,
            'kpi' => $kpiTotal,
            'tarification' => $tarificationTotal,
            'total_output_quantity' => $totalOutputQty,
            'cost_per_unit_overall_uzs' => round($costPerUnitOverall, 2),
        ]);
    }

    public function getMonthlyExpense(Request $request): \Illuminate\Http\JsonResponse
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $expenses = MonthlyExpense::whereMonth('month', Carbon::parse($month)->month)
            ->whereYear('month', Carbon::parse($month)->year)
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->get();

        if ($expenses->isEmpty()) {
            return response()->json(
                [
                    'month' => $month,
                'expenses' => []
                ],
            );
        }

        return response()->json([
            'month' => $month,
            'expenses' => $expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'name' => $expense->name,
                    'type' => $expense->type,
                    'amount' => $expense->amount,
                    'date' => $expense->month,
                ];
            }),
        ]);
    }

    public function storeMonthlyExpense(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'month' => 'required|date_format:Y-m',
            'name' => 'required|string|max:255',
        ]);

        $expense = MonthlyExpense::create([
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'month' => $validated['month'] . '-01',
            'name' => $validated['name'],
            'branch_id' => auth()->user()->employee->branch_id,
        ]);

        return response()->json(['message' => 'Saved', 'data' => $expense]);
    }

    public function editMonthlyExpense($id, Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'month' => 'nullable|date_format:Y-m',
            'name' => 'nullable|string|max:255',
        ]);
        $expense = MonthlyExpense::findOrFail($id);

        if (isset($validated['month'])) {
            $validated['month'] .= '-01'; // 2025-06 => 2025-06-01
        }

        $expense->update($validated);

        return response()->json(['message' => 'Updated', 'data' => $expense]);
    }

    public function exportGroupsByDepartmentIdPdf(Request $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $departmentId = $request->input('department_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $group_id = $request->input('group_id');
        $orderIds = $request->input('order_ids', []); // ✅ order_ids qo‘shdik

        if (!$departmentId) {
            return response()->json(['message' => '❌ department_id kiritilmadi.'], 422);
        }

        $groupQuery = Group::where('department_id', $departmentId)
            ->with(['employees' => function ($query) {
                $query->select('id', 'name', 'position_id', 'group_id', 'balance', 'payment_type', 'status', 'type')
                    ->where('type', '!=', 'aup')
                    ->with('salaryPayments');
            }]);

        if (!empty($group_id)) {
            $groupQuery->where('id', $group_id);
        }

        $groups = $groupQuery->get();

        $result = $groups->map(function ($group) use ($startDate, $endDate, $orderIds) {
            $employees = $group->employees
                ->map(fn($employee) => $this->getEmployeeEarnings($employee, $startDate, $endDate, $orderIds)) // ✅ orderIds qo‘shildi
                ->filter(function ($employeeData) {
                    if (!$employeeData) {
                        return false;
                    }
                    if (
                        ($employeeData['total_earned'] ?? 0) == 0 &&
                        ($employeeData['balance'] ?? 0) == 0
                    ) {
                        return false;
                    }
                    return true;
                })
                ->sortBy(fn($e) => mb_strtolower($e['name'] ?? ''))
                ->values();

            $groupTotal = $employees->sum(fn($e) => $e['balance'] ?? 0);

            return [
                'id' => $group->id,
                'name' => $group->name,
                'total_balance' => $groupTotal,
                'employees' => $employees->values()->toArray(),
            ];
        })->values()->toArray();

        /*
        // Guruhsiz xodimlar (hozircha jo‘natilmaydi)
        $ungroupedEmployees = Employee::where('department_id', $departmentId)
            ->whereNull('group_id')
            ->where('type', '!=', 'aup')
            ->select('id', 'name', 'group_id', 'position_id', 'balance', 'payment_type', 'status', 'type')
            ->with('salaryPayments')
            ->get()
            ->map(fn($employee) => $this->getEmployeeEarnings($employee, $startDate, $endDate, $orderIds))
            ->filter(function ($employeeData) {
                if (!$employeeData) {
                    return false;
                }
                if (
                    ($employeeData['total_earned'] ?? 0) == 0 &&
                    ($employeeData['balance'] ?? 0) == 0
                ) {
                    return false;
                }
                return true;
            })
            ->sortBy(fn($e) => mb_strtolower($e['name'] ?? ''))
            ->values();

        if ($ungroupedEmployees->isNotEmpty()) {
            $ungroupedTotal = $ungroupedEmployees->sum(fn($e) => $e['balance'] ?? 0);
            $result[] = [
                'id' => null,
                'name' => 'Guruhsiz',
                'total_balance' => $ungroupedTotal,
                'employees' => $ungroupedEmployees->values()->toArray(),
            ];
        }
        */

        // PDF yasash
        $pdf = Pdf::loadView('pdf.group-salary-report', [
            'data' => $result,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('group-salary-report.pdf');
    }

    public function giveSalaryOrAdvance(Request $request)
    {
        return DB::transaction(function () use ($request) {

            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'amount' => 'required|numeric',
                'month' => 'required|date_format:Y-m',
                'type' => 'required|in:salary,advance',
                'group_id' => 'nullable|exists:groups,id', // 🔥 YANGI
                'comment' => 'nullable|string',
            ]);

            $validated['month'] = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
            $employee = Employee::findOrFail($validated['employee_id']);

            $cashboxBalance = CashboxBalance::with('cashbox')
                ->whereHas('cashbox', fn($q) =>
                $q->where('branch_id', auth()->user()->employee->branch_id)
                )
                ->whereHas('currency', fn($q) =>
                $q->where('name', "So'm")
                )
                ->firstOrFail();

            $cashboxId = $cashboxBalance->cashbox_id;
            $currency = Currency::where('name', "So'm")->firstOrFail();

            // Miqdorni tekshirish
            $absoluteAmount = abs($validated['amount']);
            $isPositive = $validated['amount'] >= 0;

            SalaryPayment::create([
                'employee_id' => $validated['employee_id'],
                'group_id' => $validated['group_id'] ?? null,
                'amount' => $validated['amount'],
                'month' => $validated['month'],
                'type' => $validated['type'],
                'comment' => $validated['comment'] ?? null,
            ]);

            CashboxTransaction::create([
                'cashbox_id' => $cashboxId,
                'currency_id' => $currency->id,
                'type' => $isPositive ? 'expense' : 'income',
                'amount' => $absoluteAmount,
                'date' => now()->toDateString(),
                'destination_id' => $employee->id,
                'via_id' => auth()->user()->employee->id,
                'purpose' => $validated['type'] === 'advance' ? "Avans to'lovi" : "Oylik to'lovi",
                'comment' => ($validated['type'] === 'advance' ? 'Avans' : 'Oylik') . ' - ' . $employee->name . ' | ' . ($validated['comment'] ?? ''),
                'branch_id' => auth()->user()->employee->branch_id,
            ]);

            if ($isPositive) {
                $employee->decrement('balance', $absoluteAmount);
                $cashboxBalance->decrement('amount', $absoluteAmount);
            } else {
                $employee->increment('balance', $absoluteAmount);
                $cashboxBalance->increment('amount', $absoluteAmount);
            }

            $updatedBalance = CashboxBalance::find($cashboxBalance->id);

            DB::afterCommit(function () use ($employee, $validated, $updatedBalance, $isPositive, $absoluteAmount) {

                $remainingBalance = number_format($updatedBalance->amount, 0, '.', ' ');
                $icon = $isPositive ? '💸' : '↩️';
                $action = $isPositive ? "To'lov amalga oshirildi!" : "To'lov qaytarildi!";

                $text = "$icon *$action*\n"
                    . "👤 Xodim: {$employee->name}\n"
                    . "💰 Miqdor: " . number_format($absoluteAmount, 0, '.', ' ') . " so'm" . ($isPositive ? '' : ' (qaytarildi)') . "\n"
                    . "📅 Oy: " . $validated['month']->format('Y-m') . "\n"
                    . "🏷️ Turi: " . ($validated['type'] === 'advance' ? 'Avans' : 'Oylik') . "\n"
                    . "🏢 Filial: " . (auth()->user()->employee->branch->name ?? '-') . "\n"
                    . "👥 Guruh: " . ($validated['group_id'] ?? '-') . "\n" // 🔥 YANGI
                    . "📝 Izoh: " . ($validated['comment'] ?? '-');

                Http::post("https://api.telegram.org/bot7778276162:AAHVKgbh5mJlgp7jMhw_VNunvvR3qoDyjms/sendMessage", [
                    'chat_id' => -979504247,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]);
            });

            return response()->json([
                'message' => $isPositive ? "✅ To'lov muvaffaqiyatli amalga oshirildi." : "✅ To'lov qaytarildi."
            ]);
        });
    }

    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {

        $orders = \App\Models\Order::with(['orderModel.submodels.submodel', 'orderModel.model', 'orderModel.submodels.group.group'])
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->get();

        return response()->json($orders);
    }

    public function exportEmployeeAttendance(Request $request)
    {
        $departmentId = $request->input('department_id');
        $branchId = auth()->user()->employee->branch_id;
        $groupId = $request->input('group_id');
        $month = $request->input('month', date('Y-m')); // Format: 2024-01
        $type = $request->input('type'); // aup, simple

        // Oyning birinchi va oxirgi kunlarini aniqlash
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;

        // Xodimlarni olish
        $employeeQuery = Employee::select('id', 'name', 'group_id', 'department_id')
            ->with(['group:id,name', 'department:id,name']);

        $employeeQuery->where('status', 'working');

        // Filtrlar
        if ($departmentId) {
            $employeeQuery->where('department_id', $departmentId);
        } elseif ($branchId) {
            $employeeQuery->whereHas('department', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            });
        }

        if ($groupId) {
            $employeeQuery->where('group_id', $groupId);
        }

        if ($type === 'aup') {
            $employeeQuery->where('type', 'aup');
        } elseif ($type === 'simple') {
            $employeeQuery->where('type', '!=', 'aup');
        } else {
            $employeeQuery->whereIn('type', ['aup', 'simple']);
        }

        $employees = $employeeQuery->orderBy('name')->get();

        if ($employees->isEmpty()) {
            return response()->json(['message' => 'Xodimlar topilmadi'], 404);
        }

        // Excel yaratish
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Sarlavha
        $sheet->setCellValue('A1', '№');
        $sheet->setCellValue('B1', 'Xodim ismi');

        // Kunlarni qo'yish (C1 dan boshlab)
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($day + 2); // C, D, E...
            $sheet->setCellValue($column . '1', $day);
        }

        // Xodimlar ma'lumotlarini qo'yish
        $row = 2;
        foreach ($employees as $index => $employee) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $employee->name);

            // Kunlar uchun bo'sh kataklar (istegancha to'ldirish mumkin)
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($day + 2);
                $sheet->setCellValue($column . $row, ''); // Bo'sh katak
            }

            $row++;
        }

        // Styling
        $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($daysInMonth + 2) . '1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0']
            ]
        ]);

        // Barcha ma'lumotlar uchun border
        $dataRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($daysInMonth + 2) . ($row - 1);
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);

        // Column width
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(25);
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($day + 2);
            $sheet->getColumnDimension($column)->setWidth(4);
        }

        // Fayl nomini yaratish
        $fileName = 'Xodimlar_Davomat_' . $month . '_' . date('YmdHis') . '.xlsx';

        // Response headers
        $response = response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);

        return $response;
    }

    public function getGroupsByDepartmentId(Request $request): \Illuminate\Http\JsonResponse
    {
        $departmentId = $request->input('department_id');
        $branchId = auth()->user()->employee->branch_id;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $group_id = $request->input('group_id');
        $orderIds = $request->input('order_ids', []); // array
        $type = $request->input('type'); // normal yoki aup

        // Agar department_id ham, branch_id ham kelmasa xato
        if (!$departmentId && !$branchId) {
            return response()->json(['message' => '❌ department_id yoki branch_id kiritilishi shart.'], 422);
        }

        // Guruhlarni olish
        $groupQuery = Group::query();

        if ($departmentId) {
            // Agar department_id berilgan bo'lsa
            $groupQuery->where('department_id', $departmentId);
        } elseif ($branchId) {
            // Agar faqat branch_id berilgan bo'lsa, shu branchdagi barcha departmentlar
            $groupQuery->whereHas('department', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            });
        }

        $groupQuery->with(['employees' => function ($query) use ($type) {
            $query->select('id', 'name', 'position_id', 'group_id', 'salary', 'balance', 'payment_type', 'status')
                ->with('salaryPayments');
            if ($type === 'aup') {
                $query->where('type', 'aup');
            } elseif ($type === 'simple') {
                $query->where('type', '!=', 'aup');
            } else {
                $query->whereIn('type', ['aup','simple']);
            }
        }]);

        if (!empty($group_id)) {
            $groupQuery->where('id', $group_id);
        }

        $groups = $groupQuery->get();

        $result = $groups->map(function ($group) use ($startDate, $endDate, $orderIds) {
            $employees = $group->employees
                ->map(function ($employee) use ($startDate, $endDate, $orderIds) {
                    return $this->getEmployeeEarnings($employee, $startDate, $endDate, $orderIds);
                })
                ->filter();

            $groupTotal = $employees->sum(fn($e) => $e['balance'] ?? 0);

            return [
                'id' => $group->id,
                'name' => $group->name,
                'total_balance' => $groupTotal,
                'employees' => $employees->values()->toArray(),
            ];
        })->values()->toArray();

        // Guruhsiz xodimlarni olish
        $ungroupedQuery = Employee::whereNull('group_id');

        if ($departmentId) {
            $ungroupedQuery->where('department_id', $departmentId);
        } elseif ($branchId) {
            $ungroupedQuery->whereHas('department', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            });
        }

        $ungroupedEmployees = $ungroupedQuery
            ->when($type === 'aup', fn($q) => $q->where('type', 'aup'))
            ->when($type === 'simple', fn($q) => $q->where('type', '!=', 'aup'))
            ->when(!$type || !in_array($type, ['aup', 'simple']), fn($q) => $q->whereIn('type', ['aup','simple']))
            ->select('id', 'name', 'group_id', 'position_id', 'balance', 'salary', 'payment_type', 'status')
            ->with('salaryPayments')
            ->get()
            ->map(function ($employee) use ($startDate, $endDate, $orderIds) {
                return $this->getEmployeeEarnings($employee, $startDate, $endDate, $orderIds);
            })
            ->filter();

        if ($ungroupedEmployees->isNotEmpty()) {
            $ungroupedTotal = $ungroupedEmployees->sum(fn($e) => $e['balance'] ?? 0);

            $result[] = [
                'id' => null,
                'name' => 'Guruhsiz',
                'total_balance' => $ungroupedTotal,
                'employees' => $ungroupedEmployees->values()->toArray(),
            ];
        }

        return response()->json($result);
    }

    //2-usul

    public function getGroupsOrdersEarnings(Request $request): \Illuminate\Http\JsonResponse
    {
        $departmentId = $request->input('department_id');
        $branchId = auth()->user()->employee->branch_id ?? null;
        $groupId = $request->input('group_id');
        $type = $request->input('type');
        $month = $request->input('month', date('Y-m'));

        if (!$departmentId && !$branchId) {
            return response()->json(['message' => '❌ department_id yoki branch_id kiritilishi shart.'], 422);
        }

        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        // BULK: Barcha kerakli order ID'larni bir marta olamiz
        $addOrderIds = MonthlySelectedOrder::where('month', $monthDate)
            ->pluck('order_id')
            ->toArray();

        $allOrderIds = Order::pluck('id')->toArray();
        $minusOrderIds = array_diff($allOrderIds, $addOrderIds);

        // BULK: Employee ID'larni olish
        $employeeQuery = Employee::select('id', 'name', 'position_id', 'group_id', 'salary', 'balance', 'payment_type', 'status', 'department_id');

        if ($departmentId) {
            $employeeQuery->where('department_id', $departmentId);
        } elseif ($branchId) {
            $employeeQuery->whereHas('department', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        if ($type === 'aup') {
            $employeeQuery->where('type', 'aup');
        } elseif ($type === 'simple') {
            $employeeQuery->where('type', '!=', 'aup');
        } else {
            $employeeQuery->whereIn('type', ['aup', 'simple']);
        }

        if (!empty($groupId)) {
            $employeeQuery->where('group_id', $groupId);
        }

        $employees = $employeeQuery->get();
        $employeeIds = $employees->pluck('id')->toArray();

        if (empty($employeeIds)) {
            return response()->json([]);
        }

        // BULK: Barcha kerakli ma'lumotlarni bir marta olamiz

        // 1. Attendance salaries - BULK
        // Attendance salaries - BULK + check for multiple entries per day
        $attendanceData = DB::table('attendance_salary')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->select('employee_id', 'date', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as entries_count'))
            ->groupBy('employee_id', 'date') // employee+date bo'yicha guruhlash
            ->get()
            ->groupBy('employee_id'); // employee bo'yicha key qilamiz

        $presentAttendance = DB::table('attendances')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'present')
            ->select('employee_id')
            ->groupBy('employee_id')
            ->pluck('employee_id')
            ->toArray();

// Tekshirish: bir kunda bir nechta yozuv bo'lsa, xato qaytarish
        foreach ($attendanceData as $empId => $recordsByDate) {
            foreach ($recordsByDate as $record) {
                if ($record->entries_count > 1) {
                    return response()->json([
                        'message' => "❌ Muammo: Employee ID {$empId} uchun {$record->date} sanasida {$record->entries_count} ta attendance_salary yozilgan. Iltimos, ma'lumotlarni tekshiring."
                    ], 422);
                }
            }
        }

// Attendance summasini olish (normal holat)
        $attendanceTotals = $attendanceData->map(function ($recordsByDate) {
            $totalAmount = $recordsByDate->sum('total_amount');
            $daysCount = $recordsByDate->count();
            return (object)[
                'total_amount' => $totalAmount,
                'days_count' => $daysCount,
            ];
        });

        // 2. Tarification logs with all relations - BULK
        $tarificationData = DB::table('employee_tarification_logs as etl')
            ->join('tarifications as t', 'etl.tarification_id', '=', 't.id')
            ->join('tarification_categories as tc', 't.tarification_category_id', '=', 'tc.id')
            ->join('order_sub_models as sm', 'tc.submodel_id', '=', 'sm.id')
            ->join('order_models as om', 'sm.order_model_id', '=', 'om.id')
            ->join('orders as o', 'om.order_id', '=', 'o.id')
            ->whereIn('etl.employee_id', $employeeIds)
            ->whereNotIn('o.id', $minusOrderIds)
            ->select('etl.employee_id', 'etl.amount_earned', 'o.id as order_id')
            ->get()
            ->groupBy('employee_id');

        // 3. Salary payments - BULK
        $paymentsData = DB::table('salary_payments')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('month', [$startDate, $endDate])
            ->select('employee_id', 'type', 'amount', 'date', 'comment', 'month')
            ->get()
            ->groupBy('employee_id');

        // 4. Monthly Pieceworks - BULK
        $monthlyPieceworksData = DB::table('employee_monthly_pieceworks as emp')
            ->leftJoin('users as u', 'emp.created_by', '=', 'u.id')
            ->leftJoin('employees as e', 'emp.employee_id', '=', 'e.id')
            ->whereIn('emp.employee_id', $employeeIds)
            ->where('emp.month', $monthDate)
            ->select('emp.id', 'emp.employee_id', 'emp.amount', 'emp.comment', 'emp.status', 'e.name as created_by_name')
            ->get()
            ->keyBy('employee_id');

        // 5. Monthly Salaries - BULK
        $monthlySalariesData = DB::table('employee_monthly_salaries as ems')
            ->leftJoin('users as u', 'ems.created_by', '=', 'u.id')
            ->leftJoin('employees as e', 'ems.employee_id', '=', 'e.id')
            ->whereIn('ems.employee_id', $employeeIds)
            ->where('ems.month', $monthDate)
            ->select( 'ems.id', 'ems.employee_id', 'ems.comment', 'ems.amount', 'ems.status', 'e.name as created_by_name')
            ->get()
            ->keyBy('employee_id');

        // 6. Extra orders data if needed
        $extraOrdersData = [];
        if (!empty($addOrderIds)) {
            $extraOrdersData = Order::whereIn('id', $addOrderIds)
                ->pluck('id')
                ->toArray();
        }

        // BULK: Positions va Groups - bir marta olamiz
        $positions = DB::table('positions')
            ->whereIn('id', $employees->pluck('position_id')->filter()->unique())
            ->pluck('name', 'id');

        $groups = DB::table('groups')
            ->whereIn('id', $employees->pluck('group_id')->filter()->unique())
            ->pluck('name', 'id');

        // PROCESSING: Har bir employee uchun ma'lumotlarni process qilamiz
        $processedEmployees = [];

        foreach ($employees as $employee) {
            // Attendance
            $attendanceInfo = $attendanceTotals->get($employee->id);
            $attendanceTotal = $attendanceInfo ? (float)$attendanceInfo->total_amount : 0;
            $attendanceDays = $attendanceInfo ? (int)$attendanceInfo->days_count : 0;

            // Tarification
            $empTarificationLogs = $tarificationData->get($employee->id, collect());
            $tarificationTotal = $empTarificationLogs->sum('amount_earned');
            $orderIds = $empTarificationLogs->pluck('order_id')->unique()->merge($extraOrdersData)->unique()->values();

            // Monthly Piecework
            $monthlyPiecework = $monthlyPieceworksData->get($employee->id);
            $monthlyPieceworkData = null;
            if ($monthlyPiecework) {
                $monthlyPieceworkData = [
                    'id' => $monthlyPiecework->id,
                    'amount' => (float) $monthlyPiecework->amount,
                    'status' => (bool) $monthlyPiecework->status,
                    'created_by' => $monthlyPiecework->created_by_name,
                    'comment' => $monthlyPiecework->comment,
                ];
            }

            // Monthly Salary
            $monthlySalary = $monthlySalariesData->get($employee->id);
            $monthlySalaryData = null;
            if ($monthlySalary) {
                $monthlySalaryData = [
                    'id' => $monthlySalary->id,
                    'amount' => (float) $monthlySalary->amount,
                    'status' => (bool) $monthlySalary->status,
                    'created_by' => $monthlySalary->created_by_name,
                    'comment' => $monthlySalary->comment,
                ];
            }

            $totalEarned = $tarificationTotal + $attendanceTotal;

            $hasPresent = in_array($employee->id, $presentAttendance);

            if ($totalEarned <= 0 && !$hasPresent) {
                continue;
            }

            // Payments
            $empPayments = $paymentsData->get($employee->id, collect());
            $paidAmountsByType = [];
            $paidTotal = 0;

            foreach ($empPayments->groupBy('type') as $ptype => $payments) {
                $paidAmountsByType[$ptype] = $payments->map(function ($payment) use (&$paidTotal) {
                    $paidTotal += (float) $payment->amount;
                    return [
                        'amount' => (float) $payment->amount,
                        'date' => $payment->date,
                        'comment' => $payment->comment,
                        'month' => $payment->month ? Carbon::parse($payment->month)->format('Y-m') : null,
                    ];
                })->values()->toArray();
            }

            // Status check
            if ($employee->status === 'kicked' && $totalEarned < 0) {
                continue;
            }

            $processedEmployees[] = [
                'id' => $employee->id,
                'name' => $employee->name,
                'position' => $positions->get($employee->position_id) ?? 'N/A',
                'group' => $employee->group_id ? ($groups->get($employee->group_id) ?? 'N/A') : 'N/A',
                'group_id' => $employee->group_id,
                'balance' => (float) $employee->balance,
                'payment_type' => $employee->payment_type,
                'salary' => (float) $employee->salary,
                'status' => $employee->status,
                'attendance_salary' => $attendanceTotal,
                'attendance_days' => $attendanceDays,
                'tarification_salary' => $tarificationTotal,
                'total_earned' => $totalEarned,
                'paid_amounts' => $paidAmountsByType,
                'total_paid' => round($paidTotal, 2),
                'net_balance' => round($totalEarned - $paidTotal, 2),
                'orders' => $orderIds->toArray(),
                'monthly_piecework' => $monthlyPieceworkData,
                'monthly_salary' => $monthlySalaryData,
            ];
        }

        // GROUPING: Employee'larni group bo'yicha guruhlash
        $groupedEmployees = collect($processedEmployees)->groupBy('group_id');

        $result = [];

        foreach ($groupedEmployees as $groupId => $groupEmployees) {
            $groupName = $groupId ? ($groups->get($groupId) ?? 'N/A') : 'Guruhsiz';
            $groupTotal = $groupEmployees->sum('total_earned');

            // group_id ni employee ma'lumotlaridan olib tashlaymiz
            $cleanEmployees = $groupEmployees->map(function ($emp) {
                unset($emp['group_id']);
                return $emp;
            })->values()->toArray();

            $result[] = [
                'id' => $groupId,
                'name' => $groupName,
                'total_balance' => $groupTotal,
                'employees' => $cleanEmployees,
            ];
        }

        return response()->json(array_values($result));
    }
    //2-usul excel

    public function exportGroupsOrdersEarnings(Request $request)
    {
        $data = $this->getGroupsOrdersEarnings($request)->getData(true);

        $department = DB::table('departments')
            ->select('name')
            ->where('id', $request->input('department_id'))
            ->first();
        $group = DB::table('groups')
            ->select('name')
            ->where('id', $request->input('group_id'))
            ->first();
        $month = $request->input('month', date('Y-m'));

        $departmentName = $department->name ?? '';
        $groupName = $group->name ?? '';

        $fileName = 'groups_orders_earnings_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(
            new GroupsOrdersEarningsExport($data, $departmentName, $groupName, $month),
            $fileName
        );
    }

    /**
     * $employee — Employee eloquent modeli (salaryPayments eager-load qilingan bo‘lishi mumkin)
     * $startDate, $endDate — 'Y-m-d' formatdagi string (yoki null)
     */

    public function getGroupsOrdersEarnings3(Request $request): \Illuminate\Http\JsonResponse
    {
        $departmentId = $request->input('department_id');
        $branchId = auth()->user()->employee->branch_id ?? null;
        $groupId = $request->input('group_id');
        $type = $request->input('type');
        $month = $request->input('month', date('Y-m'));

        if (!$departmentId && !$branchId) {
            return response()->json(['message' => '❌ department_id yoki branch_id kiritilishi shart.'], 422);
        }

        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();

        // Employee Query
        $employeeQuery = Employee::select('id', 'name', 'position_id', 'group_id', 'salary', 'balance', 'payment_type', 'status', 'department_id');
        if ($departmentId) $employeeQuery->where('department_id', $departmentId);
        elseif ($branchId) $employeeQuery->whereHas('department', fn($q) => $q->where('branch_id', $branchId));
        if ($type === 'aup') $employeeQuery->where('type', 'aup');
        elseif ($type === 'simple') $employeeQuery->where('type', '!=', 'aup');

        $employees = $employeeQuery->get();
        $employeeIds = $employees->pluck('id')->toArray();
        if (empty($employeeIds)) return response()->json([]);

        // Attendance salaries grouped
        $attendanceData = DB::table('attendance_salary')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->select('employee_id', 'date', 'amount')
            ->get()
            ->groupBy('employee_id');

        // Group changes
        $groupChanges = DB::table('group_changes')
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('employee_id');

        $monthlyPieceworksData = DB::table('employee_monthly_pieceworks as emp')
            ->leftJoin('users as u', 'emp.created_by', '=', 'u.id')
            ->leftJoin('employees as e', 'emp.employee_id', '=', 'e.id')
            ->whereIn('emp.employee_id', $employeeIds)
            ->where('emp.month', $monthDate)
            ->select('emp.id', 'emp.employee_id', 'emp.amount', 'emp.comment', 'emp.status', 'e.name as created_by_name')
            ->get()
            ->keyBy('employee_id');

        $monthlySalariesData = DB::table('employee_monthly_salaries as ems')
            ->leftJoin('users as u', 'ems.created_by', '=', 'u.id')
            ->leftJoin('employees as e', 'ems.employee_id', '=', 'e.id')
            ->whereIn('ems.employee_id', $employeeIds)
            ->where('ems.month', $monthDate)
            ->select('ems.id', 'ems.employee_id', 'ems.comment', 'ems.amount', 'ems.status', 'e.name as created_by_name')
            ->get()
            ->keyBy('employee_id');

        // --- To'lovlar (salary_payments) - date bilan olish
        $payments = DB::table('salary_payments')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->select('employee_id', 'amount', 'type', 'date', 'comment', 'month')
            ->get()
            ->groupBy('employee_id');

        // Tarification logs
        $addOrderIds = MonthlySelectedOrder::where('month', $monthDate)->pluck('order_id')->toArray();
        $minusOrderIds = Order::pluck('id')->diff($addOrderIds)->toArray();

        $tarificationData = DB::table('employee_tarification_logs as etl')
            ->join('tarifications as t', 'etl.tarification_id', '=', 't.id')
            ->join('tarification_categories as tc', 't.tarification_category_id', '=', 'tc.id')
            ->join('order_sub_models as sm', 'tc.submodel_id', '=', 'sm.id')
            ->join('order_groups as og', 'sm.id', '=', 'og.submodel_id')
            ->join('order_models as om', 'sm.order_model_id', '=', 'om.id')
            ->join('orders as o', 'om.order_id', '=', 'o.id')
            ->join('groups as g', 'og.group_id', '=', 'g.id')
            ->whereIn('etl.employee_id', $employeeIds)
            ->whereNotIn('o.id', $minusOrderIds)
            ->select('etl.employee_id', 'etl.amount_earned', 'etl.created_at', 'g.id as real_group_id')
            ->get()
            ->groupBy('employee_id');

        $groups = DB::table('groups')->pluck('name', 'id');
        $positions = DB::table('positions')->pluck('name', 'id');

        $processed = [];

        foreach ($employees as $employee) {
            $empDataPerGroup = [];
            $empAttendance = $attendanceData[$employee->id] ?? [];
            $empGroupChanges = $groupChanges[$employee->id] ?? collect();
            $defaultGroupId = $employee->group_id;

            // Attendance per day -> assign to real group by group_changes
            foreach ($empAttendance as $day) {
                $realGroupId = $defaultGroupId;
                $dayDate = Carbon::parse($day->date)->startOfDay();

                foreach ($empGroupChanges as $change) {
                    $changeDate = Carbon::parse($change->created_at)->startOfDay();
                    if ($changeDate > $dayDate) {
                        $realGroupId = $change->old_group_id;
                    } else {
                        break;
                    }
                }

                if ($groupId && $realGroupId != $groupId) continue;

                if (!isset($empDataPerGroup[$realGroupId])) {
                    $empDataPerGroup[$realGroupId] = [
                        'attendance_salary' => 0,
                        'attendance_days' => 0,
                        'tarification_salary' => 0
                    ];
                }
                $empDataPerGroup[$realGroupId]['attendance_salary'] += $day->amount;
                $empDataPerGroup[$realGroupId]['attendance_days']++;
            }

            // Tarification per log -> assign to real group by group_changes
            $tlGroups = $tarificationData[$employee->id] ?? collect();
            foreach ($tlGroups as $tl) {
                $realGroupId = $defaultGroupId;
                $tlDate = Carbon::parse($tl->created_at ?? $monthDate)->startOfDay();

                foreach ($empGroupChanges as $change) {
                    $changeDate = Carbon::parse($change->created_at)->startOfDay();
                    if ($changeDate <= $tlDate) {
                        $realGroupId = $change->new_group_id;
                    }
                }

                if (!empty($groupId) && $realGroupId != $groupId) continue;

                if (!isset($empDataPerGroup[$realGroupId])) {
                    $empDataPerGroup[$realGroupId] = [
                        'attendance_salary' => 0,
                        'attendance_days' => 0,
                        'tarification_salary' => 0
                    ];
                }
                $empDataPerGroup[$realGroupId]['tarification_salary'] += $tl->amount_earned;
            }

            // ✅ Endi payments ham guruh bo'yicha ajratiladi
            $paymentsList = $payments[$employee->id] ?? collect();
            $paymentsPerGroup = [];

            foreach ($paymentsList as $pmt) {
                $realGroupId = $defaultGroupId;
                $pmtDate = Carbon::parse($pmt->date)->startOfDay();

                // Shu to'lov qaysi guruhda ishlayotgan paytga tegishli ekanligini topish
                foreach ($empGroupChanges as $change) {
                    $changeDate = Carbon::parse($change->created_at)->startOfDay();
                    if ($changeDate > $pmtDate) {
                        $realGroupId = $change->old_group_id;
                    } else {
                        break;
                    }
                }

                if (!isset($paymentsPerGroup[$realGroupId])) {
                    $paymentsPerGroup[$realGroupId] = collect();
                }
                $paymentsPerGroup[$realGroupId]->push($pmt);
            }

            // Build per-group rows for employee
            foreach ($empDataPerGroup as $gid => $row) {
                // monthly piecework & salary
                $mp = $monthlyPieceworksData[$employee->id] ?? null;
                $monthlyPieceworkData = $mp ? [
                    'id' => $mp->id,
                    'amount' => (float)$mp->amount,
                    'status' => (bool)$mp->status,
                    'created_by' => $mp->created_by_name,
                    'comment' => $mp->comment,
                ] : null;

                $ms = $monthlySalariesData[$employee->id] ?? null;
                $monthlySalaryData = $ms ? [
                    'id' => $ms->id,
                    'amount' => (float)$ms->amount,
                    'status' => (bool)$ms->status,
                    'created_by' => $ms->created_by_name,
                    'comment' => $ms->comment,
                ] : null;

                // ✅ Payments faqat shu guruhga tegishli
                $groupPayments = $paymentsPerGroup[$gid] ?? collect();
                $paidAmountsByType = [];
                $paidTotal = 0.0;

                foreach ($groupPayments->groupBy('type') as $ptype => $pays) {
                    $paidAmountsByType[$ptype] = $pays->map(function ($pmt) use (&$paidTotal) {
                        $paidTotal += (float)$pmt->amount;
                        return [
                            'amount' => (float)$pmt->amount,
                            'date' => $pmt->date,
                            'comment' => $pmt->comment,
                            'month' => $pmt->month ? Carbon::parse($pmt->month)->format('Y-m') : null,
                        ];
                    })->values()->toArray();
                }

                $totalEarned = ($row['attendance_salary'] ?? 0) + ($row['tarification_salary'] ?? 0);

                $processed[$gid][] = [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'position' => $positions[$employee->position_id] ?? 'N/A',
                    'group' => $groups[$gid] ?? 'N/A',
                    'group_id' => $gid,
                    'attendance_salary' => $row['attendance_salary'] ?? 0,
                    'attendance_days' => $row['attendance_days'] ?? 0,
                    'tarification_salary' => $row['tarification_salary'] ?? 0,
                    'total_earned' => round($totalEarned, 2),
                    'paid_amounts' => $paidAmountsByType,
                    'total_paid' => round($paidTotal, 2),
                    'net_balance' => round($totalEarned - $paidTotal, 2),
                    'monthly_piecework' => $monthlyPieceworkData,
                    'monthly_salary' => $monthlySalaryData,
                ];
            }
        }

        $response = [];
        foreach ($processed as $gid => $list) {
            $response[] = [
                'id' => $gid,
                'name' => $groups[$gid] ?? 'Guruhsizlik',
                'total_balance' => collect($list)->sum('total_earned'),
                'employees' => array_values($list)
            ];
        }

        return response()->json(array_values($response));
    }

    public function getEmployeeEarnings($employee, $startDate, $endDate, $orderIds = [])
    {
        if ($employee->status === 'kicked' && ((float) $employee->balance) === 0.0) {
            return null;
        }

        $employee->loadMissing(['position', 'branch', 'group']);

        // AttendanceSalary (oylikchilar uchun)
        $attendanceQuery = $employee->attendanceSalaries();
        if ($startDate && $endDate) {
            $attendanceQuery->whereBetween('date', [$startDate, $endDate]);
        }
        $attendanceTotal = $attendanceQuery->sum('amount');
        $attendanceDays = $attendanceQuery->count();

        // EmployeeSalary
        $employeeSalaryQuery = $employee->employeeSalaries();
        if ($startDate && $endDate) {
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);

            $employeeSalaryQuery->where(function ($q) use ($start, $end) {
                $q->whereBetween('year', [$start->year, $end->year])
                    ->where(function ($q) use ($start, $end) {
                        $q->where(function ($q) use ($start) {
                            $q->where('year', $start->year)
                                ->where('month', '>=', $start->month);
                        })
                            ->orWhere(function ($q) use ($end) {
                                $q->where('year', $end->year)
                                    ->where('month', '<=', $end->month);
                            })
                            ->orWhere(function ($q) use ($start, $end) {
                                $q->whereBetween('year', [$start->year + 1, $end->year - 1]);
                            });
                    });
            });
        }
        $employeeSalaryTotal = $employeeSalaryQuery->sum('amount');

        // TarificationLogs (piece_work uchun)
        $tarificationQuery = $employee->employeeTarificationLogs();

        if (!empty($orderIds)) {
            $tarificationIds = \App\Models\Order::whereIn('id', $orderIds)
                ->with('orderModel.submodels.tarificationCategories.tarifications:id,tarification_category_id')
                ->get()
                ->flatMap(function ($order) {
                    return optional($order->orderModel)->submodels?->flatMap(function ($submodel) {
                        return optional($submodel->tarificationCategories)->flatMap(function ($category) {
                            return optional($category->tarifications)->pluck('id');
                        }) ?? collect();
                    }) ?? collect();
                })
                ->unique()
                ->values();

            if ($tarificationIds->isNotEmpty()) {
                $tarificationQuery->whereIn('tarification_id', $tarificationIds);
            } else {
                return null;
            }
        } elseif ($startDate && $endDate) {
            $tarificationQuery->whereBetween('date', [$startDate, $endDate]);
        }

        $tarificationTotal = $tarificationQuery->sum('amount_earned');

        // Umumiy hisob
        if ($employee->payment_type === 'piece_work') {
            $totalEarned = $employeeSalaryTotal + $tarificationTotal;
        } else {
            $totalEarned = $attendanceTotal + $employeeSalaryTotal;
        }

        // To'lovlar
        $paidQuery = $employee->salaryPayments();
        if ($startDate && $endDate) {
            $paidQuery->whereBetween('month', [$startDate, $endDate]);
        }
        $paymentsGrouped = $paidQuery->get()->groupBy('type');

        $paidAmountsByType = [];
        $paidTotal = 0;

        foreach ($paymentsGrouped as $type => $payments) {
            $paidAmountsByType[$type] = $payments->map(function ($payment) use (&$paidTotal) {
                $paidTotal += (float) $payment->amount;
                return [
                    'amount' => (float) $payment->amount,
                    'date' => $payment->date,
                    'comment' => $payment->comment,
                    'month' => $payment->month->format('Y-m'),
                ];
            })->values();
        }

        if ($totalEarned === 0){
            return null;
        }

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'position' => $employee->position->name ?? 'N/A',
            'group' => optional($employee->group)->name ?? 'N/A',
            'balance' => (float) $employee->balance,
            'payment_type' => $employee->payment_type,
            'salary' => (float) $employee->salary,

            'attendance_salary' => $attendanceTotal,
            'attendance_days' => $attendanceDays,
            'employee_salary' => $employeeSalaryTotal,
            'tarification_salary' => $tarificationTotal,
            'total_earned' => $totalEarned,

            'paid_amounts' => $paidAmountsByType,
            'total_paid' => round($paidTotal, 2),
            'net_balance' => round($totalEarned - $paidTotal, 2),
        ];
    }

    public function getSource(): \Illuminate\Http\JsonResponse
    {
        $sources = \App\Models\IncomeSource::all();
        return response()->json($sources);
    }

    public function getVia(): \Illuminate\Http\JsonResponse
    {
        $via = \App\Models\IncomeVia::all();
        return response()->json($via);
    }

    public function getDestination(): \Illuminate\Http\JsonResponse
    {
        $destinations = \App\Models\IncomeDestination::all();
        return response()->json($destinations);
    }

    public function storeIncome(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'source' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'date' => 'nullable|date',
            'purpose' => 'nullable|string|max:1000',
        ]);

        try {
            $data['type'] = 'income';
            $data['date'] = $data['date'] ?? now()->toDateString();
            $data['branch_id'] = auth()->user()->employee->branch_id;
            $data['via_id'] = auth()->user()->employee->id;

            DB::transaction(function () use (&$data) {
                $cashbox = \App\Models\Cashbox::firstOrCreate(
                    ['branch_id' => $data['branch_id']],
                    ['name' => 'Avto Cashbox: ' . now()->format('Y-m-d H:i:s')]
                );

                $data['cashbox_id'] = $cashbox->id;

                \App\Models\CashboxTransaction::create($data);

                $balance = \App\Models\CashboxBalance::firstOrCreate(
                    [
                        'cashbox_id' => $cashbox->id,
                        'currency_id' => $data['currency_id'],
                    ],
                    ['amount' => 0]
                );

                $balance->increment('amount', $data['amount']);

                // ✅ TUZATISH: Yangilangan balansni refresh qilamiz
                $balance->refresh();

                // ✅ Yoki yanada ishonchliroq: ID orqali qayta yuklaymiz
                $updatedBalance = \App\Models\CashboxBalance::find($balance->id);

                DB::afterCommit(function () use ($data, $updatedBalance) {
                    $currency = \App\Models\Currency::find($data['currency_id']);
                    $branch = \App\Models\Branch::find($data['branch_id']);
                    $user = auth()->user()->employee;

                    // ✅ Endi to'g'ri yangilangan balans
                    $remainingBalance = number_format($updatedBalance->amount, 0, '.', ' ');

                    $text = "💰 *Kirim qo'shildi!*\n"
                        . "🏢 Filial: {$branch->name}\n"
                        . "👤 Xodim: {$user->name}\n"
                        . "💵 Miqdor: " . number_format($data['amount'], 0, '.', ' ') . " {$currency->name}\n"
                        . "📅 Sana: {$data['date']}\n"
                        . "🏦 Qolgan balans: *{$remainingBalance} {$currency->name}*\n"
                        . "📘 Maqsad: " . ($data['purpose'] ?? "Noma'lum") . "\n"
                    . "💬 Izoh: " . ($data['comment'] ?? "-");

                $botToken = "7778276162:AAHVKgbh5mJlgp7jMhw_VNunvvR3qoDyjms";
                $chatId = -979504247;

                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]);
            });
        });

    } catch (\Exception $e) {
        return response()->json([
            'message' => '❌ Kirim muvaffaqiyatsiz. ' . $e->getMessage(),
        ], 500);
    }

    return response()->json(['message' => "✅ Kirim muvaffaqiyatli qo'shildi."]);
}

    public function storeExpense(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'purpose' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000',
            'date' => 'nullable|date',
        ]);

        $data['type'] = 'expense';
        $data['date'] = $data['date'] ?? now()->toDateString();
        $data['branch_id'] = auth()->user()->employee->branch_id;

        try {
            DB::transaction(function () use (&$data) {
                $cashbox = \App\Models\Cashbox::firstOrCreate(
                    ['branch_id' => $data['branch_id']],
                    ['name' => 'Avto Cashbox: ' . now()->format('Y-m-d H:i:s')]
                );

                $data['cashbox_id'] = $cashbox->id;
                $data['via_id'] = auth()->user()->employee->id;

                $balance = \App\Models\CashboxBalance::firstOrCreate(
                    [
                        'cashbox_id' => $cashbox->id,
                        'currency_id' => $data['currency_id'],
                    ],
                    ['amount' => 0]
                );

                if ($balance->amount < $data['amount']) {
                    throw new \Exception('❌ Kassada yetarli mablag\' mavjud emas.');
                }

                \App\Models\CashboxTransaction::create($data);
                $balance->decrement('amount', $data['amount']);

                // ✅ TUZATISH: Yangilangan balansni olish
                $updatedBalance = \App\Models\CashboxBalance::find($balance->id);

                DB::afterCommit(function () use ($data, $updatedBalance) {
                    $currency = \App\Models\Currency::find($data['currency_id']);
                    $branch = \App\Models\Branch::find($data['branch_id']);
                    $user = auth()->user()->employee;

                    // ✅ To'g'ri yangilangan balans
                    $remainingBalance = number_format($updatedBalance->amount, 0, '.', ' ');

                    $text = "📤 *Chiqim amalga oshirildi!*\n"
                        . "🏢 Filial: {$branch->name}\n"
                        . "👤 Xodim: {$user->name}\n"
                        . "💸 Miqdor: " . number_format($data['amount'], 0, '.', ' ') . " {$currency->name}\n"
                        . "📅 Sana: {$data['date']}\n"
                        . "🏦 Qolgan balans: *{$remainingBalance} {$currency->name}*\n"
                        . "📘 Maqsad: " . ($data['purpose'] ?? "Noma'lum") . "\n"
                        . "💬 Izoh: " . ($data['comment'] ?? '-');

                    $botToken = "7778276162:AAHVKgbh5mJlgp7jMhw_VNunvvR3qoDyjms";
                    $chatId = -979504247;

                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $text,
                        'parse_mode' => 'Markdown',
                    ]);
                });
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => '❌ Chiqim muvaffaqiyatsiz. ' . $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => '✅ Chiqim muvaffaqiyatli yozildi.']);
    }

    public function getBalances(): \Illuminate\Http\JsonResponse
    {
        $cashboxes = Cashbox::with(['balances.currency'])
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->get();

        return response()->json([
            'cashboxes' => $cashboxes->map(function ($cashbox) {
                return [
                    'id' => $cashbox->id,
                    'name' => $cashbox->name,
                    'balance' => $cashbox->balances->map(function ($balance) {
                        return [
                            'currency' => $balance->currency->name,
                            'amount' => number_format($balance->amount, 2, '.', ' '),
                        ];
                    })
                ];
            })
        ]);
    }

    public function getTransactions(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = CashboxTransaction::with('currency', 'cashbox')
            ->where('branch_id', auth()->user()->employee->branch_id);

        if ($request->filled('cashbox_id')) {
            $query->where('cashbox_id', $request->cashbox_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('search')) {
            $search = mb_strtolower($request->search); // PHP tomonda lowercase qilish
            $query->where(function ($q) use ($search) {
                $q->orWhereHas('via', function ($q3) use ($search) {
                        $q3->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('destination', function ($q4) use ($search) {
                        $q4->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereRaw('LOWER(purpose) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(source) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(comment) LIKE ?', ["%{$search}%"]);
            });
        }

        $transactions = $query->orderBy('date', 'desc')->get();

        return response()->json([
            'transactions' => $transactions->map(function ($tx) {
                return [
                    'cashbox' => $tx->cashbox,
                    'type' => $tx->type,
                    'amount' => $tx->amount,
                    'currency' => $tx->currency,
                    'date' => $tx->date,
                    'source' => $tx->source,
                    'destination' => $tx->destination,
                    'via' => $tx->via,
                    'purpose' => $tx->purpose,
                    'comment' => $tx->comment,
                    'created_at' => $tx->created_at->format('Y-m-d H:i:s'),
                ];
            })
        ]);
    }

    public function exportTransactions1(Request $request)
    {
        $fileName = 'transactions_' . now()->format('Y-m-d_His') . '.xlsx';
        return Excel::download(new TransactionsExport($request), $fileName);
    }

    public function transferBetweenCashboxes(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $data = $request->validate([
                'from_currency_id' => 'required|exists:currencies,id',
                'to_currency_id' => 'required|exists:currencies,id|different:from_currency_id',
                'from_amount' => 'required|numeric|min:0.01',
                'to_amount' => 'required|numeric|min:0.01',
                'exchange_rate' => 'required|numeric|min:0.0000001',
                'date' => 'nullable|date',
                'comment' => 'nullable|string|max:1000',
            ]);

            $data['date'] = $data['date'] ?? now()->toDateString();

            $branchId = auth()->user()->employee->branch_id;

            // Har ikkala kassani bir xil filialdan olish
            $cashbox = Cashbox::where('branch_id', $branchId)->firstOrFail();

            // Balansni tekshirish
            $fromBalance = CashboxBalance::where('cashbox_id', $cashbox->id)
                ->where('currency_id', $data['from_currency_id'])
                ->value('amount');

            if ($fromBalance === null || $fromBalance < $data['from_amount']) {
                return response()->json([
                    'message' => '❌ Kassada yetarli mablag‘ mavjud emas.'
                ], 422);
            }

            DB::transaction(function () use ($data, $cashbox) {
                // Chiqim
                CashboxTransaction::create([
                    'cashbox_id' => $cashbox->id,
                    'type' => 'expense',
                    'currency_id' => $data['from_currency_id'],
                    'amount' => $data['from_amount'],
                    'target_cashbox_id' => $cashbox->id,
                    'exchange_rate' => $data['exchange_rate'],
                    'target_amount' => $data['to_amount'],
                    'date' => $data['date'],
                    'comment' => '🔁 Valyuta ayirboshlash',
                    'branch_id' => $cashbox->branch_id,
                    'via_id' => auth()->user()->employee->id, // Hozirgi foydalanuvchi
                    'purpose' => $data['comment'] ?? null,
                ]);

                // Kirim
                CashboxTransaction::create([
                    'cashbox_id' => $cashbox->id,
                    'type' => 'income',
                    'currency_id' => $data['to_currency_id'],
                    'amount' => $data['to_amount'],
                    'target_cashbox_id' => $cashbox->id,
                    'exchange_rate' => $data['exchange_rate'],
                    'target_amount' => $data['from_amount'],
                    'date' => $data['date'],
                    'comment' => '🔁 Valyuta ayirboshlash',
                    'branch_id' => $cashbox->branch_id,
                    'via_id' => auth()->user()->employee->id, // Hozirgi foydalanuvchi
                    'purpose' => $data['comment'] ?? null,
                ]);

                // From balans kamayadi
                CashboxBalance::where('cashbox_id', $cashbox->id)
                    ->where('currency_id', $data['from_currency_id'])
                    ->decrement('amount', $data['from_amount']);

                // To balans oshadi
                CashboxBalance::updateOrCreate(
                    [
                        'cashbox_id' => $cashbox->id,
                        'currency_id' => $data['to_currency_id'],
                    ],
                    [
                        'amount' => DB::raw("amount + {$data['to_amount']}")
                    ]
                );
            });

            return response()->json([
                'message' => "✅ Pul muvaffaqiyatli ayirboshlanib saqlandi:\n{$data['from_amount']} → {$data['to_amount']}"
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => '❌ Xatolik yuz berdi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeRequestForm(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'purpose' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000',
            'deadline' => 'required|date|after_or_equal:today',
        ]);

        try {
            $requestForm = \App\Models\RequestForm::create([
                'employee_id' => $validated['employee_id'],
                'currency_id' => $validated['currency_id'],
                'amount' => $validated['amount'],
                'purpose' => $validated['purpose'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'status' => 'pending',
                'created_by' => auth()->id(),
                'branch_id' => auth()->user()->employee->branch_id,
                'deadline' => $validated['deadline'],
            ]);

            return response()->json([
                'message' => '✅ Talabnoma muvaffaqiyatli yaratildi.',
                'request_id' => $requestForm->id
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => '❌ Talabnoma yaratishda xatolik yuz berdi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRequestForm(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = \App\Models\RequestForm::with('employee', 'currency', 'creator', 'approver')
            ->where('branch_id', auth()->user()->employee->branch_id);

        // 🔍 Search
        if ($request->filled('search')) {
            $search = mb_strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', fn($q2) => $q2->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]))
                    ->orWhereRaw('LOWER(purpose) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(comment) LIKE ?', ["%{$search}%"]);
            });
        }

        // 📅 Deadline sanasi bo‘yicha filter
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('deadline', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->whereDate('deadline', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->whereDate('deadline', '<=', $request->end_date);
        }

        // 📋 Status bo‘yicha filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requestForms = $query->orderBy('deadline', 'desc')->paginate(10);

        return response()->json([
            'data' => $requestForms->getCollection()->transform(function ($form) {
                return [
                    'id' => $form->id,
                    'employee' => $form->employee->name,
                    'currency' => $form->currency->name,
                    'amount' => number_format($form->amount, 2, '.', ' '),
                    'purpose' => $form->purpose,
                    'comment' => $form->comment,
                    'status' => $form->status,
                    'created_at' => $form->created_at->format('Y-m-d H:i:s'),
                    'created_by' => $form->creator->employee->name ?? 'N/A',
                    'approved_by' => $form->approver->employee->name ?? 'N/A',
                    'approved_at' => $form->approved_at ? $form->approved_at->format('Y-m-d H:i:s') : null,
                    'deadline' => $form->deadline,
                ];
            }),
            'current_page' => $requestForms->currentPage(),
            'last_page' => $requestForms->lastPage(),
            'per_page' => $requestForms->perPage(),
            'total' => $requestForms->total(),
            'from' => $requestForms->firstItem(),
            'to' => $requestForms->lastItem(),
        ]);
    }

    public function storeGroupPlan(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'quantity' => 'required|integer|min:0',
        ]);

        $groupPlan = \App\Models\GroupPlan::updateOrCreate(
            [
                'group_id' => $validated['group_id'],
                'month' => $validated['month'],
                'year' => $validated['year'],
            ],
            ['quantity' => $validated['quantity']]
        );

        return response()->json([
            'message' => '✅ Guruh rejasi muvaffaqiyatli saqlandi.',
            'data' => $groupPlan,
        ]);
    }

    public function getGroupPlans(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = \App\Models\GroupPlan::whereHas('group.department.mainDepartment', function ($q) {
            $q->where('branch_id', auth()->user()->employee->branch_id);
        });

        $query->whereHas('group.orders.order', function ($q){
            $q->whereIn('status', ['tailored', 'tailoring']);
        });

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->where('month', $request->month)
                ->where('year', $request->year)
                ->with('group.orders.order.orderModel.submodels.sewingOutPuts', function ($q) use ($request) {
                    $q->whereBetween('created_at', [
                        Carbon::create($request->year, $request->month, 1)->startOfMonth(),
                        Carbon::create($request->year, $request->month, 1)->endOfMonth()
                    ]);
                });
        }

        $query->with('group.orders.order.orderModel.submodels');

        $plans = $query->get();

        return response()->json(GroupPlanResource::collection($plans));
    }

    public function editGroupPlan(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $groupPlan = \App\Models\GroupPlan::findOrFail($id);

        $validated = $request->validate([
            'group_id' => 'sometimes|exists:groups,id',
            'month' => 'sometimes|integer|min:1|max:12',
            'year' => 'sometimes|integer|min:2000|max:2100',
            'quantity' => 'sometimes|integer|min:0',
        ]);

        $groupPlan->update($validated);

        return response()->json([
            'message' => '✅ Guruh rejasi muvaffaqiyatli yangilandi.',
            'data' => $groupPlan,
        ]);
    }

    public function exportGroupsByDepartmentId(Request $request)
    {
        $departmentId = $request->input('department_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $group_id = $request->input('group_id');
        $orderIds = $request->input('order_ids', []);

        if (!$departmentId) {
            return response()->json(['message' => '❌ department_id kiritilmadi.'], 422);
        }

        return Excel::download(
            new DepartmentGroupsExport($departmentId, $startDate, $endDate, $group_id, $orderIds),
            'department_groups.xlsx'
        );
    }

    public function getLatestPurposes(Request $request): \Illuminate\Http\JsonResponse
    {
        $purposes = CashboxTransaction::where('type', $request->type)
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->whereNotNull('purpose')
            ->orderByDesc('created_at')
            ->pluck('purpose')
            ->unique()
            ->take(1000)
            ->values();

        return response()->json($purposes);
    }

    public function getLatestComments(Request $request): \Illuminate\Http\JsonResponse
    {
        $comments = CashboxTransaction::where('type', $request->type)
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->whereNotNull('comment')
            ->orderByDesc('created_at')
            ->pluck('comment')
            ->unique()
            ->take(1000)
            ->values();

        return response()->json($comments);
    }

    public function getLatestSources(Request $request): \Illuminate\Http\JsonResponse
    {
        $sources = CashboxTransaction::where('type', 'income')
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->whereNotNull('source')
            ->orderByDesc('created_at')
            ->pluck('source')
            ->unique()
            ->take(1000)
            ->values();

        return response()->json($sources);
    }

    public function exportTransactions(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
            'type'       => 'nullable|string',
        ]);

        return Excel::download(new CashboxTransactionsExport(
            auth()->user()->employee->branch_id,
            $request->start_date,
            $request->end_date,
            $request->type
        ), 'cashbox_transactions.xlsx');
    }

    /**
     * Recalculate daily payments for given year/month (optimized)
     */

    public function recalculateDailyPayments(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
        ]);

        ini_set('memory_limit', '3G');
        set_time_limit(0);

        $year = $data['year'];
        $month = $data['month'];
        $branchId = auth()->user()->employee->branch_id;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth()->toDateTimeString();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay()->toDateTimeString();

        $log = [
            'processed_cuts' => 0,
            'processed_sewings' => 0,
            'updated_payments' => 0,
            'created_payments' => 0,
            'skipped_no_attendance' => 0,
            'errors' => []
        ];

        DB::beginTransaction();
        try {
            // ===== 1) Preload departments & budgets =====
            $neededDeptNames = [
                "Sifat nazorati va qadoqlash bo'limi",
                "Texnik bo'lim",
                "Ho'jalik ishlari bo'limi",
                "Rejalashtirish va iqtisod bo'limi",
                "Ma'muriy boshqaruv"
            ];

            $departments = DB::table('departments as d')
                ->join('main_department as md', 'md.id', '=', 'd.main_department_id')
                ->where('md.branch_id', $branchId)
                ->whereIn('d.name', $neededDeptNames)
                ->select('d.id', 'd.name')
                ->get()
                ->keyBy('name');

            $budgets = DB::table('department_budgets')
                ->whereIn('department_id', $departments->pluck('id')->all())
                ->get()
                ->keyBy('department_id');

            // ===== 2) Preload employees =====
            $employees = DB::table('employees')
                ->where('branch_id', $branchId)
                ->where('status', 'working')
                ->select('id', 'department_id', 'percentage')
                ->get()
                ->groupBy('department_id');

            // ===== 3) Preload attendances =====
            $attendancesRaw = DB::table('attendance')
                ->join('employees as e', 'e.id', '=', 'attendance.employee_id')
                ->where('e.branch_id', $branchId)
                ->whereBetween('attendance.date', [Carbon::parse($startDate)->toDateString(), Carbon::parse($endDate)->toDateString()])
                ->select('attendance.employee_id', 'attendance.status', 'attendance.date', 'attendance.check_in')
                ->get();

            $attendances = [];
            foreach ($attendancesRaw as $a) {
                $d = $a->date;
                if (!isset($attendances[$d])) $attendances[$d] = [];
                $attendances[$d][$a->employee_id] = [
                    'status' => $a->status,
                    'check_in' => $a->check_in
                ];
            }
            unset($attendancesRaw);

            // ===== 4) Preload existing payments - YANGI STRUKTURA =====
            // Faqat date, order_id, employee_id bo'yicha unique
            $existingPaymentsRaw = DB::table('daily_payments')
                ->whereBetween('payment_date', [Carbon::parse($startDate)->toDateString(), Carbon::parse($endDate)->toDateString()])
                ->whereIn('order_id', function($q) use ($branchId) {
                    $q->select('o.id')->from('orders as o')->where('o.branch_id', $branchId);
                })
                ->select('*')
                ->get();

            // YANGI MAP: existingPayments[date][order_id][employee_id] => payment row
            $existingPayments = [];
            foreach ($existingPaymentsRaw as $p) {
                $d = $p->payment_date;
                $orderId = $p->order_id;
                $empId = $p->employee_id;

                // Agar bir employee uchun bir date va order bo'yicha bir nechta yozuv bo'lsa,
                // eng oxirgisini saqlash (yoki birinchisini - sizning logikangizga bog'liq)
                $existingPayments[$d][$orderId][$empId] = $p;
            }
            unset($existingPaymentsRaw);

            // ===== Helper function =====
            $wasEmployeeEligible = function(int $employeeId, string $date, Carbon $eventTime) use ($attendances) : bool {
                if (!isset($attendances[$date][$employeeId])) return false;
                $rec = $attendances[$date][$employeeId];

                if ($rec['status'] !== 'present') return false;
                if (empty($rec['check_in'])) return false;

                $checkInRaw = $rec['check_in'];

                try {
                    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $checkInRaw)) {
                        $arrival = Carbon::createFromFormat('Y-m-d H:i:s', $checkInRaw);
                    } else {
                        $arrival = Carbon::createFromFormat('H:i:s', $checkInRaw)
                            ->setDate($eventTime->year, $eventTime->month, $eventTime->day);
                    }
                } catch (\Throwable $e) {
                    Log::add(null, 'ARRIVAL PARSE ERROR', 'error', null, [
                        'check_in_raw' => $checkInRaw,
                        'message' => $e->getMessage(),
                    ]);
                    return false;
                }

                return $arrival->lessThanOrEqualTo($eventTime);
            };

            $toInsert = [];
            $toUpdate = [];

            // ===== 5) Process Sewing outputs =====
            DB::table('sewing_outputs as so')
                ->join('order_sub_models as osm', 'osm.id', '=', 'so.order_submodel_id')
                ->join('order_models as om', 'om.id', '=', 'osm.order_model_id')
                ->join('orders as o', 'o.id', '=', 'om.order_id')
                ->join('models as m', 'm.id', '=', 'om.model_id')
                ->whereBetween('so.created_at', [$startDate, $endDate])
                ->where('o.branch_id', $branchId)
                ->select(
                    'so.id as sewing_id',
                    'so.quantity as sewing_quantity',
                    'so.created_at as sewing_created_at',
                    'om.model_id',
                    'm.minute as model_minute',
                    'o.id as order_id',
                    'o.price as order_usd_price',
                    'o.quantity as order_quantity'
                )
                ->orderBy('so.id')
                ->chunk(300, function($sews) use (
                    &$log, $departments, $budgets, $employees, $attendances, $wasEmployeeEligible,
                    &$existingPayments, &$toInsert, &$toUpdate, $branchId
                ) {
                    foreach ($sews as $s) {
                        try {
                            $sewDate = Carbon::parse($s->sewing_created_at)->toDateString();
                            $sewTime = Carbon::parse($s->sewing_created_at);
                            $orderId = $s->order_id;
                            $newQty = (int)$s->sewing_quantity;
                            $modelMinute = (float)($s->model_minute ?? 0);

                            $sewingDeptNames = [
                                "Sifat nazorati va qadoqlash bo'limi",
                                "Texnik bo'lim",
                                "Ho'jalik ishlari bo'limi",
                                "Rejalashtirish va iqtisod bo'limi",
                                "Ma'muriy boshqaruv"
                            ];

                            foreach ($sewingDeptNames as $dname) {
                                if (!isset($departments[$dname])) continue;
                                $dept = $departments[$dname];
                                $deptBudget = $budgets[$dept->id] ?? null;
                                if (!$deptBudget) continue;

                                // Calculate totalAmount
                                if ($deptBudget->type === 'minute_based' && $modelMinute > 0) {
                                    $totalAmount = $modelMinute * $newQty * $deptBudget->quantity;
                                } elseif ($deptBudget->type === 'percentage_based') {
                                    $usdRate = getUsdRate();
                                    $orderUsdPrice = $s->order_usd_price ?? 0;
                                    $orderUzsPrice = $orderUsdPrice * $usdRate;
                                    $percentage = $deptBudget->quantity ?? 0;
                                    $totalAmount = round(($orderUzsPrice * $percentage) / 100, 2);
                                } else {
                                    $totalAmount = $newQty * $deptBudget->quantity;
                                }

                                if ($totalAmount <= 0) continue;

                                $empList = $employees[$dept->id] ?? collect();
                                if (empty($empList)) {
                                    $log['skipped_no_attendance']++;
                                    continue;
                                }

                                foreach ($empList as $emp) {
                                    $empId = $emp->id;
                                    if (!$wasEmployeeEligible($empId, $sewDate, $sewTime)) continue;

                                    // MUHIM: Faqat date, order_id, employee_id bo'yicha existing topish
                                    $existing = $existingPayments[$sewDate][$orderId][$empId] ?? null;

                                    $percentage = $existing ? ($existing->employee_percentage ?? 0) : ($emp->percentage ?? 0);
                                    if ($percentage == 0) continue;

                                    $earned = round(($totalAmount * $percentage) / 100, 2);
                                    if ($earned <= 0) continue;

                                    if ($existing && isset($existing->id)) {
                                        // QO'SHISH: yangi quantity va amountni mavjudga qo'shamiz
                                        $newQuantity = $existing->quantity_produced + $newQty;
                                        $newAmount = $existing->calculated_amount + $earned;

                                        $toUpdate[] = [
                                            'id' => $existing->id,
                                            'data' => [
                                                'quantity_produced' => $newQuantity,
                                                'calculated_amount' => $newAmount,
                                                'employee_percentage' => $percentage,
                                                'updated_at' => now(),
                                            ]
                                        ];

                                        // Update qilgandan keyin memory-dagi existing-ni ham yangilash
                                        $existingPayments[$sewDate][$orderId][$empId]->quantity_produced = $newQuantity;
                                        $existingPayments[$sewDate][$orderId][$empId]->calculated_amount = $newAmount;

                                        $log['updated_payments']++;
                                    } else {
                                        // Mavjud yozuv bor-yo'qligini tekshirish (memory-da)
                                        $memoryExisting = $existingPayments[$sewDate][$orderId][$empId] ?? null;

                                        if ($memoryExisting && !isset($memoryExisting->id)) {
                                            // Bu yozuv shu chunk ichida yaratilgan, faqat memory-da yangilash
                                            $memoryExisting->quantity_produced += $newQty;
                                            $memoryExisting->calculated_amount += $earned;
                                            // toInsert massivida ham yangilash kerak
                                            foreach ($toInsert as &$insertItem) {
                                                if ($insertItem['employee_id'] == $empId &&
                                                    $insertItem['order_id'] == $orderId &&
                                                    $insertItem['payment_date'] == $sewDate) {
                                                    $insertItem['quantity_produced'] += $newQty;
                                                    $insertItem['calculated_amount'] += $earned;
                                                    break;
                                                }
                                            }
                                        } else {
                                            // Yangi yozuv yaratish
                                            $newPayment = [
                                                'employee_id' => $empId,
                                                'model_id' => $s->model_id,
                                                'order_id' => $orderId,
                                                'department_id' => $dept->id,
                                                'payment_date' => $sewDate,
                                                'quantity_produced' => $newQty,
                                                'calculated_amount' => $earned,
                                                'employee_percentage' => $percentage,
                                                'created_at' => now(),
                                                'updated_at' => now(),
                                            ];

                                            $toInsert[] = $newPayment;

                                            // Memory-ga ham qo'shish keyingi sewingOutput lar uchun
                                            if (!isset($existingPayments[$sewDate])) {
                                                $existingPayments[$sewDate] = [];
                                            }
                                            if (!isset($existingPayments[$sewDate][$orderId])) {
                                                $existingPayments[$sewDate][$orderId] = [];
                                            }
                                            $existingPayments[$sewDate][$orderId][$empId] = (object)$newPayment;

                                            $log['created_payments']++;
                                        }
                                    }
                                }
                            }

                            $log['processed_sewings']++;
                        } catch (\Throwable $e) {
                            $log['errors'][] = "Sewing ID {$s->sewing_id}: {$e->getMessage()}";
                        }
                    }

                    // Flush batch
                    if (!empty($toInsert)) {
                        DB::table('daily_payments')->insert($toInsert);
                        $toInsert = [];
                    }
                    if (!empty($toUpdate)) {
                        foreach ($toUpdate as $u) {
                            DB::table('daily_payments')->where('id', $u['id'])->update($u['data']);
                        }
                        $toUpdate = [];
                    }
                });

            DB::commit();

            return response()->json([
                'message' => "To'lovlar muvaffaqiyatli qayta hisoblandi",
                'summary' => $log
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'summary' => $log
            ], 500);
        }
    }

    public function recalculateDailyPaymentsCut(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
        ]);

        ini_set('memory_limit', '3G');
        set_time_limit(0);

        $year = $data['year'];
        $month = $data['month'];
        $branchId = auth()->user()->employee->branch_id;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth()->toDateTimeString();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay()->toDateTimeString();

        $log = [
            'processed_cuts' => 0,
            'updated_payments' => 0,
            'created_payments' => 0,
            'skipped_no_attendance' => 0,
            'errors' => []
        ];

        DB::beginTransaction();
        try {
            // ===== 1) Preload Kroy bo'limi va Markaziy ombor bo'limlari =====
            $neededDeptNames = [
                "Kroy bo'limi",
                "Markaziy ombor"
            ];

            $departments = DB::table('departments as d')
                ->join('main_department as md', 'md.id', '=', 'd.main_department_id')
                ->where('md.branch_id', $branchId)
                ->whereIn('d.name', $neededDeptNames)
                ->select('d.id', 'd.name')
                ->get()
                ->keyBy('name');

            $budgets = DB::table('department_budgets')
                ->whereIn('department_id', $departments->pluck('id')->all())
                ->get()
                ->keyBy('department_id');

            // ===== 2) Preload employees =====
            $employees = DB::table('employees')
                ->where('branch_id', $branchId)
                ->where('status', 'working')
                ->whereIn('department_id', $departments->pluck('id')->all())
                ->select('id', 'department_id', 'percentage')
                ->get()
                ->groupBy('department_id');

            // ===== 3) Preload attendances =====
            $attendancesRaw = DB::table('attendance')
                ->join('employees as e', 'e.id', '=', 'attendance.employee_id')
                ->where('e.branch_id', $branchId)
                ->whereBetween('attendance.date', [Carbon::parse($startDate)->toDateString(), Carbon::parse($endDate)->toDateString()])
                ->select('attendance.employee_id', 'attendance.status', 'attendance.date', 'attendance.check_in')
                ->get();

            $attendances = [];
            foreach ($attendancesRaw as $a) {
                $d = $a->date;
                if (!isset($attendances[$d])) $attendances[$d] = [];
                $attendances[$d][$a->employee_id] = [
                    'status' => $a->status,
                    'check_in' => $a->check_in
                ];
            }
            unset($attendancesRaw);

            $existingPaymentsRaw = DB::table('daily_payments')
                ->whereBetween('payment_date', [Carbon::parse($startDate)->toDateString(), Carbon::parse($endDate)->toDateString()])
                ->whereIn('order_id', function($q) use ($branchId) {
                    $q->select('id')->from('orders')->where('branch_id', $branchId);
                })
                ->select('*')
                ->get();

            $existingPayments = [];
            foreach ($existingPaymentsRaw as $p) {
                $d = $p->payment_date;
                $orderId = $p->order_id;
                $empId = $p->employee_id;
                $existingPayments[$d][$orderId][$empId] = $p;
            }
            unset($existingPaymentsRaw);

            // ===== 4) Helper function =====
            $wasEmployeeEligible = function(int $employeeId, string $date, Carbon $eventTime) use ($attendances) : bool {
                if (!isset($attendances[$date][$employeeId])) return false;
                $rec = $attendances[$date][$employeeId];
                if ($rec['status'] !== 'present') return false;
                if (empty($rec['check_in'])) return false;
                try {
                    $arrival = Carbon::parse($rec['check_in']);
                } catch (\Throwable $e) {
                    return false;
                }
                return $arrival->lessThanOrEqualTo($eventTime);
            };

            $toInsert = [];
            $toUpdate = [];

            // ===== 5) Process Order Cuts =====
            DB::table('order_cuts as oc')
                ->join('orders as o', 'o.id', '=', 'oc.order_id')
                ->join('order_models as om', 'om.order_id', '=', 'o.id')
                ->join('models as m', 'm.id', '=', 'om.model_id')
                ->whereBetween('oc.cut_at', [$startDate, $endDate])
                ->where('o.branch_id', $branchId)
                ->select(
                    'oc.id as cut_id',
                    'oc.quantity as cut_quantity',
                    'oc.cut_at',
                    'om.model_id',
                    'm.minute as model_minute',
                    'o.id as order_id',
                    'o.price as order_usd_price'
                )
                ->orderBy('oc.id')
                ->chunk(300, function($cuts) use (
                    &$log, $departments, $budgets, $employees, $attendances, $wasEmployeeEligible,
                    &$existingPayments, &$toInsert, &$toUpdate
                ) {
                    foreach ($cuts as $c) {
                        try {
                            $cutDate = Carbon::parse($c->cut_at)->toDateString();
                            $cutTime = Carbon::parse($c->cut_at);
                            $orderId = $c->order_id;
                            $quantity = (int)$c->cut_quantity;

                            foreach ($departments as $dept) {
                                $deptBudget = $budgets[$dept->id] ?? null;
                                if (!$deptBudget) continue;

                                // Minute-based hisoblash
                                if ($deptBudget->type === 'minute_based' && $c->model_minute > 0) {
                                    $totalAmount = $c->model_minute * $quantity * $deptBudget->quantity;
                                } elseif ($deptBudget->type === 'percentage_based') {
                                    $usdRate = getUsdRate();
                                    $orderUsdPrice = $c->order_usd_price ?? 0;
                                    $orderUzsPrice = $orderUsdPrice * $usdRate;
                                    $percentage = $deptBudget->quantity ?? 0;
                                    $totalAmount = round(($orderUzsPrice * $percentage) / 100, 2);
                                } else {
                                    $totalAmount = $quantity * $deptBudget->quantity;
                                }

                                if ($totalAmount <= 0) continue;

                                $empList = $employees[$dept->id] ?? collect();
                                if (empty($empList)) {
                                    $log['skipped_no_attendance']++;
                                    continue;
                                }

                                foreach ($empList as $emp) {
                                    $empId = $emp->id;
                                    if (!$wasEmployeeEligible($empId, $cutDate, $cutTime)) continue;

                                    $existing = $existingPayments[$cutDate][$orderId][$empId] ?? null;
                                    $percentage = $existing ? ($existing->employee_percentage ?? 0) : ($emp->percentage ?? 0);
                                    if ($percentage == 0) continue;

                                    $earned = round(($totalAmount * $percentage) / 100, 2);
                                    if ($earned <= 0) continue;

                                    if ($existing && isset($existing->id)) {
                                        $toUpdate[] = [
                                            'id' => $existing->id,
                                            'data' => [
                                                'quantity_produced' => $existing->quantity_produced + $quantity,
                                                'calculated_amount' => $existing->calculated_amount + $earned,
                                                'employee_percentage' => $percentage,
                                                'updated_at' => now(),
                                            ]
                                        ];
                                        $existingPayments[$cutDate][$orderId][$empId]->quantity_produced += $quantity;
                                        $existingPayments[$cutDate][$orderId][$empId]->calculated_amount += $earned;
                                        $log['updated_payments']++;
                                    } else {
                                        $newPayment = [
                                            'employee_id' => $empId,
                                            'model_id' => $c->model_id,
                                            'order_id' => $orderId,
                                            'department_id' => $dept->id,
                                            'payment_date' => $cutDate,
                                            'quantity_produced' => $quantity,
                                            'calculated_amount' => $earned,
                                            'employee_percentage' => $percentage,
                                            'created_at' => now(),
                                            'updated_at' => now(),
                                        ];
                                        $toInsert[] = $newPayment;
                                        $existingPayments[$cutDate][$orderId][$empId] = (object)$newPayment;
                                        $log['created_payments']++;
                                    }
                                }
                            }


                            $log['processed_cuts']++;
                        } catch (\Throwable $e) {
                            $log['errors'][] = "Cut ID {$c->cut_id}: {$e->getMessage()}";
                        }
                    }

                    if (!empty($toInsert)) {
                        DB::table('daily_payments')->insert($toInsert);
                        $toInsert = [];
                    }
                    if (!empty($toUpdate)) {
                        foreach ($toUpdate as $u) {
                            DB::table('daily_payments')->where('id', $u['id'])->update($u['data']);
                        }
                        $toUpdate = [];
                    }
                });

            DB::commit();

            return response()->json([
                'message' => "Cut bo'limi to'lovlari muvaffaqiyatli qayta hisoblandi",
                'summary' => $log
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'summary' => $log
            ], 500);
        }
    }

}

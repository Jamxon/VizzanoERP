<?php

namespace App\Http\Controllers;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\Group;
use App\Models\Order;
use App\Models\OrderSubModel;
use App\Models\Tarification;
use App\Models\TarificationCategory;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;


class InternalAccountantController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('branch_id', auth()->user()->employee->branch_id)
            ->whereIn('status', ['cutting','pending','tailoring', 'tailored', 'checking', 'checked', 'packaging', 'completed'])
            ->with(
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.submodels.submodel',
            )
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($orders);
    }

    public function showOrder(Order $order): \Illuminate\Http\JsonResponse
    {
        $order->load(
            'orderModel',
            'orderModel.model',
            'orderModel.material',
            'orderModel.submodels.submodel',
            'orderModel.submodels.submodelSpend',
            'orderModel.submodels.tarificationCategories',
            'orderModel.submodels.tarificationCategories.tarifications',
            'orderModel.submodels.tarificationCategories.tarifications.employee',
            'orderModel.submodels.tarificationCategories.tarifications.razryad',
            'orderModel.submodels.tarificationCategories.tarifications.typewriter',
        );

        return response()->json($order);
    }

    public function searchTarifications(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = $request->input('search');
        $tarifications = Tarification::where('name', 'like', "%$search%")
                ->orWhere('razryad_id', 'like', "%$search%")
                ->orWhere('typewriter_id', 'like', "%$search%")
                ->orWhere('code', 'like', "%$search%")
            ->with(
                'tarifications',
                'tarifications.employee',
                'tarifications.razryad',
                'tarifications.typewriter',
            )
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($tarifications);
    }

    public function generateDailyPlan(Request $request): \Illuminate\Http\Response
    {
        $request->validate([
            'submodel_id' => 'required|exists:order_sub_models,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        $submodel = OrderSubmodel::with([
            'tarificationCategories.tarifications.employee:id,name'
        ])->findOrFail($request->submodel_id);

        $group = Group::with('department')->findOrFail($request->group_id);
        $workStart = Carbon::parse($group->department->start_time);
        $workEnd = Carbon::parse($group->department->end_time);
        $breakTime = $group->department->break_time ?? 0;

        $totalWorkMinutes = $workEnd->diffInMinutes($workStart) - $breakTime;

        $plans = [];
        $tarifications = collect();

        foreach ($submodel->tarificationCategories as $category) {
            foreach ($category->tarifications as $tarification) {
                if ($tarification->employee) {
                    $tarifications->push([
                        'id' => $tarification->id,
                        'code' => $tarification->code,
                        'name' => $tarification->name,
                        'seconds' => $tarification->second,
                        'sum' => $tarification->summa,
                        'minutes' => round($tarification->second / 60, 4),
                        'employee_id' => $tarification->employee->id,
                        'employee_name' => $tarification->employee->name,
                    ]);
                }
            }
        }

        $grouped = $tarifications->groupBy('employee_id');
        $date = now()->format('Y-m-d');

        foreach ($grouped as $employeeId => $tasks) {
            $employeeName = $tasks->first()['employee_name'];
            $remainingMinutes = $totalWorkMinutes;
            $usedMinutes = 0;
            $totalEarned = 0;
            $assigned = [];

            foreach ($tasks->sortBy('minutes') as $task) {
                if ($remainingMinutes >= $task['minutes']) {
                    $count = 1;
                    $total_minutes = round($task['minutes'], 2);
                    $amount_earned = round($task['sum'], 2);

                    $assigned[] = [
                        'tarification_id' => $task['id'],
                        'tarification_name' => $task['name'],
                        'code' => $task['code'],
                        'count' => $count,
                        'minutes_per_unit' => $task['minutes'],
                        'total_minutes' => $total_minutes,
                        'sum' => $task['sum'],
                        'amount_earned' => $amount_earned,
                    ];

                    $usedMinutes += $task['minutes'];
                    $remainingMinutes -= $task['minutes'];
                    $totalEarned += $amount_earned;
                }
            }

            $i = 0;
            while ($remainingMinutes > 0 && count($assigned)) {
                $index = $i % count($assigned);
                $item = &$assigned[$index];
                $minutes = $item['minutes_per_unit'];

                if ($remainingMinutes >= $minutes) {
                    $item['count'] += 1;
                    $item['total_minutes'] = round($item['count'] * $minutes, 2);
                    $item['amount_earned'] = round($item['count'] * $item['sum'], 2);

                    $usedMinutes += $minutes;
                    $remainingMinutes -= $minutes;
                    $totalEarned += $item['sum'];
                } else break;

                $i++;
            }

            foreach ($assigned as &$item) {
                unset($item['minutes_per_unit']);
            }

            // 📌 Save plan to DB
            $plan = DailyPlan::create([
                'employee_id' => $employeeId,
                'submodel_id' => $submodel->id,
                'group_id' => $group->id,
                'date' => $date,
                'used_minutes' => $usedMinutes,
                'total_earned' => $totalEarned,
            ]);

            unset($item);
            foreach ($assigned as $item) {
                DailyPlanItem::create([
                    'daily_plan_id' => $plan->id,
                    'tarification_id' => $item['tarification_id'],
                    'count' => $item['count'],
                    'total_minutes' => $item['total_minutes'],
                    'amount_earned' => $item['amount_earned'],
                ]);
            }

            $plans[] = [
                'plan_id' => $plan->id,
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
                'used_minutes' => round($usedMinutes, 2),
                'total_earned' => round($totalEarned, 2),
                'tarifications' => $assigned,
                'date' => $date,
            ];
        }

        $pdf = Pdf::loadView('pdf.daily-plan-styled', [
            'plans' => $plans
        ])->setPaper([0, 0, 226.77, 566.93], 'portrait'); // 80mm x ~200mm

        return $pdf->download('daily_plan.pdf');
    }

}
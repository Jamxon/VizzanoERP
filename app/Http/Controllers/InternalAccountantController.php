<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Order;
use App\Models\OrderSubModel;
use App\Models\Tarification;
use App\Models\TarificationCategory;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
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

    public function generateDailyPlan(Request $request): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'submodel_id' => 'required|exists:order_sub_models,id',
        ]);

        $submodel = OrderSubmodel::with([
            'tarificationCategories.tarifications.employee' => fn($q) => $q->select('id', 'name'),
        ])->findOrFail($request->submodel_id);

        $tarifications = collect();

        foreach ($submodel->tarificationCategories as $category) {
            foreach ($category->tarifications as $tarification) {
                if ($tarification->employee) {
                    $tarifications->push([
                        'id' => $tarification->id,
                        'name' => $tarification->name,
                        'seconds' => $tarification->second,
                        'sum' => $tarification->summa,
                        'minutes' => round($tarification->second / 60, 4),
                        'assigned_employee_id' => $tarification->employee->id,
                        'assigned_employee_name' => $tarification->employee->name,
                    ]);
                }
            }
        }

        if ($tarifications->isEmpty()) {
            return back()->with('error', 'Tarifikatsiyalar yoki ularning xodimlari topilmadi');
        }

        $grouped = $tarifications->groupBy('assigned_employee_id');

        $employeePlans = [];

        foreach ($grouped as $employeeId => $tasks) {
            $employeeName = $tasks->first()['assigned_employee_name'];
            $remainingMinutes = 500;
            $usedMinutes = 0;
            $totalEarned = 0;
            $assigned = [];
            $sortedTasks = $tasks->sortBy('minutes')->values();

            foreach ($sortedTasks as $task) {
                if ($task['minutes'] > 0 && $remainingMinutes >= $task['minutes']) {
                    $count = 1;
                    $total_minutes = round($task['minutes'] * $count, 2);
                    $amount_earned = round($task['sum'] * $count, 2);

                    $assigned[] = [
                        'tarification_id' => $task['id'],
                        'tarification_name' => $task['name'],
                        'count' => $count,
                        'total_minutes' => $total_minutes,
                        'minutes_per_unit' => $task['minutes'],
                        'sum' => $task['sum'],
                        'amount_earned' => $amount_earned,
                    ];

                    $usedMinutes += $task['minutes'];
                    $remainingMinutes -= $task['minutes'];
                    $totalEarned += $amount_earned;
                }
            }

            $i = 0;
            while ($remainingMinutes > 0 && count($assigned) > 0) {
                $index = $i % count($assigned);
                $unit = $assigned[$index];
                $minutes = $unit['minutes_per_unit'];
                $sum = $unit['sum'];

                if ($remainingMinutes >= $minutes) {
                    $assigned[$index]['count'] += 1;
                    $assigned[$index]['total_minutes'] = round($assigned[$index]['count'] * $minutes, 2);
                    $assigned[$index]['amount_earned'] = round($assigned[$index]['count'] * $sum, 2);

                    $usedMinutes += $minutes;
                    $remainingMinutes -= $minutes;
                    $totalEarned += $sum;
                } else {
                    break;
                }

                $i++;
            }

            foreach ($assigned as &$item) {
                unset($item['minutes_per_unit']);
            }

            $employeePlans[] = [
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
                'used_minutes' => round($usedMinutes, 2),
                'total_earned' => round($totalEarned, 2),
                'tarifications' => $assigned,
            ];
        }

        $pdf = Pdf::loadView('pdf.daily-plan', [
            'plans' => $employeePlans
        ])->setPaper([0, 0, 226.77, 141.73], 'portrait'); // 80mm x 50mm in points

        return $pdf->download('daily_plan.pdf');
    }

}
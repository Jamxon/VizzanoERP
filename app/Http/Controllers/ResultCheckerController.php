<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetGroupsForResultCheckerResource;
use App\Models\EmployeeResult;
use App\Models\Group;
use App\Models\Log;
use App\Models\OrderGroup;
use App\Models\Tarification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class  ResultCheckerController extends Controller
{

    public function getGroups(Request $request): \Illuminate\Http\JsonResponse
    {
        $groups = Group::where('department_id', $request->input('department_id'))
            ->with([
                'responsibleUser',
                'orders.order',
                'orders.orderSubmodel.submodel',
                'orders.orderSubmodel.orderModel.model',
                'orders.orderSubmodel.sewingOutputs' => function ($q) {
                    $q->whereDate('created_at', now());
                },
            ])
            ->get();

        return response()->json(GetGroupsForResultCheckerResource::collection($groups));
    }

    public function getEmployeeByGroupId(Request $request): \Illuminate\Http\JsonResponse
    {
        $groupId = $request->input('group_id');

        // 1. Group orqali tegishli order_submodel_id larni topamiz
        $orderSubmodelIds = OrderGroup::where('group_id', $groupId)
            ->pluck('submodel_id');

        // 2. Shu order_submodel_id lar orqali tarification_id larni topamiz
        $tarificationIds = Tarification::whereHas('tarificationCategory', function ($q) use ($orderSubmodelIds) {
            $q->whereIn('submodel_id', $orderSubmodelIds);
        })->pluck('id');

        // 3. Group + employees + bugungi results (tarifications yuklanmaydi)
        $group = Group::with([
            'employees' => function ($q) {
                $q->where('status', '!=', 'kicked')
                    ->with([
                        'employeeResults' => function ($query) {
                            $query->whereDate('created_at', Carbon::today())
                                ->with(['time', 'tarification', 'createdBy.employee']);
                        }
                    ]);
            }
        ])->find($groupId);

        // Faqat kerakli tarification larni qo‘shamiz
        $employees = $group?->employees->map(function ($employee) use ($tarificationIds) {
            $filtered = $employee->tarifications()->whereIn('tarifications.id', $tarificationIds)->get();
            $employee->setRelation('tarifications', $filtered); // nomini o‘zgartirdik
            return $employee;
        });

        return response()->json($employees);
    }

    public function storeEmployeeResult(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required',
            'quantity' => 'required',
            'tarification_id' => 'required',
            'time_id' => 'required',
        ]);

        $data['created_by'] = auth()->id();

        EmployeeResult::create($data);

        Log::add(
            auth()->id(),
            "Hodim uchun statistika yozildi",
            "create",
            null,
            EmployeeResult::all()->last()->id
        );

        return response()->json(['message' => 'Employee Result stored']);
    }

    public function getDailyWorkStatistics(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->input('date') ?? now()->toDateString();

        $statistics = DB::table('employee_results')
            ->join('tarifications', 'employee_results.tarification_id', '=', 'tarifications.id')
            ->join('employees', 'employee_results.employee_id', '=', 'employees.id')
            ->leftJoin('groups', 'employees.group_id', '=', 'groups.id') // group qo‘shildi
            ->select(
                'employees.id as employee_id',
                'employees.name as employee_name',
                'employees.group_id',
                'groups.name as group_name',
                DB::raw('SUM(employee_results.quantity * tarifications.second) as total_seconds')
            )
            ->whereDate('employee_results.created_at', $date)
            ->groupBy('employees.id', 'employees.name', 'employees.group_id', 'groups.name')
            ->orderByDesc('total_seconds')
            ->get();

        return response()->json($statistics);
    }

}
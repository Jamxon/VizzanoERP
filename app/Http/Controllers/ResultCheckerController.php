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
                                ->with(['time', 'createdBy.employee']);
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
            'minute' => 'required',
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

    public function updateEmployeeResult(Request $request, EmployeeResult $employeeResult): \Illuminate\Http\JsonResponse
    {
        // Faqat yuborilgan maydonlargina tekshiriladi (PATCHga mos tarzda)
        $data = $request->validate([
            'employee_id' => 'sometimes|exists:employees,id',
            'quantity'    => 'sometimes|numeric',
            'minute'      => 'sometimes|numeric',
            'time_id'     => 'sometimes|exists:times,id',
        ]);

        // Auth ID ni qo‘shish (agar kerak bo‘lsa)
        $data['created_by'] = auth()->id();

        // PATCH — faqat mavjud maydonlarni yangilash
        $employeeResult->fill($data)->save();

        Log::add(
            auth()->id(),
            "EmployeeResult PATCH update",
            "update",
            $employeeResult->getOriginal(),  // eski holat
            $employeeResult->toArray()       // yangi holat
        );

        return response()->json(['message' => 'Employee Result successfully patched.']);
    }

    public function getDailyWorkStatistics(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->input('date') ?? now()->toDateString();

        $statistics = DB::table('employee_results')
            ->join('employees', 'employee_results.employee_id', '=', 'employees.id')
            ->leftJoin('groups', 'employees.group_id', '=', 'groups.id')
            ->select(
                'employees.id as employee_id',
                'employees.name as employee_name',
                'employees.group_id',
                'groups.name as group_name',
                DB::raw('SUM(employee_results.minute) as total_minutes'),
                DB::raw('ROUND(SUM(employee_results.minute) / 500 * 100, 2) as percentage')
            )
            ->whereDate('employee_results.created_at', $date)
            ->groupBy('employees.id', 'employees.name', 'employees.group_id', 'groups.name')
            ->orderByDesc('total_minutes')
            ->get();

        return response()->json($statistics);
    }

}
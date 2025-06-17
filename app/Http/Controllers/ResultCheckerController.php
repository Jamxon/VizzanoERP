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

        // 3. Group + employees + filtered tarifications + bugungi results
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

        // filtered_tarifications qo‘shib, tarifications ni yuklamaymiz
        $employees = $group?->employees->map(function ($employee) use ($tarificationIds) {
            $filtered = $employee->tarifications()->whereIn('tarifications.id', $tarificationIds)->get();
            $employee->setRelation('filtered_tarifications', $filtered); // Eloquent tarzida relation sifatida qo‘shamiz
            return $employee->makeHidden('tarifications'); // eski to‘liq `tarifications` ni yashiramiz
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
}
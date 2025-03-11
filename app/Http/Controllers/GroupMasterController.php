<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetOrderGroupMasterResource;
use App\Http\Resources\GetTarificationGroupMasterResource;
use App\Http\Resources\ShowOrderGroupMaster;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\OrderCut;
use App\Models\OrderGroup;
use App\Models\SewingOutputs;
use App\Models\Tarification;
use App\Models\Time;
use Illuminate\Http\Request;

class GroupMasterController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user->group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $status = $request->has('status') ? strtolower(trim($request->status)) : null;

        $query = OrderGroup::where('group_id', $user->group->id)
            ->whereHas('order', function ($q) use ($status) {
                if ($status) {
                    $q->where('status', $status);
                } else {
                    $q->whereIn('status', ['pending', 'cutting']);
                }
            })
            ->with([
                'order.orderModel',
                'order.orderModel.model',
                'order.orderModel.material',
                'order.orderModel.sizes.size',
                'order.instructions',

            ])
            ->selectRaw('DISTINCT ON (order_id, submodel_id) *');

        $orders = $query->get();

        $orders = $orders->groupBy('order_id')->map(function ($orderGroups) {
            $firstOrderGroup = $orderGroups->first();
            $order = $firstOrderGroup->order;

            if ($order && $order->orderModel) {
                $linkedSubmodelIds = $orderGroups->pluck('submodel_id')->unique();
                $order->orderModel->submodels = $order->orderModel->submodels
                    ->whereIn('id', $linkedSubmodelIds)
                    ->values();
            }

            return $firstOrderGroup;
        })->values();

        return response()->json(GetOrderGroupMasterResource::collection($orders));
    }

    public function showOrder($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::where('id', $id)
            ->with([
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.sizes.size',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group',
                'instructions',
                'orderModel.submodels.sewingOutputs'
            ])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $orderGroups = OrderGroup::where('order_id', $id)
            ->where('group_id', auth()->user()->group->id)
            ->get();

        if ($order->orderModel) {
            $linkedSubmodelIds = $orderGroups->pluck('submodel_id');

            $order->orderModel->submodels = $order->orderModel->submodels
                ->whereIn('id', $linkedSubmodelIds)
                ->values();
        }

        return response()->json(new ShowOrderGroupMaster($order));
    }

    public function getEmployees(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user->group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $employees = $user->group->employees()->get();

        return response()->json($employees);
    }

    public function getTarifications($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::where('id', $id)
            ->with([
                'orderModel.submodels.tarificationCategories'
            ])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $resource = new GetTarificationGroupMasterResource($order);

        return response()->json($resource);
    }

    public function startOrder($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::find($id);

        $order->update([
            'status' => 'tailoring'
        ]);

        return response()->json([
            'message' => "Order successful started",
            'order' => $order
        ]);
    }

    public function assignEmployeesToTarifications(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->input('data');

        foreach ($data as $item) {
            $tarificationId = $item['tarification_id'];
            $userId = $item['user_id'];

            $tarification = Tarification::find($tarificationId);

            $tarification->update([
                'user_id' => $userId
            ]);
        }

        return response()->json([
            'message' => 'Employees assigned to tarifications successfully'
        ]);
    }

    public function getTimes(): \Illuminate\Http\JsonResponse
    {

       $times = Time::all();

       return response()->json($times);
    }

    public function SewingOutputsStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $validatedData = $request->validate([
            'order_submodel_id' => 'required|exists:order_sub_models,id',
            'quantity' => 'required|integer',
            'time_id' => 'required|exists:times,id',
            'comment' => 'nullable|string'
        ]);

        SewingOutputs::create($validatedData);

        return response()->json([
            'message' => 'Sewing output created successfully'
        ]);
    }

    public function showOrderCuts(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderId = $request->order_id;
        $categoryId = $request->category_id;

        $order = Order::find($orderId)
            ->whereHas('orderCuts', function ($q) use ($categoryId) {
                $q->where('specification_category_id', $categoryId);
            })
            ->with(
                'orderCuts',
                'orderCuts.category',
                'orderCuts.user'
            )
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order cut not found'], 404);
        }

        return response()->json($order);
    }

    public function getOrderCuts(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        $orderCuts = OrderCut::where('user_id', $user->id)
            ->where('cut_at', now()->format('Y-m-d'))
            ->with(
                'category',
                'user',
                'order'
            )
            ->get();

        return response()->json($orderCuts);
    }

    public function receiveOrderCut($id): \Illuminate\Http\JsonResponse
    {
        $orderCut = OrderCut::find($id);

        if (!$orderCut) {
            return response()->json(['message' => 'Order cut not found'], 404);
        }

        $orderCut->update([
            'status' => true,
            'user_id' => auth()->user()->id,
        ]);

        return response()->json([
            'message' => 'Order cut received successfully',
            'order_cut' => $orderCut
        ]);
    }

    public function getPlans(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $group = $user->group;

//        $todayAttendanceCount = Attendance::whereDate('date', now()->format('Y-m-d'))
//            ->whereIn('employee_id', $group->employees()->pluck('id'))
//            ->count();

        $todayAttendanceCount = 50;

        $requiredAttendanceBudget = $todayAttendanceCount * 115000;

        $orderGroups = $group->orders()
            ->whereHas('order', fn($query) => $query->where('status', 'tailoring'))
            ->with(['order.orderModel'])
            ->get();

        //bir submodel bitishi uchun ketadigan vaqt
        $orderSubModelSpends = $orderGroups->map(fn($orderGroup) => [
            'spends' => $orderGroup->order->orderModel->submodels->flatMap(fn($submodel) =>
                $submodel->submodelSpend->pluck('seconds')
                )->sum() ?? 0,
        ]);

        //bir submodel bitishi uchun beriladigan summa
        $orderSubModelSumma = $orderGroups->map(fn($orderGroup) => [
            'summa' => $orderGroup->order->orderModel->submodels->flatMap(fn($submodel) =>
                $submodel->submodelSpend->pluck('summa')
                )->sum() ?? 0,
        ]);

        $totalSpends = $orderSubModelSpends->sum('spends');

        $todayPlan = $totalSpends > 0 ? ($todayAttendanceCount * 30000) / $totalSpends : 0;

        $orderCalculations = $orderGroups->map(fn($orderGroup) => [
            'expense' => $orderGroup->order->orderModel->rasxod ?? 0,
            'quantity' => $orderGroup->order->quantity ?? 0,
            'total_cost' => ($orderGroup->order->orderModel->rasxod ?? 0) * ($orderGroup->order->quantity ?? 0),
        ]);

        $totalProductionCost = $orderSubModelSumma * $orderCalculations['quantity'];

        $firstExpense = $orderCalculations->first()['expense'] ?? 1;

        $requiredTailors = $requiredAttendanceBudget / $firstExpense;

        $todayRealBudget = $todayPlan * $firstExpense;

        $oneEmployeeBudget = $todayAttendanceCount > 0 ? $todayRealBudget / $todayAttendanceCount : 0;

        return response()->json([
            'attendanceCount' => $todayAttendanceCount,
            'totalProduction_cost' => $totalProductionCost,
            'requiredAttendance_budget' => $requiredAttendanceBudget,
            'requiredTailors' => floor($requiredTailors),
            'totalSpends' => $totalSpends,
            'todayRealPlan' => floor($todayPlan),
            'todayRealBudget' => floor($todayRealBudget),
            'oneEmployeeBudget' => $oneEmployeeBudget,
        ]);
    }
}
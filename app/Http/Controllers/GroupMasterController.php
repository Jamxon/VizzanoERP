<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetOrderGroupMasterResource;
use App\Http\Resources\GetTarificationGroupMasterResource;
use App\Http\Resources\ShowOrderGroupMaster;
use App\Models\Bonus;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderCut;
use App\Models\OrderGroup;
use App\Models\OrderModel;
use App\Models\OrderSubModel;
use App\Models\SewingOutputs;
use App\Models\Tarification;
use App\Models\Time;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GroupMasterController extends Controller
{
    public function receiveOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderId = $request->input('order_id');
        $submodelId = $request->input('submodel_id');

        try {
            DB::beginTransaction();

            $order = Order::find($orderId);

            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            $orderSubmodel = OrderSubmodel::find($submodelId);

            if (!$orderSubmodel) {
                return response()->json(['message' => 'OrderSubmodel not found'], 404);
            }

            $allOrderSubmodels = OrderSubmodel::where('order_model_id', $orderSubmodel->order_model_id)->pluck('id')->toArray();

            $existingOrderGroup = OrderGroup::where('submodel_id', $submodelId)->first();

            $oldData = null;
            $newData = [
                'order_id' => $order->id,
                'group_id' => auth()->user()->group->id,
                'submodel_id' => $submodelId
            ];

            if ($existingOrderGroup) {
                $oldData = $existingOrderGroup->toArray();
                $existingOrderGroup->update([
                    'group_id' => auth()->user()->group->id,
                ]);
                $newData = $existingOrderGroup->fresh()->toArray();
            } else {
                OrderGroup::create([
                    'order_id' => $order->id,
                    'group_id' => auth()->user()->group->id,
                    'submodel_id' => $submodelId,
                ]);
            }

            $linkedSubmodels = OrderGroup::whereIn('submodel_id', $allOrderSubmodels)->distinct('submodel_id')->count();

            $orderOldStatus = $order->status;

            if (count($allOrderSubmodels) > 0 && count($allOrderSubmodels) == $linkedSubmodels) {
                $order->update(['status' => 'tailoring']);

                // Log order status change separately
                if ($orderOldStatus !== 'tailoring') {
                    Log::add(
                        auth()->id(),
                        "Buyurtma statusi o'zgartirildi!",
                        'receive',
                        ['order_id' => $order->id, 'status' => $orderOldStatus],
                        ['order_id' => $order->id, 'status' => 'tailoring']
                    );
                }
            }

            DB::commit();

            // Log the main action
            Log::add(
                auth()->id(),
                'Buyurtma qabul qilindi',
                $oldData,
                $newData
            );

            return response()->json([
                'message' => 'Order received successfully',
                'order' => $order,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error receiving order: ' . $e->getMessage()], 500);
        }
    }

    public function getPendingOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status', 'pending')
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->with([
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.sizes.size',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group.group',
                'orderModel.submodels.group.group.responsibleUser',
                'instructions',
            ])
            ->get();

        return response()->json($orders);
    }

    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user->group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $query = OrderGroup::where('group_id', $user->group->id)
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['pending', 'tailoring']);
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

                // Tikilgan umumiy sonni hisoblash
                $totalSewn = $order->orderModel->submodels->flatMap(function ($submodel) {
                    return $submodel->sewingOutputs;
                })->sum('quantity');

                // Dinamik property qo‘shish yoki resource ichida ishlatish
                $order->total_sewn_quantity = $totalSewn;
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
                'orderModel.submodels.group.group',
                'instructions',
                'orderModel.submodels.sewingOutputs',
                'orderModel.submodels.exampleOutputs',
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

    public function assignEmployeesToTarifications(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->input('data');
        $logData = [];

        foreach ($data as $item) {
            $tarificationId = $item['tarification_id'];
            $userId = $item['user_id'];

            $tarification = Tarification::find($tarificationId);

            $oldUserId = $tarification->user_id;

            $tarification->update([
                'user_id' => $userId
            ]);

            // Collect log data for each assignment
            $logData[] = [
                'tarification_id' => $tarificationId,
                'old_user_id' => $oldUserId,
                'new_user_id' => $userId
            ];
        }

        // Log the action with all assignments
        Log::add(
            auth()->id(),
            'Tarificationga xodim tayinlandi',
            'assign',
            null,
            $logData
        );

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
            'quantity' => 'required|integer|min:0',
            'time_id' => 'required|exists:times,id',
            'comment' => 'nullable|string',
        ]);

        $orderSubModel = OrderSubModel::find($validatedData['order_submodel_id']);
        $orderModel = OrderModel::find($orderSubModel->order_model_id);
        $order = Order::find($orderModel->order_id);

        if (!$order) {
            return response()->json(['message' => 'Buyurtma topilmadi!'], 404);
        }

        if ($order->status === 'pending') {
            $order->update(['status' => 'tailoring']);
        }

        $date = now()->toDateString();
        $exists = SewingOutputs::where('order_submodel_id', $validatedData['order_submodel_id'])
            ->where('time_id', $validatedData['time_id'])
            ->whereDate('created_at', $date)
            ->exists();

        if ($exists) {
            return response()->json(['message' => '❗️Bugun bu uchun natija kiritilgan, sahifani yangilang.'], 422);
        }

        $minutes = $orderModel->rasxod / 250;
        $orderQuantity = $order->quantity;
        $totalSewnQuantity = SewingOutputs::where('order_submodel_id', $orderSubModel->id)->sum('quantity');
        $newQuantity = $validatedData['quantity'];
        $combinedQuantity = $totalSewnQuantity + $newQuantity;

        if ($combinedQuantity > $orderQuantity) {
            $a = $orderQuantity - $totalSewnQuantity;
            return response()->json(['message' => "Siz faqat $a dona qo'shishingiz mumkin. Buyurtma umumiy miqdori: {$orderQuantity}, allaqachon tikilgan: {$totalSewnQuantity}."], 400);
        }

        $sewingOutput = SewingOutputs::create($validatedData);

        if ($combinedQuantity === $orderQuantity) {
            $order->update(['status' => 'tailored']);
        }

        // Bonuslar yoziladi
        $total_bonus_logs = [];

        $individualEmployees = Employee::where('payment_type', 'fixed_tailored_bonus')
            ->where('status', '!=', 'kicked')
            ->where('branch_id', $order->branch_id)
            ->get();

        foreach ($individualEmployees as $employee) {
            $bonusAmount = $employee->bonus * $minutes * $newQuantity;
            $oldBalance = $employee->balance;
            $employee->balance += $bonusAmount;
            $employee->save();

            $total_bonus_logs[] = [
                'employee_id' => $employee->id,
                'old_balance' => $oldBalance,
                'new_balance' => $employee->balance,
                'bonus_added' => $bonusAmount,
                'type' => 'individual',
            ];

            Bonus::create([
                'employee_id' => $employee->id,
                'order_id' => $order->id,
                'type' => 'individual',
                'amount' => $bonusAmount,
                'quantity' => $newQuantity,
                'old_balance' => $oldBalance,
                'new_balance' => $employee->balance,
                'created_by' => auth()->id(),
            ]);

            Log::add(auth()->id(), 'Maosh yozildi', 'create', $oldBalance, $employee->balance);
        }

        $groupEmployees = Employee::where('payment_type', 'fixed_tailored_bonus_group')
            ->where('status', '!=', 'kicked')
            ->where('group_id', $orderSubModel->group->group_id)
            ->where('branch_id', $order->branch_id)
            ->get();

        foreach ($groupEmployees as $employee) {
            $bonusAmount = $employee->bonus * $minutes * $newQuantity;
            $oldBalance = $employee->balance;
            $employee->balance += $bonusAmount;
            $employee->save();

            $total_bonus_logs[] = [
                'employee_id' => $employee->id,
                'old_balance' => $oldBalance,
                'new_balance' => $employee->balance,
                'bonus_added' => $bonusAmount,
                'type' => 'group',
            ];

            Bonus::create([
                'employee_id' => $employee->id,
                'order_id' => $order->id,
                'type' => 'individual',
                'amount' => $bonusAmount,
                'quantity' => $newQuantity,
                'old_balance' => $oldBalance,
                'new_balance' => $employee->balance,
                'created_by' => auth()->id(),
            ]);

            Log::add(auth()->id(), 'Guruh bo‘yicha maosh yozildi', 'create', $oldBalance, $employee->balance);
        }

        $time = Time::find($validatedData['time_id']);
        $user = auth()->user();
        $submodelName = $orderSubModel->submodel->name ?? '—';
        $orderName = $order->name ?? '—';
        $groupName = $orderSubModel->group->group->name ?? '—';
        $responsible = optional($orderSubModel->group->group->responsibleUser->employee)->name ?? '—';

        // Yangi natija haqida xabar
        $newEntryMessage = "<b>🧵 Yangi natija kiritildi</b>\n";
        $newEntryMessage .= "👤 <b>Foydalanuvchi:</b> {$user->employee->name}\n";
        $newEntryMessage .= "📦 <b>Buyurtma:</b> {$orderName}\n";
        $newEntryMessage .= "🧶 <b>Submodel:</b> {$submodelName}\n";
        $newEntryMessage .= "👥 <b>Guruh:</b> {$groupName}\n";
        $newEntryMessage .= "🧑‍💼 <b>Mas’ul:</b> {$responsible}\n";
        $newEntryMessage .= "➕ <b>Kiritilgan:</b> <b><i>{$newQuantity}</i></i></b> dona\n\n";

        // Bugungi umumiy natijalar (shu filialdagi barcha orderlar bo‘yicha)
        $today = now()->toDateString();

        $groupedSewings = SewingOutputs::whereDate('created_at', $today)
            ->whereHas('orderSubmodel.orderModel.order', function ($q) use ($order) {
                $q->where('branch_id', $order->branch_id);
            })
            ->with([
                'orderSubmodel.orderModel.model',
                'orderSubmodel.submodel',
                'orderSubmodel.group.group',
                'orderSubmodel.group.group.responsibleUser.employee',
            ])
            ->get()
            ->groupBy('order_submodel_id');

        $todaySummaryMessage = "📊 <b>Bugungi umumiy tikuv natijalari</b>\n";

        foreach ($groupedSewings as $submodelId => $outputs) {
            $first = $outputs->first();
            $model = optional($first->orderSubmodel->orderModel->model)->name ?? '—';
            $group = optional($first->orderSubmodel->group->group)->name ?? '—';
            $responsible = optional($first->orderSubmodel->group->group->responsibleUser->employee)->name ?? '—';
            $sum = $outputs->sum('quantity');

            $todaySummaryMessage .= "🔹 <b>{$model}</b> — <i>{$group}</i>\n";
            $todaySummaryMessage .= "👤 {$responsible} | ✅ {$sum} dona\n\n";
        }

        // Yuborish
        $this->sendTelegramMessage($newEntryMessage . $todaySummaryMessage);

        return response()->json([
            'message' => "Natija muvaffaqiyatli qo'shildi. Qolgan miqdor: " . ($orderQuantity - $combinedQuantity)
        ]);
    }

    private function sendTelegramMessage(string $message): void
    {
        $token = "7544266151:AAEzvGwm2kQRcHmlD17DxDA7xadjiY_-nkY";
        $chatId = -1001883536528;

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
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

        $oldData = $orderCut->toArray();

        $orderCut->update([
            'status' => true,
            'user_id' => auth()->user()->id,
        ]);

        $newData = $orderCut->fresh()->toArray();

        // Log the action
        Log::add(
            auth()->id(),
            'Kesilgan detallar qabul qilindi',
            'receive',
            $oldData,
            $newData
        );

        return response()->json([
            'message' => 'Order cut received successfully',
            'order_cut' => $orderCut
        ]);
    }

    public function getPlans(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $group = $user->group;

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

        $totalProductionCost = $orderSubModelSumma[0]['summa'] * $orderCalculations[0]['quantity'];

        $firstExpense = $orderCalculations->first()['expense'] ?? 1;

        $requiredTailors = $requiredAttendanceBudget / $orderSubModelSumma[0]['summa'] ?? 1;

        $todayRealBudget = $todayPlan * $orderSubModelSumma[0]['summa'];

        $oneEmployeeBudget = $todayAttendanceCount > 0 ? $todayRealBudget / $todayAttendanceCount : 0;

        $resultData = [
            'attendanceCount' => $todayAttendanceCount,
            'totalProductionCost' => $totalProductionCost,
            'requiredAttendanceBudget' => $requiredAttendanceBudget,
            'requiredTailors' => floor($requiredTailors),
            'totalSpends' => $totalSpends,
            'todayRealPlan' => $todayPlan,
            'todayRealBudget' => $todayRealBudget,
            'oneEmployeeBudget' => $oneEmployeeBudget,
        ];

        return response()->json($resultData);
    }
}
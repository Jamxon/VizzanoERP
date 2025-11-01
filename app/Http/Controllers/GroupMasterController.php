<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetOrderGroupMasterResource;
use App\Http\Resources\GetTarificationGroupMasterResource;
use App\Http\Resources\ShowOrderGroupMaster;
use App\Models\Bonus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Order;
use App\Models\OrderCut;
use App\Models\OrderGroup;
use App\Models\OrderModel;
use App\Models\OrderSubModel;
use App\Models\SewingOutputs;
use App\Models\ShipmentItem;
use App\Models\Tarification;
use App\Models\TelegramSewingMessage;
use App\Models\Time;
use App\Models\Log;
use Carbon\Carbon;
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
                'orderModel.sizes.color',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group.group',
                'orderModel.submodels.group.group.responsibleUser',
                'instructions',
            ])
            ->get();

        return response()->json($orders);
    }

    public function getOrders(): \Illuminate\Http\JsonResponse
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
                'order.orderModel.sizes.color',
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

    public function getOrdersAll(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user->group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $orders = Order::whereIn('status', [
            'pending',
            'tailoring',
            'active',
            'tailored',
            'cutting',
        ])
            ->where('branch_id', $user->employee->branch_id)
            ->with([
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.sizes.size',
                'orderModel.sizes.color',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group.group',
                'orderModel.submodels.tarificationCategories.tarifications',
                'instructions',
            ])
            ->get();

        return response()->json($orders);
    }

    public function showOrdersAll($id): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('id', $id)
            ->whereIn('status', [
                'pending',
                'tailoring',
                'active',
                'tailored',
                'cutting',
            ])
            ->with([
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.sizes.size',
                'orderModel.sizes.color',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group.group',
                'orderModel.submodels.tarificationCategories.tarifications',
                'instructions',
            ])
            ->first();

        return response()->json($orders);
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

    public function getEmployees(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $search = $request->search; // Employee name search query
        $paymentType = $request->payment_type; // Employee payment type filter
        $status = $request->status; // Employee status filter

        if (!$user->group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $employees = $user->group->employees()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($paymentType, function ($query, $paymentType) {
                $query->where('payment_type', $paymentType);
            })
            ->when(isset($status), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->paginate(10);

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

        $orderQuantity = $order->quantity;
        $totalSewnQuantity = SewingOutputs::where('order_submodel_id', $orderSubModel->id)->sum('quantity');
        $newQuantity = $validatedData['quantity'];
        $combinedQuantity = $totalSewnQuantity + $newQuantity;

        if ($combinedQuantity > $orderQuantity) {
            $a = $orderQuantity - $totalSewnQuantity;
            return response()->json([
                'message' => "Siz faqat $a dona qo'shishingiz mumkin. Buyurtma umumiy miqdori: {$orderQuantity}, allaqachon tikilgan: {$totalSewnQuantity}."
            ], 400);
        }

        // SewingOutput yaratish
        $sewingOutput = SewingOutputs::create($validatedData);

        if ($combinedQuantity === $orderQuantity) {
            $order->update(['status' => 'tailored']);
        }

        // Telegram xabarini yuborish
        $time = Time::find($validatedData['time_id']);
        $user = auth()->user();
        $submodelName = $orderSubModel->submodel->name ?? '—';
        $orderName = $order->name ?? '—';
        $groupName = $orderSubModel->group->group->name ?? '—';
        $responsible = optional($orderSubModel->group->group->responsibleUser->employee)->name ?? '—';

        $newEntryMessage = "<b>🧵 Yangi natija kiritildi</b>\n";
        $newEntryMessage .= "⏰<b>{$time->time}</b>\n";
        $newEntryMessage .= "➕ <b>Kiritilgan:</b> {$newQuantity} dona\n";
        $newEntryMessage .= "📉 <b>Qolgan:</b> " . ($orderQuantity - $combinedQuantity) . " dona\n";
        $newEntryMessage .= "👤 <b>Foydalanuvchi:</b> {$user->employee->name}\n";
        $newEntryMessage .= "📦 <b>Buyurtma:</b> {$orderName}\n";
        $newEntryMessage .= "🧶 <b>Submodel:</b> {$submodelName}\n";
        $newEntryMessage .= "👥 <b>Guruh:</b> {$groupName}\n";
        $newEntryMessage .= "🧑‍💼 <b>Mas'ul:</b> {$responsible}\n";
        $newEntryMessage .= "\n";

        $today = now()->toDateString();
        $branchId = $order->branch_id;
        $timeId = $validatedData['time_id'];

        $sameTimeOutputs = SewingOutputs::whereDate('created_at', $today)
            ->where('time_id', $timeId)
            ->whereHas('orderSubmodel.orderModel.order', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->with([
                'orderSubmodel.orderModel.model',
                'orderSubmodel.submodel',
                'orderSubmodel.group.group',
                'orderSubmodel.group.group.responsibleUser.employee',
            ])
            ->get()
            ->groupBy('order_submodel_id');

        $summaryMessage = "⏰ <b>{$time->time}</b> dagi natijalar:\n";

        $sortedOutputs = $sameTimeOutputs->map(function ($outputs) {
            return [
                'outputs' => $outputs,
                'total_quantity' => $outputs->sum('quantity')
            ];
        })->sortByDesc('total_quantity');

        $totalSumForTime = $sameTimeOutputs->flatten()->sum('quantity');

        foreach ($sortedOutputs as $entry) {
            $outputs = $entry['outputs'];
            $first = $outputs->first();
            $model = optional($first->orderSubmodel->orderModel->model)->name ?? '—';
            $group = optional($first->orderSubmodel->group->group)->name ?? '—';
            $responsible = optional($first->orderSubmodel->group->group->responsibleUser->employee)->name ?? '—';
            $sum = $entry['total_quantity'];

            $orderQty = optional($first->orderSubmodel->orderModel->order)->quantity ?? 0;
            $sewnQty = SewingOutputs::where('order_submodel_id', $first->order_submodel_id)->sum('quantity');
            $remaining = max($orderQty - $sewnQty, 0);

            $summaryMessage .= "🔹 {$model} — {$group}\n";
            $summaryMessage .= "👤 {$responsible} | ✅ {$sum} dona | 📉 Qoldiq: {$remaining} dona\n\n";
        }
        $summaryMessage .= "⏰ <b><i>Jami natijalar: {$totalSumForTime} dona </i></b> ⚡️\n";

        $telegramResult = $this->sendTelegramMessageWithEditSupport(
            $newEntryMessage . $summaryMessage,
            $time->time,
            $timeId,
            $branchId
        );

        if ($telegramResult['status'] === 'error') {
            return response()->json($telegramResult, 500);
        }

        Log::add(
            auth()->id(),
            'Natija kiritildi',
            'sewing_output',
            null,
            $sewingOutput->toArray()
        );

        /** ✅ Payment calculation for Sewing Output based departments **/
        $sewingDepartments = [
            'Сифат назорати ва қадоқлаш бўлими',
            'Маъмурий бошқарув',
            'Хўжалик ишлари бўлими',
            'Режалаштириш ва иқтисод бўлими',
            'Тикув бўлими',
        ];

        $branchId = auth()->user()->employee->branch_id;

        foreach ($sewingDepartments as $deptName) {

            $department = Department::where('name', $deptName)
                ->whereHas('mainDepartment', function($q) use($branchId) {
                    $q->where('branch_id', $branchId);
                })->first();


            if (!$department) continue;

            $budget = DB::table('department_budgets')
                ->where('department_id', $department->id)
                ->first();

            if (!$budget) continue;

            $modelMinute = $orderModel->model->minute ?? 0;

            $usdRate = getUsdRate();
            $orderUsdPrice = $order->price ?? 0;
            $orderUzsPrice = $orderUsdPrice * $usdRate;

            dd($usdRate);

            if ($budget->type == 'minute_based' && $modelMinute > 0) {
                $totalAmount = $modelMinute * $newQuantity * $budget->quantity;
            } elseif ($budget->type == 'percentage_based') {
                $percentage = $budget->quantity ?? 0; // Bu yerda type = %, quantity = foiz

                $totalAmount = round(($orderUzsPrice * $percentage) / 100, 2);
            } else {
                $totalAmount = $newQuantity * $budget->quantity;
            }

            $employees = Employee::where('department_id', $department->id)
                ->where('status', 'working')
                ->whereHas('attendances', function ($q) {
                    $q->whereDate('date', Carbon::today())
                        ->where('status', 'present');
                })
                ->get();

            foreach ($employees as $emp) {

                $percentage = $emp->percentage ?? 0;
                if ($percentage == 0) continue;

                $earned = round(($totalAmount * $percentage) / 100, 2);
                if ($earned == 0) continue;

                $existing = DB::table('daily_payments')
                    ->where('employee_id', $emp->id)
                    ->where('order_id', $order->id)
                    ->where('model_id', $orderModel->model->id)
                    ->first();

                if ($existing) {
                    DB::table('daily_payments')->where('id', $existing->id)
                        ->update([
                            'quantity_produced' => $existing->quantity_produced + $newQuantity,
                            'calculated_amount' => $existing->calculated_amount + $earned,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('daily_payments')->insert([
                        'employee_id' => $emp->id,
                        'model_id' => $orderModel->model->id,
                        'order_id' => $order->id,
                        'department_id' => $department->id,
                        'payment_date' => Carbon::today(),
                        'quantity_produced' => $newQuantity,
                        'calculated_amount' => $earned,
                        'employee_percentage' => $percentage,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $usdRate = getUsdRate();
        $orderUsdPrice = $order->total_price_usd ?? 0;
        $orderUzsPrice = $orderUsdPrice * $usdRate;

        $expenses = DB::table('expenses')
            ->where('branch_id', $branchId)
            ->get();

        foreach ($expenses as $expense) {

            // ✅ faqat master va texnolog
            if (!in_array(strtolower($expense->name), ['master', 'texnolog'])) {
                continue; // ✅ qolgan expense ni SKIP
            }

            if ($expense->type !== 'percentage_based') continue;

            $expenseAmount = round(($orderUzsPrice * $expense->quantity) / 100, 2);

            $employees = Employee::whereHas('position', function($q) use ($expense) {
                $q->whereIn('name', [
                    'master', 'Master', 'MASTer',
                    'texnolog', 'Texnolog', 'TEXNOLOG'
                ]);
            })
                ->where('branch_id', $branchId)
                ->whereHas('attendances', function ($q) {
                    $q->whereDate('date', Carbon::today())
                        ->where('status', 'present');
                })
                ->where('status', 'working')
                ->get();

            foreach ($employees as $emp) {

                $percentage = $emp->percentage ?? 0;
                $earned = round(($expenseAmount * $percentage) / 100, 2);

                if ($earned <= 0) continue;

                DB::table('daily_payments')->insert([
                    'employee_id' => $emp->id,
                    'order_id' => $order->id,
                    'model_id' => $order->orderModel->model->id,
                    'department_id' => null,
                    'expense_id' => $expense->id,
                    'payment_date' => Carbon::today(),
                    'quantity_produced' => $order->quantity,
                    'calculated_amount' => $earned,
                    'employee_percentage' => $percentage,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Response'da to'lov ma'lumotlarini ham qaytarish
        $responseMessage = "Natija muvaffaqiyatli qo'shildi. Qolgan miqdor: " . ($orderQuantity - $combinedQuantity);

        return response()->json([
            'message' => $responseMessage,
        ]);
    }

    private function sendTelegramMessageWithEditSupport(string $message, string $timeName, int $timeId, int $branchId)
    {
        try {
            $chatIdMap = [
                5 => -1001883536528,
                4 => -1003041140850,
            ];

            $chatId = $chatIdMap[$branchId] ?? null;

            if (!$chatId) {
                return [
                    'status' => 'error',
                    'message' => "Branch uchun chatId topilmadi (branch_id: $branchId)"
                ];
            }
            $today = now()->toDateString();

            $existing = TelegramSewingMessage::whereDate('date', $today)
                ->where('time_id', $timeId)
                ->where('branch_id', $branchId)
                ->first();

            if ($existing) {
                $this->editTelegramMessage($chatId, $existing->message_id, $message);
                return [
                    'status' => 'success',
                    'message' => 'Telegram message edited successfully',
                ];
            } else {
                $response = $this->sendTelegramMessage($message);

                if (
                    $response['status'] === 'success' &&
                    isset($response['data']['result']['message_id']) &&
                    !empty($response['data']['result']['message_id'])
                ) {
                    TelegramSewingMessage::create([
                        'time_id' => $timeId,
                        'date' => $today,
                        'branch_id' => $branchId,
                        'chat_id' => $chatId,
                        'message_id' => $response['data']['result']['message_id'],
                    ]);
                    return [
                        'status' => 'success',
                        'message' => 'Telegram message sent successfully',
                    ];
                } else {
                    return [
                        'status' => 'error',
                        'message' => 'Failed to send Telegram message',
                        'error' => $response['description'] ?? 'Unknown error'
                    ];
                }
            }
        } catch (\Throwable $e) {

            return [
                'status' => 'error',
                'message' => 'Telegramga yuborishda xato',
                'error' => $e->getMessage()
            ];
        }
    }

    private function sendTelegramMessage(string $message)
    {
        try {
            $botToken = "7544266151:AAEzvGwm2kQRcHmlD17DxDA7xadjiY_-nkY";
            $chatIdMap = [
                5 => -1001883536528,
                4 => -1003041140850,
            ];
            $branchId = auth()->user()->employee->branch_id;
            $chatId = $chatIdMap[$branchId] ?? null;
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

            $response = Http::post($url, [
                'chat_id'    => $chatId,
                'text'       => $message,
                'parse_mode' => 'HTML'
            ]);

            // Agar API javobi muvaffaqiyatsiz bo‘lsa
            if ($response->failed()) {
                return [
                    'status'  => 'error',
                    'message' => 'Telegram API xato javob qaytardi',
                    'code'    => $response->status(),
                    'body'    => $response->body()
                ];
            }

            return [
                'status'  => 'success',
                'data'    => $response->json()
            ];

        } catch (\Throwable $e) {
            // Logga yozib qo‘yish
            \Log::error('Telegram sendMessage error', [
                'error' => $e->getMessage()
            ]);

            return [
                'status'  => 'error',
                'message' => 'Telegramga yuborishda xato',
                'error'   => $e->getMessage()
            ];
        }
    }

    private function editTelegramMessage(string $chatId, string $messageId, string $message): array
    {
        $botToken = "7544266151:AAEzvGwm2kQRcHmlD17DxDA7xadjiY_-nkY";
        $url = "https://api.telegram.org/bot{$botToken}/editMessageText";

        $response = Http::post($url, [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);

        $json = $response->json();
        \Log::info('Telegram edit response', $json);

        return $json;
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

    public function getTopEarners(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->date ?? Carbon::today()->toDateString();
        $branchId = auth()->user()->employee->branch_id;
        $groupId = auth()->user()->group->id ?? null;

        if ($groupId === null){
            return response()->json(['message' => 'Group not found'], 404);
        }

        // 1. Har bir employee va tarification bo‘yicha grouping
        $logs = DB::table('employee_tarification_logs')
            ->select(
                'employee_id',
                'tarification_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(amount_earned) as total_earned')
            )
            ->whereDate('date', $date)
            ->whereIn('employee_id', Employee::where('group_id', $groupId)->pluck('id'))
            ->groupBy('employee_id', 'tarification_id')
            ->get();

        // 2. Daromad bo‘yicha employee-larni guruhlab umumiy topganini hisoblash
        $grouped = $logs->groupBy('employee_id')->map(function ($items, $employeeId) {
            $totalEarned = $items->sum('total_earned');

            $details = $items->map(function ($item) {
                $tarification = Tarification::with('tarificationCategory.submodel')->find($item->tarification_id);
                return [
                    'tarification_id' => $item->tarification_id,
                    'operation' => $tarification?->name,
                    'second' => $tarification?->second,
                    'code' => $tarification?->code,
                    'quantity' => $item->total_quantity,
                    'earned' => $item->total_earned,
                ];
            });

            $employee = Employee::find($employeeId);

            return [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name ?? '---',
                'image' => $employee->img ?? null,
                'group' => $employee->group->name ?? '---',
                'total_earned' => $totalEarned,
                'works' => $details,
            ];
        });

        // 3. Eng ko‘p topgan 10 nafar xodimni olish
        $topEarners = $grouped->sortByDesc('total_earned')->values();

        return response()->json([
            'date' => $date,
            'top_earners' => $topEarners,
        ]);
    }

    public function tvResult($id)
    {
        $today = now();
        $yesterday = now()->subDay();

        $group = Group::where('id', $id)
            ->with([
                'plans' => function ($query) use ($today) {
                    $query->where('month', $today->month)
                        ->where('year', $today->year);
                },
                'responsibleUser.employee',
                'orders.order.orderModel.submodels',
                'employees:id,name,img,group_id'
            ])
            ->firstOrFail();

        $plan = $group->plans->first();

        // 🔢 Kunlik plan
        $dailyPlan = null;
        if ($plan) {
            $daysInMonth = $today->daysInMonth;
            $sundays = 0;
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $date = $today->copy()->startOfMonth()->addDays($i - 1);
                if ($date->isSunday()) $sundays++;
            }
            $workingDays = max(1, $daysInMonth - $sundays);
            $dailyPlan = (int) ceil($plan->quantity / $workingDays);
        }

        // 📅 Yakshanba bo‘lsa kechagi
        $resultDate = $today->isSunday() ? $yesterday : $today;

        // 🧵 Tikilgan natija
        $submodelIds = $group->orders
            ->flatMap(fn($o) => $o->order->orderModel?->submodels->pluck('id') ?? collect())
            ->toArray();

        $todayResult = SewingOutputs::whereIn('order_submodel_id', $submodelIds)
            ->whereDate('created_at', $resultDate->toDateString())
            ->sum('quantity');

        // 🥇 TOP-3 xodim
        $employeeIds = $group->employees->pluck('id');

        $topRows = DB::table('employee_tarification_logs')
            ->select('employee_id', DB::raw('SUM(amount_earned) AS total_earned'))
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', $resultDate->toDateString())
            ->groupBy('employee_id')
            ->orderByDesc('total_earned')
            ->limit(3)
            ->get();

        $topEarners = $topRows->map(function ($row) use ($group, $resultDate) {
            $emp = $group->employees->firstWhere('id', $row->employee_id);

            // 🔎 shu kunlik tarifikatsiyalarini olish
            $tarifications = DB::table('employee_tarification_logs')
                ->select(
                    'tarification_id',
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('SUM(amount_earned) as total_earned')
                )
                ->where('employee_id', $row->employee_id)
                ->whereDate('date', $resultDate->toDateString())
                ->groupBy('tarification_id')
                ->get()
                ->map(function ($item) {
                    $tar = \App\Models\Tarification::with('tarificationCategory.submodel')->find($item->tarification_id);
                    return [
                        'tarification_id' => $item->tarification_id,
                        'operation'       => $tar?->name,
                        'code'            => $tar?->code,
                        'second'          => $tar?->second,
                        'quantity'        => $item->total_quantity,
                        'earned'          => $item->total_earned,
                    ];
                });

            return [
                'employee_id'   => (int) $row->employee_id,
                'employee_name' => $emp->name ?? '---',
                'image'         => $emp->img ?? null,
                'group'         => $group->name,
                'total_earned'  => (float) $row->total_earned,
                'works'         => $tarifications, // ← shu kungi barcha operationlari
            ];
        })->values();

        return response()->json([
            'group_name'        => $group->name,
            'daily_plan'        => $dailyPlan,
            'responsible_user'  => $group->responsibleUser,
            'today_result'      => (int) $todayResult,
            'result_date'       => $resultDate->toDateString(),
            'top_earners'       => $topEarners, // TOP-3 + tarifikatsiyalari
        ]);
    }
}

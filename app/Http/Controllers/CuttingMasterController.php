<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use App\Models\BoxTarification;
use App\Http\Resources\GetOrderCutResource;
use App\Http\Resources\GetSpecificationResource;
use App\Http\Resources\showOrderCuttingMasterResource;
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
                'user:id,name',
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
            $order = Order::find($id);
            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }

            $oldStatus = $order->status;

            // Kesilgan umumiy quantity ni hisoblash
            $totalCutQuantity = DB::table('order_cuts')
                ->where('order_id', $id)
                ->sum('quantity');

            $orderQuantity = $order->quantity;

            // Agar hali kesilmagan quantity mavjud bo‘lsa
            $remaining = $orderQuantity - $totalCutQuantity;

            if ($remaining > 0) {
                // qolganini saqlash uchun OrderCut yozuvi qo‘shish
                DB::table('order_cuts')->insert([
                    'order_id' => $order->id,
                    'user_id' => auth()->id(),
                    'cut_at' => now(),
                    'quantity' => $remaining,
                    'status' => false, // yoki boshqa maqbul status
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Order statusini yangilash
            $order->update([
                'status' => 'pending'
            ]);

            // Printing time statusini yangilash
            $order->orderPrintingTime->update([
                'status' => 'completed'
            ]);

            // Log yozish
            Log::add(
                auth()->user()->id,
                "Buyurtmani kesish yakunlandi (Order ID: $id)",
                'cut',
                ['old_data' => $oldStatus, 'order_id' => $id],
                ['new_data' => 'pending']
            );

            DB::commit();

            $departmentId = auth()->user()->employee->department_id ?? 'Noma\'lum';

            $departmentBudget = DB::table('department_budgets')
                ->where('department_id', $departmentId)
                ->first();

            $modelMinute = $order->orderModel->model->minute;

            if ($departmentBudget->type === 'minute_based' && $modelMinute) {
                $totalMinutes = $modelMinute * $order->quantity;
                $totalEarned = $departmentBudget->quantity * $totalMinutes;

                $departmentEmployees = Employee::where('department_id', $departmentId)
                    ->whereHas('attendances', function ($query) {
                        $query->whereDate('date', Carbon::today()->toDateString());
                        $query->where('status', 'present');
                    })
                    ->where('status', '!=', 'kicked')
                    ->get();

                foreach ($departmentEmployees as $employee) {
                    $employeePercentage = $employee->percentage;
                    $employeeEarned = $totalEarned / 100 * $employeePercentage;

                    DB::insert('INSERT INTO daily_payments (employee_id, model_id, order_id, department_id, payment_date, quantity_produced, calculated_amount, employee_percentage, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [
                        $employee->id,
                        $order->orderModel->model->id,
                        $order->id,
                        $departmentId,
                        Carbon::today()->toDateString(),
                        $order->quantity,
                        $employeeEarned,
                        $employeePercentage,
                        now(),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Order cutting finished',
                'remaining_cut_added' => $remaining > 0 ? $remaining : 0
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function markAsCutAndExportMultiplePdfs(Request $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'quantity' => 'required|integer|min:1',
            'submodel_id' => 'required|integer|exists:order_sub_models,id',
            'size_id' => 'required|integer|exists:order_sizes,id',
            'box_capacity' => 'required|integer|min:1',
            'region' => 'required|string|max:255',
        ]);

        ini_set('memory_limit', '2G');
        set_time_limit(0);

        $order = Order::findOrFail($data['order_id']);

        $existingCutForSubmodel = OrderCut::where('order_id', $data['order_id'])
            ->where('submodel_id', $data['submodel_id'])
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
            'submodel_id' => $data['submodel_id'],
            'size_id' => $data['size_id'],
        ]);

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

            // Bonus log entry
            Log::add(
                auth()->id(),
                'Qadoqlovchiga bonus qo‘shildi',
                'packaging_bonus',
                $oldBalance,
                $employee->balance,
                request()->ip(),
                request()->userAgent()
            );

            // Bonus modelga yozish
            Bonus::create([
                'employee_id' => $employee->id,
                'amount' => $bonusAmount,
                'type' => 'packaging',
                'description' => 'Qadoqlash bonusi',
                'date' => now(),
                'order_id' => $order->id,
            ]);
        }

        $totalCut = OrderCut::where('order_id', $order->id)->sum('quantity');

        if ($totalCut >= $order->quantity) {
            $order->status = 'pending';
            $order->save();
        }


        $submodel = OrderSubmodel::with([
            'orderModel.order:id,name',
            'orderModel.model:id,name',
            'submodel:id,name',
        ])->findOrFail($data['submodel_id']);

        // Tanlangan regionga mos tarificationCategories olish:
        $filteredCategories = $submodel->tarificationCategories()
            ->where('region', $data['region'])
            ->with([
                'tarifications.razryad:id,name',
                'tarifications.typewriter:id,name',
                'tarifications.employee:id,name'
            ])
            ->get();

        $submodel->tarificationCategories = $filteredCategories;


        $sizeName = OrderSize::find($data['size_id'])->size->name ?? '-';
        $totalQuantity = $data['quantity'];
        $capacity = $data['box_capacity'];
        $boxes = intdiv($totalQuantity, $capacity);
        $remainder = $totalQuantity % $capacity;

        $pdfBoxes = [];
        $boxNumber = 1;

        for ($i = 0; $i < $boxes; $i++) {
            $pdfBoxes[] = $this->storeBoxTarifications(
                $boxNumber++, $capacity, $data, $submodel, $sizeName
            );
        }

        if ($remainder > 0) {
            $pdfBoxes[] = $this->storeBoxTarifications(
                $boxNumber, $remainder, $data, $submodel, $sizeName
            );
        }

        $pdf = Pdf::loadView('pdf.tarifications-pdf', [ 
            'boxes' => $pdfBoxes,
            'totalQuantity' => $totalQuantity,
            'totalBoxes' => count($pdfBoxes),
            'submodel' => $submodel,
            'size' => $sizeName,
            'order_id' => $data['order_id'],
        ])->setPaper('A4', 'portrait');

        return $pdf->download('kesish_tarifikatsiyasi_' . now()->format('Ymd_His') . '.pdf');
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

}
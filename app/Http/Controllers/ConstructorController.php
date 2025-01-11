<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class ConstructorController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status', 'active')
            ->whereDate('start_date', '<=', now()->addDays(3)->toDateString())
            ->orderBy('start_date', 'asc')
            ->get();

        return response()->json($orders);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class TailorMasterController extends Controller
{
    public function getOrders()
    {
        $orders = Order::where('branch_id' , auth()->user()->branch_id)
            ->with('orderItems')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
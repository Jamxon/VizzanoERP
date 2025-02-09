<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GroupMasterController extends Controller
{
    public function getOrders()
    {
        $user = auth()->user();
        $group = $user->group;
        return $group->orders;
    }
}

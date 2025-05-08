<?php

namespace App\Http\Controllers;

use App\Models\SubModel;
use Illuminate\Http\Request;

class SubModelController extends Controller
{
    public function index()
    {
        $submodel = SubModel::all();
        return response()->json($submodel);
    }
}

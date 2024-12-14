<?php

namespace App\Http\Controllers;

use App\Models\Detail;
use Illuminate\Http\Request;

class DetailController extends Controller
{
    public function index()
    {
        $details = Detail::orderBy('updated_at', 'asc')->get();
        return response()->json($details);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'detail_category_id' => 'required|integer|exists:detail_categories,id',
            'razryad_id' => 'required|integer|exists:razyrads,id',
            'machine' => 'required|string',
            'second' => 'required|integer',
            'summa' => 'required|numeric',
        ]);

        $detail = Detail::create([
            'name' => $request->name,
            'detail_category_id' => $request->detail_category_id,
            'razryad_id' => $request->razryad_id,
            'machine' => $request->machine,
            'second' => $request->second,
            'summa' => $request->summa,
        ]);

        if ($detail) {
            return response()->json([
                'message' => 'Detail created successfully',
                'detail' => $detail,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Detail not created',
                'error' => $detail->errors(),
            ]);
        }
    }
    public function update(Request $request, Detail $detail)
    {
        if ($request->second > $detail->second || $request->second < $detail->second) {
            Detail::update([
                'second' => $request->second,
                'summa' => $request->second * $detail->razryad->salary,
            ]);
        }
    }
}

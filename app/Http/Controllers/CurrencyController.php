<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $currencies = \App\Models\Currency::all();

        return response()->json($currencies);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $currency = \App\Models\Currency::create($validated);

        return response()->json($currency, 201);
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        $currency = \App\Models\Currency::findOrFail($id);

        return response()->json($currency);
    }

    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $currency = \App\Models\Currency::findOrFail($id);
        $currency->update($validated);

        return response()->json($currency);
    }
}

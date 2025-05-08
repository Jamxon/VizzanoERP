<?php

namespace App\Http\Controllers;

use App\Models\Source;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $sources = Source::all();

        return response()->json($sources);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $source = Source::create($validated);

        return response()->json($source, 201);
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        $source = Source::findOrFail($id);

        return response()->json($source);
    }

    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $source = Source::findOrFail($id);
        $source->update($validated);

        return response()->json($source);
    }
}

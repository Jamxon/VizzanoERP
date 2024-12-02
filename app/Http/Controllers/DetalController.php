<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetDetalResource;
use App\Models\Detal;
use Illuminate\Http\Request;

class DetalController extends Controller
{
    public function index()
    {
        $data = Detal::all();
        $resource = GetDetalResource::collection($data);
        return response($resource, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'model_id' => 'required',
            'name' => 'required',
        ]);
        $data = Detal::create($request->all());
        $resource = new GetDetalResource($data);
        return response($resource, 201);
    }

    public function update(Request $request, Detal $detal)
    {
        $request->validate([
            'model_id' => 'required',
            'name' => 'required',
        ]);
        $detal->model_id = $request->model_id;
        $detal->name = $request->name;
        $detal->save();
        $resource = new GetDetalResource($detal);
        return response($resource, 200);
    }

    public function delete(Detal $detal)
    {
        $detal->delete();
        return response(null, 204);
    }

    public function sortByModel($id): \Illuminate\Foundation\Application|\Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = Detal::where('model_id', $id)->get();
        $resource = GetDetalResource::collection($data);
        return response($resource, 200);
    }
}

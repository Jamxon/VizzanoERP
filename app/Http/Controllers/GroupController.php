<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetGroupsResource;
use App\Models\Group;
use App\Models\OrderGroup;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $groups = Group::all();
        $resource = GetGroupsResource::collection($groups);
        return response()->json($resource);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $group = Group::create($request->all());
        return response()->json($group);
    }

    public function update(Request $request, Group $group): \Illuminate\Http\JsonResponse
    {
        $groups = Group::find($group->id);

        // Ma'lumotlarni yangilash
        $groups->update($request->all());

        // Javobni qaytarish
        return response()->json([
            'message' => 'Group updated successfully',
            'group' => $group,
        ], 200);
    }


    public function delete(Group $group): \Illuminate\Http\JsonResponse
    {
       if ($group->delete()){
              return response()->json([
                'message' => 'Group deleted successfully'
              ], 200);
       }
       else{
              return response()->json([
                'message' => 'Group not found'
              ], 404);
       }
    }

    public function fasteningOrderToGroup(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (is_null($data)) {
            return response()->json([
                'message' => 'Invalid JSON format',
            ], 400);
        }

        $validator = validator($data, [
            'data' => 'required|array',
            'data.*.group_id' => 'required|integer|exists:groups,id',
            'data.*.order_id' => 'required|integer|exists:orders,id',
            'data.*.submodel_id' => 'required|integer|exists:submodels,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        foreach ($validatedData['data'] as $datum) {
            $groupId = $datum['group_id'];
            $orderId = $datum['order_id'];
            $submodelId = $datum['submodel_id'];

            OrderGroup::create([
                'group_id' => $groupId,
                'order_id' => $orderId,
                'submodel_id' => $submodelId,
            ]);
        }

        return response()->json([
            'message' => 'Order fastened to group successfully',
        ], 200);
    }

}

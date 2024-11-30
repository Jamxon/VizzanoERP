<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetGroupsResource;
use App\Models\Group;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index()
    {
        $groups = Group::all();
        $resource = GetGroupsResource::collection($groups);
        return response()->json($resource);
    }

    public function store(Request $request)
    {
        $group = Group::create($request->all());
        return response()->json($group);
    }

    public function update(Request $request, Group $group)
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


    public function delete(Group $group)
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
}

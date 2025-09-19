<?php

namespace App\Http\Controllers;

use App\Models\TodoList;
use Illuminate\Http\Request;


class TodoListController
{
    public function index()
    {
        $todos = TodoList::where('user_id', auth()->id())->get();
        return response()->json($todos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $todo = TodoList::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'due_date' => $request->due_date,
        ]);

        return response()->json($todo, 201);
    }

    public function update(Request $request, $id)
    {
        $todo = TodoList::where('user_id', auth()->id())->findOrFail($id);

        $todo->update($request->only('title', 'description', 'is_done', 'due_date'));

        return response()->json($todo);
    }

    public function destroy($id)
    {
        $todo = TodoList::where('user_id', auth()->id())->findOrFail($id);
        $todo->delete();

        return response()->json(['message' => 'Todo deleted successfully']);
    }
}
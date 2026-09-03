<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActionItem;
use App\Models\Meeting;
use Illuminate\Http\Request;

class ActionItemController extends Controller
{
    public function index(Meeting $meeting)
    {
        return response()->json($meeting->actionItems()->with('assignee')->get());
    }

    public function store(Request $request, Meeting $meeting)
    {
        $data = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'description' => 'required|string',
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:open,in_progress,done,overdue',
        ]);

        $item = $meeting->actionItems()->create($data);

        return response()->json($item->load('assignee'), 201);
    }

    public function update(Request $request, Meeting $meeting, ActionItem $actionItem)
    {
        $data = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'description' => 'sometimes|string',
            'due_date' => 'nullable|date',
            'status' => 'sometimes|in:open,in_progress,done,overdue',
        ]);

        $actionItem->update($data);

        return response()->json($actionItem->load('assignee'));
    }

    public function destroy(Meeting $meeting, ActionItem $actionItem)
    {
        $actionItem->delete();

        return response()->json(['message' => 'Action item deleted.']);
    }
}

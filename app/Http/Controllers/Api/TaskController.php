<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $tasks = $project->tasks()
            ->with('assignee', 'stage', 'subtasks')
            ->when($request->stage_id, fn ($q) => $q->where('stage_id', $request->stage_id))
            ->when($request->assigned_to, fn ($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            // Top-level tasks only by default; pass ?with_subtasks=1 to get everything flat
            ->when(!$request->boolean('with_subtasks'), fn ($q) => $q->whereNull('parent_task_id'))
            ->orderBy('planned_start')
            ->get();

        return response()->json($tasks);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'stage_id' => 'nullable|exists:project_stages,id',
            'parent_task_id' => 'nullable|exists:tasks,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'planned_hours' => 'nullable|numeric',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:todo,in_progress,review,done,blocked',
        ]);

        $task = $project->tasks()->create($data);

        return response()->json($task->load('assignee', 'stage'), 201);
    }

    public function show(Project $project, Task $task)
    {
        return response()->json($task->load('assignee', 'stage', 'subtasks', 'parentTask'));
    }

    public function update(Request $request, Project $project, Task $task)
    {
        $data = $request->validate([
            'stage_id' => 'nullable|exists:project_stages,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'planned_hours' => 'nullable|numeric',
            'actual_hours' => 'nullable|numeric',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:todo,in_progress,review,done,blocked',
        ]);

        $task->update($data);

        return response()->json($task->load('assignee', 'stage'));
    }

    public function destroy(Project $project, Task $task)
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }
}

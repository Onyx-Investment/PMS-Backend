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

        // Route status through applyStatus (rather than mass-assigning it)
        // so a task created directly as in_progress still gets started_at
        // stamped correctly.
        $status = $data['status'] ?? 'todo';
        unset($data['status']);

        if ($status === 'done') {
            return response()->json([
                'message' => "A task can't be created as Done directly — set it to In Progress first, then mark it Done once the work is finished, so hours can be calculated.",
            ], 422);
        }

        $task = $project->tasks()->make($data);
        $task->applyStatus($status);
        $task->save();

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
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:todo,in_progress,review,done,blocked',
            // NOTE: actual_hours is intentionally no longer accepted here —
            // it's computed by applyStatus() from started_at/completed_at.
        ]);

        if (isset($data['status'])) {
            if ($data['status'] === 'done' && !$task->started_at) {
                return response()->json([
                    'message' => 'Move this task to In Progress before marking it Done, so its hours can be calculated.',
                ], 422);
            }

            $task->applyStatus($data['status']);
            unset($data['status']);
        }

        $task->fill($data);
        $task->save();

        return response()->json($task->load('assignee', 'stage'));
    }

    public function destroy(Project $project, Task $task)
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    /**
     * All tasks assigned to the current user, across every project.
     * Route this outside the projects.tasks group, e.g.:
     *   Route::get('/tasks/assigned-to-me', [TaskController::class, 'assignedToMe']);
     */
    public function assignedToMe(Request $request)
    {
        $userId = $request->user()->id;

        $baseQuery = fn () => Task::where('assigned_to', $userId);

        $today = now()->startOfDay();

        $stats = [
            'total' => $baseQuery()->count(),
            'todo' => $baseQuery()->where('status', 'todo')->count(),
            'in_progress' => $baseQuery()->where('status', 'in_progress')->count(),
            'review' => $baseQuery()->where('status', 'review')->count(),
            'done' => $baseQuery()->where('status', 'done')->count(),
            'blocked' => $baseQuery()->where('status', 'blocked')->count(),
            'overdue' => $baseQuery()
                ->where('status', '!=', 'done')
                ->whereNotNull('planned_end')
                ->whereDate('planned_end', '<', $today)
                ->count(),
            'due_today' => $baseQuery()
                ->whereNotNull('planned_end')
                ->whereDate('planned_end', $today)
                ->count(),
        ];

        $tasks = Task::with(['project', 'stage', 'assignee'])
            ->where('assigned_to', $userId)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            ->when($request->project_id, fn ($q) => $q->where('project_id', $request->project_id))
            ->when($request->search, function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where(function ($q2) use ($term) {
                    $q2->where('title', 'like', $term)
                       ->orWhere('description', 'like', $term);
                });
            })
            ->orderBy('planned_end')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $tasks->items(),
            'current_page' => $tasks->currentPage(),
            'last_page' => $tasks->lastPage(),
            'per_page' => $tasks->perPage(),
            'total' => $tasks->total(),
            'stats' => $stats,
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $tasks = $project->tasks()
            ->with('assignee', 'assignees', 'stage', 'subtasks')
            ->when($request->stage_id, fn ($q) => $q->where('stage_id', $request->stage_id))
            ->when($request->assigned_to, function ($q) use ($request) {
                // Matches a task assigned to this user via the new pivot.
                // (assigned_to filter kept under its old name for
                // frontend/back-compat; it now checks assignees, not the
                // legacy column.)
                $q->whereHas('assignees', fn ($a) => $a->where('users.id', $request->assigned_to));
            })
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            // Top-level tasks only by default; pass ?with_subtasks=1 to get everything flat
            ->when(!$request->boolean('with_subtasks'), fn ($q) => $q->whereNull('parent_task_id'))
            ->orderBy('planned_start')
            ->get();

        return response()->json($tasks);
    }

    /**
     * Validates the `assignees` array shared by store() and update():
     *   assignees: [{ user_id, planned_hours }, ...]
     * Rejects the same user_id appearing twice.
     */
    private function validateAssignees(Request $request): array
    {
        $data = $request->validate([
            'assignees' => 'nullable|array',
            'assignees.*.user_id' => 'required_with:assignees|integer|exists:users,id',
            'assignees.*.planned_hours' => 'nullable|numeric|min:0',
        ]);

        $assignees = $data['assignees'] ?? [];

        $userIds = array_column($assignees, 'user_id');
        if (count($userIds) !== count(array_unique($userIds))) {
            abort(422, 'The same person cannot be assigned to a task twice.');
        }

        return $assignees;
    }

    /**
     * Syncs a task's assignees() pivot and keeps the legacy assigned_to
     * column pointed at the first assignee (or null, if none) so anything
     * not yet reading the pivot still sees a sensible value.
     */
    private function syncAssignees(Task $task, array $assignees): void
    {
        $syncData = [];
        foreach ($assignees as $a) {
            $syncData[$a['user_id']] = [
                'planned_hours' => $a['planned_hours'] ?? null,
            ];
        }

        $task->assignees()->sync($syncData);

        $task->assigned_to = $assignees[0]['user_id'] ?? null;
        $task->planned_hours = $assignees[0]['planned_hours'] ?? null;
        $task->saveQuietly();
    }

    public function store(Request $request, Project $project)
    {
        $assignees = $this->validateAssignees($request);

        $data = $request->validate([
            'stage_id' => 'nullable|exists:project_stages,id',
            'parent_task_id' => 'nullable|exists:tasks,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
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

        $this->syncAssignees($task, $assignees);

        return response()->json($task->load('assignees', 'stage'), 201);
    }

    public function show(Project $project, Task $task)
    {
        return response()->json($task->load('assignees', 'stage', 'subtasks', 'parentTask'));
    }

    public function update(Request $request, Project $project, Task $task)
    {
        $assignees = $request->has('assignees')
            ? $this->validateAssignees($request)
            : null;

        $data = $request->validate([
            'stage_id' => 'nullable|exists:project_stages,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
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

        // Only touch assignment if the request actually sent an
        // `assignees` key — omitting it entirely leaves assignment as-is,
        // the same way omitting any other field does.
        if ($assignees !== null) {
            $this->syncAssignees($task, $assignees);
        }

        return response()->json($task->load('assignees', 'stage'));
    }

    public function destroy(Project $project, Task $task)
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    /**
     * All tasks assigned to the current user, across every project.
     * Now checks the assignees pivot instead of the legacy assigned_to
     * column, so it picks up every task the user is on — not just ones
     * where they happen to be the first assignee.
     */
    public function assignedToMe(Request $request)
    {
        $userId = $request->user()->id;

        $baseQuery = fn () => Task::whereHas(
            'assignees',
            fn ($a) => $a->where('users.id', $userId)
        );

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

        $tasks = Task::with(['project', 'stage', 'assignees'])
            ->whereHas('assignees', fn ($a) => $a->where('users.id', $userId))
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
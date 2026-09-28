<?php
// app/Http/Controllers/Api/TimeEntryController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Timesheet;
use App\Models\TimeEntry;
use App\Models\Project;
use App\Models\Task;
use App\Models\Staff;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    public function store(Request $request, Timesheet $timesheet)
    {
        abort_if($timesheet->status !== 'draft', 422, 'Only draft timesheets can be edited. Ask for it to be reopened first.');

        return $request->filled('project_id')
            ? $this->storeProjectEntry($request, $timesheet)
            : $this->storeInternalEntry($request, $timesheet);
    }

    public function update(Request $request, Timesheet $timesheet, TimeEntry $entry)
    {
        abort_if($timesheet->status !== 'draft', 422, 'Only draft timesheets can be edited.');

        // Project entries are locked to their task once created — hours,
        // project and task all come from the task, so only date and
        // description are editable here.
        if ($entry->project_id) {
            $data = $request->validate([
                'date' => [
                    'sometimes',
                    'date',
                    'after_or_equal:' . $timesheet->week_start->toDateString(),
                    'before_or_equal:' . $timesheet->week_end->toDateString(),
                ],
                'description' => 'required|string|min:3',
            ]);

            $entry->update($data);

            return response()->json($entry->load('project', 'task'));
        }

        $data = $request->validate([
            'time_code_id' => 'required|exists:time_codes,id',
            'internal_task_id' => 'nullable|exists:internal_tasks,id',
            'date' => [
                'sometimes',
                'date',
                'after_or_equal:' . $timesheet->week_start->toDateString(),
                'before_or_equal:' . $timesheet->week_end->toDateString(),
            ],
            'hours' => 'sometimes|numeric|min:0.25|max:24',
            'description' => 'required|string|min:3',
        ]);

        $entry->update($data);

        return response()->json($entry->load('timeCode'));
    }

    public function destroy(Timesheet $timesheet, TimeEntry $entry)
    {
        abort_if($timesheet->status !== 'draft', 422, 'Only draft timesheets can be edited.');

        $entry->delete();

        return response()->json(['message' => 'Time entry deleted.']);
    }

    public function getStaffProjectEntries(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'staff_id' => 'required|exists:staff,id',
        ]);

        $staff = Staff::with('user')->find($data['staff_id']);
        if (!$staff) {
            return response()->json([]);
        }

        $entries = TimeEntry::where('project_id', $data['project_id'])
            ->whereHas('timesheet', function ($q) use ($staff) {
                $q->where('user_id', $staff->user_id);
            })
            ->get();

        return response()->json($entries);
    }

    // ========================================================
    // PROJECT WORK — hours are sourced from the completed task,
    // not typed. No time code on this branch.
    // ========================================================

    private function storeProjectEntry(Request $request, Timesheet $timesheet)
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'required|exists:tasks,id',
            'date' => [
                'required',
                'date',
                'after_or_equal:' . $timesheet->week_start->toDateString(),
                'before_or_equal:' . $timesheet->week_end->toDateString(),
            ],
            'description' => 'required|string|min:3',
        ]);

        $userId = $request->user()->id;
        $staff = Staff::where('user_id', $userId)->first();

        if (!$staff) {
            return response()->json(['message' => 'Staff record not found for this user.'], 403);
        }

        $project = Project::find($data['project_id']);

        $isAssigned = $project->team()->where('staff_id', $staff->id)->exists();
        if (!$isAssigned) {
            return response()->json(['message' => 'You are not assigned to this project.'], 403);
        }

        $task = Task::where('id', $data['task_id'])
            ->where('project_id', $data['project_id'])
            ->first();

        if (!$task) {
            return response()->json(['message' => 'Task does not belong to the selected project.'], 422);
        }

        // assigned_to on tasks is a users.id (Task::assignee() belongs to
        // User), not a staff id — compare against the authenticated user.
        if ($task->assigned_to != $userId) {
            return response()->json(['message' => 'This task is not assigned to you.'], 403);
        }

        if ($task->status !== 'done' || $task->actual_hours === null) {
            return response()->json([
                'message' => "This task isn't marked Done yet, so there are no hours to log. Mark it complete first.",
            ], 422);
        }

        if (TimeEntry::where('task_id', $task->id)->exists()) {
            return response()->json([
                'message' => 'Hours for this task have already been logged on a timesheet.',
            ], 422);
        }

        $data['time_code_id'] = null;
        $data['internal_task_id'] = null;
        $data['hours'] = $task->actual_hours;

        $entry = $timesheet->entries()->create($data);

        return response()->json($entry->load('project', 'task'), 201);
    }

    // ========================================================
    // INTERNAL / TIME CODE — unchanged: hours are still typed,
    // time code is still required.
    // ========================================================

    private function storeInternalEntry(Request $request, Timesheet $timesheet)
    {
        $data = $request->validate([
            'time_code_id' => 'required|exists:time_codes,id',
            'internal_task_id' => 'nullable|exists:internal_tasks,id',
            'date' => [
                'required',
                'date',
                'after_or_equal:' . $timesheet->week_start->toDateString(),
                'before_or_equal:' . $timesheet->week_end->toDateString(),
            ],
            'hours' => 'required|numeric|min:0.25|max:24',
            'description' => 'required|string|min:3',
        ]);

        $data['project_id'] = null;
        $data['task_id'] = null;

        $entry = $timesheet->entries()->create($data);

        return response()->json($entry->load('timeCode'), 201);
    }


    public function billable(Request $request)
{
    return $this->filteredEntries($request, true);
}

public function nonBillable(Request $request)
{
    return $this->filteredEntries($request, false);
}

private function filteredEntries(Request $request, bool $billable)
{
    $request->validate([
        'project_id' => 'nullable|integer|exists:projects,id',
        'date_from'  => 'nullable|date',
        'date_to'    => 'nullable|date|after_or_equal:date_from',
        'per_page'   => 'nullable|integer|min:1|max:100',
        'staff_id' => 'nullable|integer|exists:staff,id',
    ]);

    return TimeEntry::with([
            'staff.user',
            'staff.gradeLevel',
            'project',
            'task',
            'timeCode',
        ])
        ->where('billable', $billable)
        ->when($request->project_id, fn ($q, $id) => $q->where('project_id', $id))
        ->when($request->staff_id, fn ($q, $staffId) =>
    $q->whereHas('task', fn ($t) => $t->where('assigned_to', $staffId))
)
        ->when($request->date_from, fn ($q, $d) => $q->whereDate('date', '>=', $d))
        ->when($request->date_to, fn ($q, $d) => $q->whereDate('date', '<=', $d))
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->paginate($request->integer('per_page', 20));
}
}
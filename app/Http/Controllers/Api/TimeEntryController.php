<?php
// app/Http/Controllers/Api/TimeEntryController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Timesheet;
use App\Models\TimeEntry;
use App\Models\Project;
use App\Models\Staff;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    public function store(Request $request, Timesheet $timesheet)
    {
        abort_if($timesheet->status !== 'draft', 422, 'Only draft timesheets can be edited. Ask for it to be reopened first.');

        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'nullable|exists:tasks,id',
            'time_code_id' => 'required|exists:time_codes,id',
            'date' => [
                'required',
                'date',
                'after_or_equal:' . $timesheet->week_start->toDateString(),
                'before_or_equal:' . $timesheet->week_end->toDateString(),
            ],
            'hours' => 'required|numeric|min:0.25|max:24',
            'description' => 'required|string|min:3',
        ]);

        // Verify the user is assigned to this project
        $userId = $request->user()->id;
        $staff = Staff::where('user_id', $userId)->first();
        
        if (!$staff) {
            return response()->json([
                'message' => 'Staff record not found for this user.'
            ], 403);
        }

        $project = Project::find($data['project_id']);
        
        // Check if user is assigned to this project
        $isAssigned = $project->team()->where('staff_id', $staff->id)->exists();
        if (!$isAssigned) {
            return response()->json([
                'message' => 'You are not assigned to this project.'
            ], 403);
        }

        // Get the staff assignment for this project
        $staffAssignment = $project->team()->where('staff_id', $staff->id)->first();
        $totalAssignedHours = $staffAssignment->planned_hours ?? 0;
        
        // Calculate used hours for this project in this timesheet
        $usedHours = $timesheet->entries()
            ->where('project_id', $data['project_id'])
            ->sum('hours');
        
        // Check if adding this entry would exceed the total assigned hours
        $newTotal = $usedHours + $data['hours'];
        if ($totalAssignedHours > 0 && $newTotal > $totalAssignedHours) {
            return response()->json([
                'message' => "You have used {$usedHours} hours out of {$totalAssignedHours} total assigned hours for this project. This entry would exceed your allocated hours.",
                'used_hours' => $usedHours,
                'total_assigned_hours' => $totalAssignedHours,
                'remaining_hours' => $totalAssignedHours - $usedHours
            ], 422);
        }

        // If task is provided, verify it belongs to the project
        if (!empty($data['task_id'])) {
            $task = \App\Models\Task::where('id', $data['task_id'])
                ->where('project_id', $data['project_id'])
                ->first();
            if (!$task) {
                return response()->json([
                    'message' => 'Task does not belong to the selected project.'
                ], 422);
            }
            
            // Verify the task is assigned to this user
            if ($task->assigned_to != $userId) {
                return response()->json([
                    'message' => 'This task is not assigned to you.'
                ], 403);
            }
        }

        $entry = $timesheet->entries()->create($data);

        return response()->json($entry->load('project', 'task', 'timeCode'), 201);
    }

    public function update(Request $request, Timesheet $timesheet, TimeEntry $entry)
    {
        abort_if($timesheet->status !== 'draft', 422, 'Only draft timesheets can be edited.');

        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'nullable|exists:tasks,id',
            'time_code_id' => 'sometimes|exists:time_codes,id',
            'date' => [
                'sometimes',
                'date',
                'after_or_equal:' . $timesheet->week_start->toDateString(),
                'before_or_equal:' . $timesheet->week_end->toDateString(),
            ],
            'hours' => 'sometimes|numeric|min:0.25|max:24',
            'description' => 'required|string|min:3',
        ]);

        // Verify the user is assigned to this project
        $userId = $request->user()->id;
        $staff = Staff::where('user_id', $userId)->first();
        
        if (!$staff) {
            return response()->json([
                'message' => 'Staff record not found for this user.'
            ], 403);
        }

        $project = Project::find($data['project_id']);
        
        // Check if user is assigned to this project
        $isAssigned = $project->team()->where('staff_id', $staff->id)->exists();
        if (!$isAssigned) {
            return response()->json([
                'message' => 'You are not assigned to this project.'
            ], 403);
        }

        // Get the staff assignment for this project
        $staffAssignment = $project->team()->where('staff_id', $staff->id)->first();
        $totalAssignedHours = $staffAssignment->planned_hours ?? 0;
        
        // Calculate used hours for this project in this timesheet (excluding the current entry)
        $usedHours = $timesheet->entries()
            ->where('project_id', $data['project_id'])
            ->where('id', '!=', $entry->id)
            ->sum('hours');
        
        // Check if updating would exceed the total assigned hours
        $newTotal = $usedHours + $data['hours'];
        if ($totalAssignedHours > 0 && $newTotal > $totalAssignedHours) {
            return response()->json([
                'message' => "You have used {$usedHours} hours out of {$totalAssignedHours} total assigned hours for this project. This update would exceed your allocated hours.",
                'used_hours' => $usedHours,
                'total_assigned_hours' => $totalAssignedHours,
                'remaining_hours' => $totalAssignedHours - $usedHours
            ], 422);
        }

        // If task is provided, verify it belongs to the project
        if (!empty($data['task_id'])) {
            $task = \App\Models\Task::where('id', $data['task_id'])
                ->where('project_id', $data['project_id'])
                ->first();
            if (!$task) {
                return response()->json([
                    'message' => 'Task does not belong to the selected project.'
                ], 422);
            }
            
            // Verify the task is assigned to this user
            if ($task->assigned_to != $userId) {
                return response()->json([
                    'message' => 'This task is not assigned to you.'
                ], 403);
            }
        }

        $entry->update($data);

        return response()->json($entry->load('project', 'task', 'timeCode'));
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

    // Get the staff's user
    $staff = \App\Models\Staff::with('user')->find($data['staff_id']);
    if (!$staff) {
        return response()->json([]);
    }

    $entries = TimeEntry::where('project_id', $data['project_id'])
        ->whereHas('timesheet', function($q) use ($staff) {
            $q->where('user_id', $staff->user_id);
        })
        ->get();

    return response()->json($entries);
}
}
<?php
// app/Http/Controllers/Api/InternalTaskController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternalTask;
use App\Models\TimeCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InternalTaskController extends Controller
{
    /**
     * Get all internal tasks with their time codes
     */
    public function index(Request $request)
    {
        $tasks = InternalTask::with('timeCode')
            ->when($request->time_code_id, function ($query) use ($request) {
                return $query->where('time_code_id', $request->time_code_id);
            })
            ->when($request->is_active !== null, function ($query) use ($request) {
                return $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('time_code_id')
            ->orderBy('name')
            ->get();

        return response()->json($tasks);
    }

    /**
     * Get tasks for a specific time code
     */
    public function getTasksByTimeCode($timeCodeId)
    {
        $timeCode = TimeCode::findOrFail($timeCodeId);
        
        $tasks = $timeCode->internalTasks()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json($tasks);
    }

    /**
     * Store a new internal task
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'time_code_id' => 'required|exists:time_codes,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        // Check if time code is non-billable/internal
        $timeCode = TimeCode::find($data['time_code_id']);
        if (!in_array($timeCode->category, ['non_billable', 'internal'])) {
            return response()->json([
                'message' => 'Internal tasks can only be created for non-billable or internal time codes'
            ], 422);
        }

        // Check for duplicate task name within the same time code
        $existing = InternalTask::where('time_code_id', $data['time_code_id'])
            ->where('name', $data['name'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A task with this name already exists for this time code'
            ], 422);
        }

        $task = InternalTask::create($data);

        return response()->json($task->load('timeCode'), 201);
    }

    /**
     * Get a specific internal task
     */
    public function show(InternalTask $internalTask)
    {
        return response()->json($internalTask->load('timeCode'));
    }

    /**
     * Update an internal task
     */
    public function update(Request $request, InternalTask $internalTask)
    {
        $data = $request->validate([
            'time_code_id' => 'sometimes|exists:time_codes,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        // If time_code_id is being updated, validate it's non-billable/internal
        if (isset($data['time_code_id'])) {
            $timeCode = TimeCode::find($data['time_code_id']);
            if (!in_array($timeCode->category, ['non_billable', 'internal'])) {
                return response()->json([
                    'message' => 'Internal tasks can only be assigned to non-billable or internal time codes'
                ], 422);
            }

            // Check for duplicate task name within the new time code
            if (isset($data['name'])) {
                $existing = InternalTask::where('time_code_id', $data['time_code_id'])
                    ->where('name', $data['name'])
                    ->where('id', '!=', $internalTask->id)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'message' => 'A task with this name already exists for this time code'
                    ], 422);
                }
            }
        }

        // Check for duplicate task name within the same time code
        if (isset($data['name']) && isset($data['time_code_id'])) {
            $existing = InternalTask::where('time_code_id', $data['time_code_id'])
                ->where('name', $data['name'])
                ->where('id', '!=', $internalTask->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'A task with this name already exists for this time code'
                ], 422);
            }
        }

        $internalTask->update($data);

        return response()->json($internalTask->load('timeCode'));
    }

    /**
     * Delete an internal task
     */
    public function destroy(InternalTask $internalTask)
    {
        // Check if this task is being used in any time entries
        if ($internalTask->timeEntries()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete this task as it is being used in time entries'
            ], 422);
        }

        $internalTask->delete();

        return response()->json(['message' => 'Internal task deleted successfully']);
    }

    /**
     * Bulk create internal tasks for a time code
     */
    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'time_code_id' => 'required|exists:time_codes,id',
            'tasks' => 'required|array',
            'tasks.*.name' => 'required|string|max:255',
            'tasks.*.description' => 'nullable|string|max:500',
            'tasks.*.is_active' => 'boolean',
        ]);

        $timeCode = TimeCode::find($data['time_code_id']);
        if (!in_array($timeCode->category, ['non_billable', 'internal'])) {
            return response()->json([
                'message' => 'Internal tasks can only be created for non-billable or internal time codes'
            ], 422);
        }

        $created = [];
        $errors = [];

        foreach ($data['tasks'] as $taskData) {
            try {
                // Check for duplicate
                $existing = InternalTask::where('time_code_id', $data['time_code_id'])
                    ->where('name', $taskData['name'])
                    ->first();

                if ($existing) {
                    $errors[] = "Task '{$taskData['name']}' already exists";
                    continue;
                }

                $task = InternalTask::create([
                    'time_code_id' => $data['time_code_id'],
                    'name' => $taskData['name'],
                    'description' => $taskData['description'] ?? null,
                    'is_active' => $taskData['is_active'] ?? true,
                ]);

                $created[] = $task;
            } catch (\Exception $e) {
                $errors[] = "Failed to create task '{$taskData['name']}': " . $e->getMessage();
            }
        }

        return response()->json([
            'created' => $created,
            'errors' => $errors,
            'total_created' => count($created),
            'total_errors' => count($errors),
        ], count($errors) > 0 ? 207 : 201);
    }
}
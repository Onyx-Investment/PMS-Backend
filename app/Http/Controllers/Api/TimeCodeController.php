<?php
// app/Http/Controllers/Api/TimeCodeController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeCode;
use Illuminate\Http\Request;

class TimeCodeController extends Controller
{
    public function index(Request $request)
    {
        $query = TimeCode::with('project');

        // Conditionally load internal tasks
        if ($request->has('with_tasks') && $request->with_tasks) {
            $query->with('internalTasks');
        }

        $codes = $query
            ->when($request->project_id, fn ($q) => $q->where('project_id', $request->project_id))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->orderBy('code')
            ->get();

        return response()->json($codes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:time_codes,code',
            'project_id' => 'nullable|exists:projects,id',
            'category' => 'required|in:billable,non_billable,internal',
            'description' => 'nullable|string',
        ]);

        $code = TimeCode::create($data);

        return response()->json($code->load('project', 'internalTasks'), 201);
    }

    public function update(Request $request, TimeCode $timeCode)
    {
        $data = $request->validate([
            'code' => 'sometimes|string|unique:time_codes,code,' . $timeCode->id,
            'project_id' => 'nullable|exists:projects,id',
            'category' => 'sometimes|in:billable,non_billable,internal',
            'description' => 'nullable|string',
        ]);

        $timeCode->update($data);

        return response()->json($timeCode->load('project', 'internalTasks'));
    }

    public function destroy(TimeCode $timeCode)
    {
        // Check if time code is in use
        if ($timeCode->timeEntries()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete time code as it is being used in time entries'
            ], 422);
        }

        // Check if time code has internal tasks
        if ($timeCode->internalTasks()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete time code as it has internal tasks associated. Delete the tasks first.'
            ], 422);
        }

        $timeCode->delete();
        return response()->json(['message' => 'Time code deleted successfully']);
    }
}
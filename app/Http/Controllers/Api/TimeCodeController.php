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
        $codes = TimeCode::with('project')
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

        return response()->json($code->load('project'), 201);
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

        return response()->json($timeCode->load('project'));
    }

    public function destroy(TimeCode $timeCode)
    {
        // Check if time code is in use
        if ($timeCode->timeEntries()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete time code as it is being used in time entries'
            ], 422);
        }

        $timeCode->delete();
        return response()->json(['message' => 'Time code deleted successfully']);
    }
}
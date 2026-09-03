<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectStage;
use Illuminate\Http\Request;

class ProjectStageController extends Controller
{
    public function index(Project $project)
    {
        return response()->json($project->stages()->withCount('tasks')->get());
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'nullable|integer|min:0',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'status' => 'nullable|in:pending,in_progress,completed,skipped',
        ]);

        $data['order'] ??= $project->stages()->max('order') + 1;

        $stage = $project->stages()->create($data);

        return response()->json($stage, 201);
    }

    public function update(Request $request, Project $project, ProjectStage $stage)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'order' => 'nullable|integer|min:0',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'status' => 'sometimes|in:pending,in_progress,completed,skipped',
        ]);

        $stage->update($data);

        return response()->json($stage);
    }

    public function destroy(Project $project, ProjectStage $stage)
    {
        $stage->delete();

        return response()->json(['message' => 'Stage deleted.']);
    }
}

<?php
// app/Http/Controllers/Api/StepController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Step;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StepController extends Controller
{
    public function index(Request $request)
    {
        $steps = Step::with('gradeLevel')
            ->when($request->grade_level_id, fn($q) => $q->where('grade_level_id', $request->grade_level_id))
            ->orderBy('grade_level_id')
            ->orderBy('step_number')
            ->get();
        
        return response()->json($steps);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'grade_level_id' => 'required|exists:grade_levels,id',
            'step_number' => [
                'required',
                'integer',
                Rule::unique('steps')->where(function ($query) use ($request) {
                    return $query->where('grade_level_id', $request->grade_level_id);
                }),
            ],
            'salary' => 'required|numeric|min:0',
            'cost_per_hour' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $step = Step::create($data);
        return response()->json($step->load('gradeLevel'), 201);
    }

    public function show(Step $step)
    {
        return response()->json($step->load('gradeLevel'));
    }

    public function update(Request $request, Step $step)
    {
        $data = $request->validate([
            'step_number' => [
                'sometimes',
                'integer',
                Rule::unique('steps')->where(function ($query) use ($step) {
                    return $query->where('grade_level_id', $step->grade_level_id);
                })->ignore($step->id),
            ],
            'salary' => 'sometimes|numeric|min:0',
            'cost_per_hour' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $step->update($data);
        return response()->json($step->load('gradeLevel'));
    }

    public function destroy(Step $step)
    {
        // Check if any staff are using this step
        if ($step->staff()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete step as it is assigned to staff members'
            ], 422);
        }

        $step->delete();
        return response()->json(['message' => 'Step deleted successfully']);
    }
}
<?php
// app/Http/Controllers/Api/GradeLevelController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GradeLevelController extends Controller
{
    public function index()
    {
        $gradeLevels = GradeLevel::orderBy('level')->get();
        return response()->json($gradeLevels);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'level' => 'required|integer|unique:grade_levels,level',
            'cost_per_hour' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $gradeLevel = GradeLevel::create($data);
        return response()->json($gradeLevel, 201);
    }

    public function show(GradeLevel $gradeLevel)
    {
        return response()->json($gradeLevel);
    }

    public function update(Request $request, GradeLevel $gradeLevel)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'level' => ['sometimes', 'integer', Rule::unique('grade_levels')->ignore($gradeLevel->id)],
            'cost_per_hour' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $gradeLevel->update($data);
        return response()->json($gradeLevel);
    }

    public function destroy(GradeLevel $gradeLevel)
    {
        // Check if any users are using this grade level
        if ($gradeLevel->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete grade level as it is assigned to staff members'
            ], 422);
        }

        $gradeLevel->delete();
        return response()->json(['message' => 'Grade level deleted successfully']);
    }
}
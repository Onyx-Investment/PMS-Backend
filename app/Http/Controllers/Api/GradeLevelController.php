<?php
// app/Http/Controllers/Api/GradeLevelController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GradeLevelController extends Controller
{
    public function index(Request $request)
    {
        $gradeLevels = GradeLevel::with('steps') // ✅ Add this to eager load steps
            ->orderBy('level')
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->get();
        
        return response()->json($gradeLevels);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'level' => 'required|integer|unique:grade_levels,level',
            'backend_code' => 'required|string|max:50|unique:grade_levels,backend_code',
            'category' => 'required|in:technical,support',
            'description' => 'nullable|string',
        ]);

        $gradeLevel = GradeLevel::create($data);
        return response()->json($gradeLevel->load('steps'), 201); // ✅ Load steps
    }

    public function show(GradeLevel $gradeLevel)
    {
        return response()->json($gradeLevel->load('steps')); // ✅ Load steps
    }

    public function update(Request $request, GradeLevel $gradeLevel)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'level' => ['sometimes', 'integer', Rule::unique('grade_levels')->ignore($gradeLevel->id)],
            'backend_code' => ['sometimes', 'string', 'max:50', Rule::unique('grade_levels')->ignore($gradeLevel->id)],
            'category' => 'sometimes|in:technical,support',
            'description' => 'nullable|string',
        ]);

        $gradeLevel->update($data);
        return response()->json($gradeLevel->load('steps')); // ✅ Load steps
    }

    public function destroy(GradeLevel $gradeLevel)
    {
        // Check if any staff are using this grade level
        if ($gradeLevel->staff()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete grade level as it is assigned to staff members'
            ], 422);
        }

        $gradeLevel->delete();
        return response()->json(['message' => 'Grade level deleted successfully']);
    }

    public function getByCategory($category)
    {
        $gradeLevels = GradeLevel::with('steps') // ✅ Load steps
            ->where('category', $category)
            ->orderBy('level')
            ->get();
        
        return response()->json($gradeLevels);
    }



public function findByBackendCode($backendCode)
{
    $gradeLevel = GradeLevel::with('steps')
        ->where('backend_code', $backendCode)
        ->firstOrFail();
    
    return response()->json($gradeLevel);
}
}
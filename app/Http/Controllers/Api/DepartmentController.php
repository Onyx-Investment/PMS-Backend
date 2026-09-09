<?php
// app/Http/Controllers/Api/DepartmentController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::with(['headOfDepartment.user', 'staff.user']);

        // Load relationships if requested
        if ($request->has('with')) {
            $relations = explode(',', $request->with);
            foreach ($relations as $relation) {
                $relation = trim($relation);
                if (in_array($relation, ['subDepartments', 'parentDepartments'])) {
                    $query->with($relation);
                }
            }
        }

        $departments = $query->orderBy('name')->get();
        return response()->json($departments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'description' => 'nullable|string',
            'head_of_department_id' => 'nullable|exists:staff,id',
            'parent_department_ids' => 'nullable|array',
            'parent_department_ids.*' => 'exists:departments,id',
        ]);

        $department = Department::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'head_of_department_id' => $data['head_of_department_id'] ?? null,
        ]);

        if (!empty($data['parent_department_ids'])) {
            $department->parentDepartments()->attach($data['parent_department_ids']);
        }

        return response()->json($department->load(['headOfDepartment.user', 'subDepartments', 'parentDepartments']), 201);
    }

    public function show(Department $department)
    {
        return response()->json($department->load([
            'headOfDepartment.user',
            'staff.user',
            'subDepartments',
            'parentDepartments'
        ]));
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('departments')->ignore($department->id)],
            'description' => 'nullable|string',
            'head_of_department_id' => 'nullable|exists:staff,id',
            'parent_department_ids' => 'nullable|array',
            'parent_department_ids.*' => 'exists:departments,id',
        ]);

        $department->update([
            'name' => $data['name'] ?? $department->name,
            'description' => $data['description'] ?? $department->description,
            'head_of_department_id' => $data['head_of_department_id'] ?? $department->head_of_department_id,
        ]);

        if (isset($data['parent_department_ids'])) {
            $department->parentDepartments()->sync($data['parent_department_ids']);
        }

        return response()->json($department->load(['headOfDepartment.user', 'subDepartments', 'parentDepartments']));
    }

    public function destroy(Department $department)
    {
        // Check if any staff belong to this department
        if ($department->staff()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete department as it has staff members assigned to it'
            ], 422);
        }

        // Check if it has sub-departments
        if ($department->subDepartments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete department as it has sub-departments'
            ], 422);
        }

        // Detach parent relationships
        $department->parentDepartments()->detach();
        $department->subDepartments()->detach();

        $department->delete();
        return response()->json(['message' => 'Department deleted successfully']);
    }

    // Add staff to department
    public function addStaff(Request $request, Department $department)
    {
        $data = $request->validate([
            'staff_ids' => 'required|array',
            'staff_ids.*' => 'exists:staff,id',
        ]);

        $department->staff()->syncWithoutDetaching($data['staff_ids']);

        return response()->json(['message' => 'Staff added to department successfully']);
    }

    // Remove staff from department
    public function removeStaff(Department $department, $staffId)
    {
        $department->staff()->detach($staffId);
        return response()->json(['message' => 'Staff removed from department successfully']);
    }

    // Add parent departments
    public function addParents(Request $request, Department $department)
    {
        $data = $request->validate([
            'parent_ids' => 'required|array',
            'parent_ids.*' => 'exists:departments,id',
        ]);

        $department->parentDepartments()->syncWithoutDetaching($data['parent_ids']);

        return response()->json(['message' => 'Parent departments added successfully']);
    }

    // Remove parent department
    public function removeParent(Department $department, $parentId)
    {
        $department->parentDepartments()->detach($parentId);
        return response()->json(['message' => 'Parent department removed successfully']);
    }
}
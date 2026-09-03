<?php
// app/Http/Controllers/Api/DepartmentController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with(['headOfDepartment.user', 'staff'])
            ->orderBy('name')
            ->get();
        return response()->json($departments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'description' => 'nullable|string',
            'head_of_department_id' => 'nullable|exists:staff,id',
        ]);

        $department = Department::create($data);
        return response()->json($department->load('headOfDepartment.user'), 201);
    }

    public function show(Department $department)
    {
        return response()->json($department->load(['headOfDepartment.user', 'staff.user']));
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('departments')->ignore($department->id)],
            'description' => 'nullable|string',
            'head_of_department_id' => 'nullable|exists:staff,id',
        ]);

        $department->update($data);
        return response()->json($department->load('headOfDepartment.user'));
    }

    public function destroy(Department $department)
    {
        // Check if any staff belong to this department
        if ($department->staff()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete department as it has staff members assigned to it'
            ], 422);
        }

        $department->delete();
        return response()->json(['message' => 'Department deleted successfully']);
    }
}
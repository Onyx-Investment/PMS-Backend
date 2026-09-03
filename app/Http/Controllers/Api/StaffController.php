<?php
// app/Http/Controllers/Api/StaffController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $staff = Staff::with(['user', 'departments', 'roles', 'gradeLevel', 'staffManager.user', 'staffType'])
            ->when($request->department_id, fn($q) => $q->whereHas('departments', function($query) use ($request) {
                $query->where('department_id', $request->department_id);
            }))
            ->when($request->role_id, fn($q) => $q->whereHas('roles', function($query) use ($request) {
                $query->where('role_id', $request->role_id);
            }))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where(function($query) use ($request) {
                $query->where('employee_no', 'like', "%{$request->search}%")
                    ->orWhereHas('user', function($q) use ($request) {
                        $q->where('first_name', 'like', "%{$request->search}%")
                            ->orWhere('last_name', 'like', "%{$request->search}%")
                            ->orWhere('email', 'like', "%{$request->search}%");
                    });
            }))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($staff);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:255',
            'password' => 'required|string|min:8',
            'employee_no' => 'required|string|unique:staff,employee_no',
            'staff_type_id' => 'nullable|exists:staff_types,id',
            'designation' => 'nullable|string|max:255',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
            'grade_level_id' => 'nullable|exists:grade_levels,id',
            'staff_manager_id' => 'nullable|exists:staff,id',
            'status' => 'nullable|in:active,inactive,on_leave',
            'joined_date' => 'nullable|date',
            'cost_per_hour' => 'nullable|numeric|min:0',
        ]);

        // Create user account
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        // Create staff record
        $staffData = [
            'user_id' => $user->id,
            'employee_no' => $data['employee_no'],
            'designation' => $data['designation'] ?? null,
            'grade_level_id' => $data['grade_level_id'] ?? null,
            'staff_manager_id' => $data['staff_manager_id'] ?? null,
            'status' => $data['status'] ?? 'active',
            'joined_date' => $data['joined_date'] ?? null,
            'cost_per_hour' => $data['cost_per_hour'] ?? null,
            'is_active' => true,
        ];
        
        $staff = Staff::create($staffData);

        // Attach departments
        if (!empty($data['department_ids'])) {
            $staff->departments()->attach($data['department_ids']);
        }

        // Attach roles
        if (!empty($data['role_ids'])) {
            $staff->roles()->attach($data['role_ids']);
        }

        return response()->json($staff->load(['user', 'departments', 'roles', 'gradeLevel']), 201);
    }

    public function show(Staff $staff)
    {
        return response()->json($staff->load(['user', 'departments', 'roles', 'gradeLevel', 'staffManager']));
    }

    public function update(Request $request, Staff $staff)
    {
        $data = $request->validate([
            'employee_no' => ['sometimes', 'string', Rule::unique('staff')->ignore($staff->id)],
            'designation' => 'nullable|string|max:255',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
            'staff_type_id' => 'nullable|exists:staff_types,id',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
            'grade_level_id' => 'nullable|exists:grade_levels,id',
            'staff_manager_id' => 'nullable|exists:staff,id',
            'status' => 'nullable|in:active,inactive,on_leave',
            'joined_date' => 'nullable|date',
            'cost_per_hour' => 'nullable|numeric|min:0',
        ]);

        // Update staff
        $staff->update($data);

        // Sync departments
        if (isset($data['department_ids'])) {
            $staff->departments()->sync($data['department_ids']);
        }

        // Sync roles
        if (isset($data['role_ids'])) {
            $staff->roles()->sync($data['role_ids']);
        }

        // Update user if needed
        if ($request->has('first_name') || $request->has('last_name') || $request->has('email') || $request->has('phone')) {
            $userData = $request->only(['first_name', 'last_name', 'email', 'phone']);
            $staff->user->update($userData);
        }

        return response()->json($staff->load(['user', 'departments', 'roles', 'gradeLevel']));
    }

    public function destroy(Staff $staff)
    {
        // Check if staff is assigned to any projects
        if ($staff->projectMemberships()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete staff member as they are assigned to projects'
            ], 422);
        }

        // Detach relationships
        $staff->departments()->detach();
        $staff->roles()->detach();

        // Get the user before deleting staff
        $user = $staff->user;
        
        // Delete staff record
        $staff->delete();
        
        // Deactivate user account
        if ($user) {
            $user->update(['is_active' => false]);
        }

        return response()->json(['message' => 'Staff deleted successfully']);
    }

    public function getActiveStaff()
    {
        $staff = Staff::with(['user', 'departments', 'roles', 'gradeLevel'])
            ->where('status', 'active')
            ->whereHas('user', fn($q) => $q->where('is_active', true))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($staff);
    }
}
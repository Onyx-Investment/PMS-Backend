<?php
// app/Http/Controllers/Api/UserController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Load users with their staff records
        $users = User::with(['staff.department', 'staff.role', 'staff.gradeLevel', 'staff.staffManager'])
            ->when($request->search, fn($q) => $q->where(function($query) use ($request) {
                $query->where('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->orderBy('first_name')
            ->paginate(20);

        return response()->json($users);
    }

    public function show(User $user)
    {
        return response()->json($user->load(['staff.department', 'staff.role', 'staff.gradeLevel', 'staff.staffManager']));
    }

    public function update(Request $request, User $user)
    {
        // Update user basic info
        $userData = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8',
        ]);

        if (isset($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        }

        $user->update($userData);

        // Update staff record if it exists
        if ($user->staff) {
            $staffData = $request->validate([
                'employee_no' => ['sometimes', 'string', Rule::unique('staff')->ignore($user->staff->id)],
                'grade' => 'nullable|string|max:255',
                'designation' => 'nullable|string|max:255',
                'department_id' => 'nullable|exists:departments,id',
                'role_id' => 'nullable|exists:roles,id',
                'grade_level_id' => 'nullable|exists:grade_levels,id',
                'staff_manager_id' => 'nullable|exists:staff,id',
                'status' => 'nullable|in:active,inactive,on_leave',
                'joined_date' => 'nullable|date',
                'cost_per_hour' => 'nullable|numeric|min:0',
            ]);

            $user->staff->update($staffData);
        }

        return response()->json($user->load(['staff.department', 'staff.role', 'staff.gradeLevel']));
    }

    public function destroy(User $user)
    {
        // Delete staff record first if exists
        if ($user->staff) {
            $user->staff->delete();
        }
        
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }
}
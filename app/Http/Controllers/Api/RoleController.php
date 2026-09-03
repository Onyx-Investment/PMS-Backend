<?php
// app/Http/Controllers/Api/RoleController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('staff')
            ->orderBy('name')
            ->get();
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'slug' => 'required|string|max:255|unique:roles,slug|regex:/^[a-z0-9_]+$/',
            'description' => 'nullable|string',
        ]);

        $role = Role::create($data);
        return response()->json($role, 201);
    }

    public function show(Role $role)
    {
        return response()->json($role->load('staff.user'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => 'nullable|string',
        ]);

        // Slug cannot be updated
        $role->update($data);
        return response()->json($role);
    }

    public function destroy(Role $role)
    {
        // Check if any staff have this role
        if ($role->staff()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete role as it is assigned to staff members'
            ], 422);
        }

        $role->delete();
        return response()->json(['message' => 'Role deleted successfully']);
    }
}
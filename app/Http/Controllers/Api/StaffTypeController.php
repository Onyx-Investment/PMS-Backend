<?php
// app/Http/Controllers/Api/StaffTypeController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StaffTypeController extends Controller
{
    public function index()
    {
        $staffTypes = StaffType::withCount('staff')
            ->orderBy('name')
            ->get();
        
        return response()->json($staffTypes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:staff_types,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $staffType = StaffType::create($data);

        return response()->json($staffType, 201);
    }

    public function show(StaffType $staffType)
    {
        return response()->json($staffType->load('staff'));
    }

    public function update(Request $request, StaffType $staffType)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('staff_types')->ignore($staffType->id)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $staffType->update($data);

        return response()->json($staffType);
    }

    public function destroy(StaffType $staffType)
    {
        // Check if any staff have this type
        if ($staffType->staff()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete staff type as it is assigned to staff members'
            ], 422);
        }

        $staffType->delete();
        return response()->json(['message' => 'Staff type deleted successfully']);
    }

    public function getActiveTypes()
    {
        $staffTypes = StaffType::where('is_active', true)
            ->orderBy('name')
            ->get();
        
        return response()->json($staffTypes);
    }
}
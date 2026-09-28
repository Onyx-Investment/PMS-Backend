<?php
// app/Http/Controllers/Api/LeaveTypeController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller
{
    public function index(Request $request)
    {
        $leaveTypes = LeaveType::with('entitlements.gradeLevel')
            ->orderBy('name')
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->get();

        return response()->json($leaveTypes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:leave_types,name',
            'code' => 'nullable|string|max:50|unique:leave_types,code',
            'annual_days' => 'required|numeric|min:0',
            'paid' => 'boolean',
            'pay_basis' => 'nullable|in:flat,percentage',
            'flat_allowance' => 'nullable|numeric|min:0',
            'salary_percentage' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'auto_generate_code' => 'boolean',
        ]);

        // Auto-generate if requested, or if no code was supplied at all
        if (($request->boolean('auto_generate_code')) || empty($data['code'])) {
            $data['code'] = $this->generateLeaveTypeCode();
        }
        unset($data['auto_generate_code']);

        $leaveType = LeaveType::create($data);

        return response()->json($leaveType->load('entitlements.gradeLevel'), 201);
    }

    public function show(LeaveType $leaveType)
    {
        return response()->json($leaveType->load('entitlements.gradeLevel'));
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('leave_types', 'name')->ignore($leaveType->id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('leave_types', 'code')->ignore($leaveType->id)],
            'annual_days' => 'sometimes|numeric|min:0',
            'paid' => 'boolean',
            'pay_basis' => 'nullable|in:flat,percentage',
            'flat_allowance' => 'nullable|numeric|min:0',
            'salary_percentage' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'auto_generate_code' => 'boolean',
        ]);

        if ($request->boolean('auto_generate_code')) {
            $data['code'] = $this->generateLeaveTypeCode();
        }
        unset($data['auto_generate_code']);

        $leaveType->update($data);

        return response()->json($leaveType->load('entitlements.gradeLevel'));
    }

    public function destroy(LeaveType $leaveType)
    {
        if ($leaveType->leaveRequests()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete a leave type that has leave requests against it. Deactivate it instead.',
            ], 422);
        }

        $leaveType->delete();

        return response()->json(['message' => 'Leave type deleted successfully']);
    }

    /**
     * Preview the next auto-generated code without creating anything —
     * same idea as ProposalController::previewCode / ProjectController's
     * previewCodeFromProposal.
     */
    public function previewCode()
    {
        return response()->json(['code' => $this->generateLeaveTypeCode()]);
    }

    /**
     * Format: LV-XXX. No parent entity to derive the prefix from (unlike
     * proposal codes, which key off the lead), so it's a flat sequence.
     */
    private function generateLeaveTypeCode(): string
    {
        $prefix = 'LV';

        $lastLeaveType = LeaveType::where('code', 'like', "{$prefix}-%")
            ->orderBy('code', 'desc')
            ->first();

        if ($lastLeaveType) {
            $parts = explode('-', $lastLeaveType->code);
            $lastSequence = intval(end($parts));
            $sequence = str_pad($lastSequence + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $sequence = '001';
        }

        $code = "{$prefix}-{$sequence}";

        while (LeaveType::where('code', $code)->exists()) {
            $sequence = str_pad(intval($sequence) + 1, 3, '0', STR_PAD_LEFT);
            $code = "{$prefix}-{$sequence}";
        }

        return $code;
    }

    // ========================================================
    // GRADE-LEVEL ENTITLEMENT OVERRIDES (days + pay)
    // ========================================================

    public function storeEntitlement(Request $request, LeaveType $leaveType)
    {
        $data = $request->validate([
            'grade_level_id' => [
                'required',
                'exists:grade_levels,id',
                Rule::unique('leave_entitlements')->where(
                    fn ($q) => $q->where('leave_type_id', $leaveType->id)
                ),
            ],
            'annual_days' => 'required|numeric|min:0',
            'flat_allowance' => 'nullable|numeric|min:0',
            'salary_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $entitlement = $leaveType->entitlements()->create($data);

        return response()->json($entitlement->load('gradeLevel'), 201);
    }

    public function updateEntitlement(Request $request, LeaveType $leaveType, LeaveEntitlement $entitlement)
    {
        abort_unless($entitlement->leave_type_id === $leaveType->id, 404);

        $data = $request->validate([
            'annual_days' => 'required|numeric|min:0',
            'flat_allowance' => 'nullable|numeric|min:0',
            'salary_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $entitlement->update($data);

        return response()->json($entitlement->load('gradeLevel'));
    }

    public function destroyEntitlement(LeaveType $leaveType, LeaveEntitlement $entitlement)
    {
        abort_unless($entitlement->leave_type_id === $leaveType->id, 404);

        $entitlement->delete();

        return response()->json(['message' => 'Grade level override removed']);
    }
}
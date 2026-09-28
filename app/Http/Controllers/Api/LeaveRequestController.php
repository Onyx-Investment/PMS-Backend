<?php
// app/Http/Controllers/Api/LeaveRequestController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Staff;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    // Roles that can give the final (HR) approval — the same set used
    // elsewhere in the app (e.g. TimesheetController@approve) for
    // org-level sign-off.
    private const HR_ROLES = ['assignment_manager', 'assignment_lead', 'staff_manager', 'admin', 'ceo', 'coo', 'md'];
    private const FINANCE_ROLES = ['finance', 'admin', 'ceo', 'coo', 'md'];

    public function index(Request $request)
    {
        $user = $request->user();
        $staff = Staff::where('user_id', $user->id)->first();
        $isHr = $user->hasRole(...self::HR_ROLES);

        $query = LeaveRequest::with(['staff.user', 'leaveType', 'manager.user']);

        if ($request->boolean('mine') || (!$request->staff_id && !$isHr)) {
            abort_if(!$staff, 403, 'Staff record not found for this user.');
            $query->where('staff_id', $staff->id);
        } elseif ($request->staff_id) {
            $targetId = (int) $request->staff_id;
            $isOwn = $staff && $staff->id === $targetId;
            $isTheirManager = $staff && Staff::where('id', $targetId)->where('staff_manager_id', $staff->id)->exists();

            abort_unless($isHr || $isOwn || $isTheirManager, 403, 'Not authorized to view these requests.');
            $query->where('staff_id', $targetId);
        }
        // else: HR with no staff_id filter -> full org list, unfiltered by staff

        $requests = $query
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->leave_type_id, fn ($q) => $q->where('leave_type_id', $request->leave_type_id))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($requests);
    }

    /**
     * Requests currently awaiting this user's action — as a manager at the
     * pending_manager stage, and/or as HR at the pending_hr stage.
     */
    public function pendingApprovals(Request $request)
    {
        $user = $request->user();
        $staff = Staff::where('user_id', $user->id)->first();
        $isHr = $user->hasRole(...self::HR_ROLES);

        // $query = LeaveRequest::with(['staff.user', 'leaveType', 'manager.user'])
        $query = LeaveRequest::with(['staff.user', 'staff.step', 'staff.gradeLevel', 'leaveType', 'manager.user'])
            ->where(function ($q) use ($staff, $isHr) {
                $matched = false;

                if ($staff) {
                    $q->where(function ($q2) use ($staff) {
                        $q2->where('status', 'pending_manager')->where('manager_id', $staff->id);
                    });
                    $matched = true;
                }

                if ($isHr) {
                    $matched
                        ? $q->orWhere('status', 'pending_hr')
                        : $q->where('status', 'pending_hr');
                }

                if (!$matched && !$isHr) {
                    // Neither a manager of anyone nor HR — match nothing.
                    $q->whereRaw('1 = 0');
                }
            });

        // Surface what approving would actually pay out, so the approver
        // sees a missing pay setup (no pay_basis, no flat_allowance/
        // salary_percentage, or the staff has no cost_per_hour) *before*
        // they approve — instead of finding out after the fact that
        // pay_amount silently came back null. This is a preview only and
        // is never persisted here.
        return response()->json(
            $query->orderBy('created_at')->get()->map(function (LeaveRequest $r) {
                $r->expected_pay_amount = $r->leaveType->paid
                    ? $this->computeLeavePay($r->staff, $r->leaveType)
                    : null;

                return $r;
            })
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        $user = $request->user();
        $staff = Staff::where('user_id', $user->id)->first();
        abort_if(!$staff, 403, 'Staff record not found for this user.');

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        abort_if(!$leaveType->is_active, 422, 'This leave type is no longer active.');

        $days = LeaveRequest::countBusinessDays($data['start_date'], $data['end_date']);
        abort_if($days <= 0, 422, "The selected dates don't include any working days.");

        if ($leaveType->paid) {
            $balance = $this->computeBalance($staff, $leaveType, (int) date('Y', strtotime($data['start_date'])));
            if ($days > $balance['remaining']) {
                return response()->json([
                    'message' => "This request needs {$days} day(s) but you only have {$balance['remaining']} day(s) of {$leaveType->name} remaining.",
                    'balance' => $balance,
                ], 422);
            }
        }

        $status = $staff->staff_manager_id ? 'pending_manager' : 'pending_hr';

        $leaveRequest = LeaveRequest::create([
            'staff_id' => $staff->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => $status,
            'manager_id' => $staff->staff_manager_id,
        ]);

        return response()->json($leaveRequest->load(['staff.user', 'leaveType', 'manager.user']), 201);
    }

    public function show(LeaveRequest $leaveRequest)
    {
        return response()->json(
            $leaveRequest->load(['staff.user', 'leaveType', 'manager.user', 'managerActedBy', 'hrActedBy'])
        );
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $staff = Staff::where('user_id', $user->id)->first();
        $isHr = $user->hasRole(...self::HR_ROLES);

        if ($leaveRequest->status === 'pending_manager') {
            abort_unless($staff && $leaveRequest->manager_id === $staff->id, 403, "Only this request's manager can approve it at this stage.");

            $leaveRequest->update([
                'manager_action' => 'approved',
                'manager_acted_by' => $user->id,
                'manager_acted_at' => now(),
                'manager_notes' => $data['notes'] ?? null,
                'status' => 'pending_hr',
            ]);
        } elseif ($leaveRequest->status === 'pending_hr') {
            abort_unless($isHr, 403, 'Only HR/admin can give final approval.');

            // inside approve(), the pending_hr branch:
$payAmount = $this->computeLeavePay($leaveRequest->staff, $leaveRequest->leaveType);

$leaveRequest->update([
    'hr_action' => 'approved',
    'hr_acted_by' => $user->id,
    'hr_acted_at' => now(),
    'hr_notes' => $data['notes'] ?? null,
    'status' => 'approved',
    'pay_amount' => $payAmount,
    'payment_status' => $payAmount !== null ? 'unpaid' : null,
]);
        } else {
            return response()->json(['message' => 'This request has already been finalized.'], 422);
        }

        return response()->json($leaveRequest->load(['staff.user', 'leaveType', 'manager.user']));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $data = $request->validate([
            'notes' => 'required|string|min:3',
        ]);

        $user = $request->user();
        $staff = Staff::where('user_id', $user->id)->first();
        $isHr = $user->hasRole(...self::HR_ROLES);

        if ($leaveRequest->status === 'pending_manager') {
            abort_unless($staff && $leaveRequest->manager_id === $staff->id, 403, "Only this request's manager can act on it at this stage.");

            $leaveRequest->update([
                'manager_action' => 'rejected',
                'manager_acted_by' => $user->id,
                'manager_acted_at' => now(),
                'manager_notes' => $data['notes'],
                'status' => 'rejected',
            ]);
        } elseif ($leaveRequest->status === 'pending_hr') {
            abort_unless($isHr, 403, 'Only HR/admin can act at this stage.');

            $leaveRequest->update([
                'hr_action' => 'rejected',
                'hr_acted_by' => $user->id,
                'hr_acted_at' => now(),
                'hr_notes' => $data['notes'],
                'status' => 'rejected',
            ]);
        } else {
            return response()->json(['message' => 'This request has already been finalized.'], 422);
        }

        return response()->json($leaveRequest->load(['staff.user', 'leaveType', 'manager.user']));
    }


    public function markAsPaid(Request $request, LeaveRequest $leaveRequest)
{
    $data = $request->validate(['notes' => 'nullable|string']);

    $user = $request->user();
    abort_unless($user->hasRole(...self::FINANCE_ROLES), 403, 'Only Finance/admin can mark leave payments as paid.');
    abort_unless($leaveRequest->status === 'approved', 422, 'Only approved leave requests can be marked as paid.');
    abort_if($leaveRequest->pay_amount === null, 422, 'This leave request has no pay amount to mark as paid.');
    abort_if($leaveRequest->payment_status === 'paid', 422, 'This payment has already been marked as paid.');

    $leaveRequest->update([
        'payment_status' => 'paid',
        'paid_at' => now(),
        'paid_by' => $user->id,
        'payment_notes' => $data['notes'] ?? null,
    ]);

    return response()->json($leaveRequest->load(['staff.user', 'leaveType', 'paidBy']));
}

public function unmarkAsPaid(Request $request, LeaveRequest $leaveRequest)
{
    $user = $request->user();
    abort_unless($user->hasRole(...self::FINANCE_ROLES), 403, 'Only Finance/admin can reverse a payment.');
    abort_unless($leaveRequest->payment_status === 'paid', 422, 'This payment has not been marked as paid.');

    $leaveRequest->update([
        'payment_status' => 'unpaid',
        'paid_at' => null,
        'paid_by' => null,
        'payment_notes' => null,
    ]);

    return response()->json($leaveRequest->load(['staff.user', 'leaveType']));
}

    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();
        $staff = Staff::where('user_id', $user->id)->first();

        abort_unless($staff && $leaveRequest->staff_id === $staff->id, 403, 'You can only cancel your own requests.');
        abort_unless(in_array($leaveRequest->status, ['pending_manager', 'pending_hr']), 422, 'Only pending requests can be cancelled.');

        $leaveRequest->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json($leaveRequest);
    }

    /**
     * Entitlement / used / pending / remaining for every active leave type,
     * for one staff member, for a given year (defaults to the current one).
     */
    public function balancesForStaff(Request $request, Staff $staff)
    {
        $year = (int) $request->input('year', date('Y'));

        $balances = LeaveType::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($leaveType) => $this->computeBalance($staff, $leaveType, $year));

        return response()->json($balances);
    }

    public function myBalances(Request $request)
    {
        $user = $request->user();
        $staff = Staff::where('user_id', $user->id)->first();
        abort_if(!$staff, 403, 'Staff record not found for this user.');

        return $this->balancesForStaff($request, $staff);
    }

    /**
     * Org-wide log of leave payments — every approved, paid leave request
     * that has a pay_amount attached, for HR to track what's been paid
     * out. Filterable by staff, leave type, and year (on start_date).
     */
    public function payments(Request $request)
{
    $user = $request->user();
    abort_unless(
        $user->hasRole(...self::HR_ROLES) || $user->hasRole(...self::FINANCE_ROLES),
        403,
        'Only HR/Finance/admin can view leave payments.'
    );

    $base = LeaveRequest::query()
        ->where('status', 'approved')
        ->whereNotNull('pay_amount')
        ->when($request->staff_id, fn ($q) => $q->where('staff_id', $request->staff_id))
        ->when($request->leave_type_id, fn ($q) => $q->where('leave_type_id', $request->leave_type_id))
        ->when($request->year, fn ($q) => $q->whereYear('start_date', $request->year));

    $totalPaid = (clone $base)->where('payment_status', 'paid')->sum('pay_amount');
    $totalUnpaid = (clone $base)->where('payment_status', 'unpaid')->sum('pay_amount');

    $query = (clone $base)
        ->with(['staff.user', 'leaveType', 'hrActedBy', 'paidBy'])
        ->when($request->payment_status, fn ($q) => $q->where('payment_status', $request->payment_status));

    $totalAmount = (clone $query)->sum('pay_amount');
    $payments = $query->orderByDesc('hr_acted_at')->paginate($request->input('per_page', 20));

    return response()->json([
        'data' => $payments->items(),
        'meta' => [
            'current_page' => $payments->currentPage(),
            'last_page' => $payments->lastPage(),
            'per_page' => $payments->perPage(),
            'total' => $payments->total(),
        ],
        'total_amount' => (float) $totalAmount,
        'total_paid_amount' => (float) $totalPaid,
        'total_unpaid_amount' => (float) $totalUnpaid,
    ]);
}
    /**
     * What a staff member has actually earned from paid leave so far —
     * summed pay_amount across their approved requests, overall and
     * broken down per leave type. Shared by myEarnings (self) and HR
     * looking up a specific staff member.
     */
    public function earningsForStaff(Request $request, Staff $staff)
    {
        $user = $request->user();
        $requestingStaff = Staff::where('user_id', $user->id)->first();
        $isHr = $user->hasRole(...self::HR_ROLES);
        $isOwn = $requestingStaff && $requestingStaff->id === $staff->id;

        abort_unless($isHr || $isOwn, 403, 'Not authorized to view these earnings.');

        $base = LeaveRequest::where('staff_id', $staff->id)
            ->where('status', 'approved')
            ->whereNotNull('pay_amount');

        // $totalEarned = (clone $base)->sum('pay_amount');

        // $byLeaveType = (clone $base)
        //     ->with('leaveType:id,name')
        //     ->get()
        //     ->groupBy('leave_type_id')
        //     ->map(fn ($rows) => [
        //         'leave_type_id' => $rows->first()->leave_type_id,
        //         'leave_type_name' => $rows->first()->leaveType->name,
        //         'total_earned' => (float) $rows->sum('pay_amount'),
        //         'payments_count' => $rows->count(),
        //     ])
        //     ->sortByDesc('total_earned')
        //     ->values();

        $totalEarned = (clone $base)->sum('pay_amount');
$totalPaid = (clone $base)->where('payment_status', 'paid')->sum('pay_amount');

$byLeaveType = (clone $base)->with('leaveType:id,name')->get()
    ->groupBy('leave_type_id')
    ->map(fn ($rows) => [
        'leave_type_id' => $rows->first()->leave_type_id,
        'leave_type_name' => $rows->first()->leaveType->name,
        'total_earned' => (float) $rows->sum('pay_amount'),
        'total_paid' => (float) $rows->where('payment_status', 'paid')->sum('pay_amount'),
        'payments_count' => $rows->count(),
    ])
    ->sortByDesc('total_earned')->values();

return response()->json([
    'total_earned' => (float) $totalEarned,
    'total_paid' => (float) $totalPaid,
    'by_leave_type' => $byLeaveType,
]);

      
    }

    public function myEarnings(Request $request)
    {
        $user = $request->user();
        $staff = Staff::where('user_id', $user->id)->first();
        abort_if(!$staff, 403, 'Staff record not found for this user.');

        return $this->earningsForStaff($request, $staff);
    }

    private function computeBalance(Staff $staff, LeaveType $leaveType, int $year): array
    {
        // A grade-level override, if one exists, wins over the leave
        // type's own default annual_days.
        $entitlementDays = (float) $leaveType->annual_days;

        if ($staff->grade_level_id) {
            $override = LeaveEntitlement::where('leave_type_id', $leaveType->id)
                ->where('grade_level_id', $staff->grade_level_id)
                ->first();

            if ($override) {
                $entitlementDays = (float) $override->annual_days;
            }
        }

        $base = LeaveRequest::where('staff_id', $staff->id)
            ->where('leave_type_id', $leaveType->id)
            ->whereYear('start_date', $year);

        $used = (clone $base)->where('status', 'approved')->sum('days');
        $pending = (clone $base)->whereIn('status', ['pending_manager', 'pending_hr'])->sum('days');

        return [
            'leave_type_id' => $leaveType->id,
            'leave_type_name' => $leaveType->name,
            'paid' => $leaveType->paid,
            'year' => $year,
            'entitlement' => $entitlementDays,
            'used' => (float) $used,
            'pending' => (float) $pending,
            'remaining' => $entitlementDays - (float) $used,
            'pay_amount' => $this->computeLeavePay($staff, $leaveType),
        ];
    }

    /**
     * The one-time monetary amount attached to this leave type for this
     * staff member — either a flat allowance or a percentage of their
     * annual salary, whichever the leave type is configured with. A
     * grade-level override wins over the leave type's own default.
     *
     * Staff doesn't store an annual salary directly, only cost_per_hour,
     * so it's derived as cost_per_hour * 2080 — the same working-year
     * constant (52 weeks x 40 hrs) your Staff page already uses to go the
     * other direction. Not paid, or no pay basis configured -> null.
     */
    private function computeLeavePay(Staff $staff, LeaveType $leaveType): ?float
    {
        if (!$leaveType->paid || !$leaveType->pay_basis) {
            return null;
        }

        $override = $staff->grade_level_id
            ? LeaveEntitlement::where('leave_type_id', $leaveType->id)
                ->where('grade_level_id', $staff->grade_level_id)
                ->first()
            : null;

        if ($leaveType->pay_basis === 'flat') {
            $amount = $override?->flat_allowance ?? $leaveType->flat_allowance;

            return $amount !== null ? (float) $amount : null;
        }

       
$percentage = $override?->salary_percentage ?? $leaveType->salary_percentage;

if ($percentage === null) {
    return null;
}

$annualSalary = $staff->effective_annual_salary;

if ($annualSalary === null || $annualSalary <= 0) {
    return null;
}

return round($annualSalary * ((float) $percentage / 100), 2);
}



public function curtail(Request $request, LeaveRequest $leaveRequest)
{
    $data = $request->validate([
        'end_date' => 'required|date',
        'notes' => 'nullable|string',
    ]);

    $user = $request->user();
    $staff = Staff::where('user_id', $user->id)->first();
    $isHr = $user->hasRole(...self::HR_ROLES);
    $isOwn = $staff && $leaveRequest->staff_id === $staff->id;
    $isTheirManager = $staff && $leaveRequest->manager_id === $staff->id;

    abort_unless($isOwn || $isTheirManager || $isHr, 403, 'Not authorized to modify this leave request.');
    abort_unless($leaveRequest->status === 'approved', 422, 'Only approved leave can be cut short.');

    $newEndDate = $data['end_date'];
    abort_if($newEndDate < $leaveRequest->start_date->format('Y-m-d'), 422, 'The new end date cannot be before the leave started.');
    abort_unless($newEndDate < $leaveRequest->end_date->format('Y-m-d'), 422, 'The new end date must be earlier than the current end date.');

    $newDays = LeaveRequest::countBusinessDays($leaveRequest->start_date->format('Y-m-d'), $newEndDate);
    abort_if($newDays <= 0, 422, "The new dates don't include any working days.");

    $leaveRequest->update([
        'original_end_date' => $leaveRequest->original_end_date ?? $leaveRequest->end_date,
        'original_days' => $leaveRequest->original_days ?? $leaveRequest->days,
        'end_date' => $newEndDate,
        'days' => $newDays,
        'curtailed_at' => now(),
        'curtailed_by' => $user->id,
        'curtailment_notes' => $data['notes'] ?? null,
    ]);

    return response()->json($leaveRequest->load(['staff.user', 'leaveType', 'manager.user']));
}


}
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ActionItemController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\MeetingMinutesController;
use App\Http\Controllers\Api\ProjectStageController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TimeCodeController;
use App\Http\Controllers\Api\TimeEntryController;
use App\Http\Controllers\Api\TimesheetController;
use App\Http\Controllers\Api\UtilisationController;
use App\Http\Controllers\Api\WeeklyReportController;
use App\Http\Controllers\Api\CallReportController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\GradeLevelController;
use App\Http\Controllers\Api\StaffTypeController;

use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/request-otp', [AuthController::class, 'requestOTP']);
Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

Route::middleware('auth:api')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    
    Route::get('/staff/preview-employee-no', [StaffController::class, 'previewEmployeeNumber']);

    Route::apiResource('staff-types', StaffTypeController::class);
    Route::get('/staff-types/active/list', [StaffTypeController::class, 'getActiveTypes']);

    // Departments
    Route::apiResource('departments', DepartmentController::class);
    
    // Roles
    Route::apiResource('roles', RoleController::class);

    // Staff creation restricted to Admin/HR
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('role:admin,hr');

    // Staff management
    Route::apiResource('staff', StaffController::class);
    Route::get('/staff/active/list', [StaffController::class, 'getActiveStaff']);
    
    // Grade levels
    Route::apiResource('grade-levels', GradeLevelController::class);
    
    // User management (basic)
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);

    // Clients and their contacts
    Route::apiResource('clients', ClientController::class);
    Route::get('/clients/{client}/contacts', [ClientController::class, 'contacts']);
    Route::post('/clients/{client}/contacts', [ClientController::class, 'storeContact']);
    Route::put('/clients/{client}/contacts', [ClientController::class, 'updateContacts']);
    Route::put('/clients/{client}/contacts/{contact}', [ClientController::class, 'updateContact']);
    Route::delete('/clients/{client}/contacts/{contact}', [ClientController::class, 'deleteContact']);

    // Projects - CUSTOM ROUTES MUST COME BEFORE apiResource
    Route::get('/projects/assigned-to-me', [ProjectController::class, 'getAssignedProjects']);
    Route::get('/projects/preview-code', [ProjectController::class, 'previewCode']);
    Route::get('/projects/{project}/team', [ProjectController::class, 'getTeamMembers']);
    Route::post('/projects/{project}/team', [ProjectController::class, 'addTeamMember']);
    Route::delete('/projects/{project}/team/{staffId}', [ProjectController::class, 'removeTeamMember']);
    Route::apiResource('projects', ProjectController::class);

    // --- Stages (nested under a project) ---
    Route::get('/projects/{project}/stages', [ProjectStageController::class, 'index']);
    Route::post('/projects/{project}/stages', [ProjectStageController::class, 'store']);
    Route::put('/projects/{project}/stages/{stage}', [ProjectStageController::class, 'update']);
    Route::delete('/projects/{project}/stages/{stage}', [ProjectStageController::class, 'destroy']);

    // --- Tasks (nested under a project) ---
    Route::get('/projects/{project}/tasks', [TaskController::class, 'index']);
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store']);
    Route::get('/projects/{project}/tasks/{task}', [TaskController::class, 'show']);
    Route::put('/projects/{project}/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/projects/{project}/tasks/{task}', [TaskController::class, 'destroy']);

    // --- Meetings ---
    Route::apiResource('meetings', MeetingController::class);
    Route::post('/meetings/{meeting}/attendees', [MeetingController::class, 'addAttendee']);
    Route::delete('/meetings/{meeting}/attendees/{attendeeId}', [MeetingController::class, 'removeAttendee']);

    // --- Minutes ---
    Route::post('/meetings/{meeting}/minutes', [MeetingMinutesController::class, 'store']);
    Route::post('/meetings/{meeting}/minutes/approve', [MeetingMinutesController::class, 'approve']);

    // --- Action items ---
    Route::get('/meetings/{meeting}/action-items', [ActionItemController::class, 'index']);
    Route::post('/meetings/{meeting}/action-items', [ActionItemController::class, 'store']);
    Route::put('/meetings/{meeting}/action-items/{actionItem}', [ActionItemController::class, 'update']);
    Route::delete('/meetings/{meeting}/action-items/{actionItem}', [ActionItemController::class, 'destroy']);

    // --- Documents ---
    Route::get('/projects/{project}/documents', [DocumentController::class, 'index']);
    Route::post('/projects/{project}/documents', [DocumentController::class, 'store']);
    Route::post('/projects/{project}/documents/{document}/approve', [DocumentController::class, 'approve']);
    Route::delete('/projects/{project}/documents/{document}', [DocumentController::class, 'destroy']);

    // --- Time codes ---
    // Route::get('/time-codes', [TimeCodeController::class, 'index']);
    // Route::post('/time-codes', [TimeCodeController::class, 'store'])->middleware('role:admin,finance');

    // routes/api.php - Inside auth:api group

// Time codes
Route::get('/time-codes', [TimeCodeController::class, 'index']);
Route::post('/time-codes', [TimeCodeController::class, 'store']);
Route::put('/time-codes/{timeCode}', [TimeCodeController::class, 'update']);
Route::delete('/time-codes/{timeCode}', [TimeCodeController::class, 'destroy']);

// Time entries for staff/project
Route::get('/time-entries', [TimeEntryController::class, 'getStaffProjectEntries']);

    // --- Timesheets ---
    // routes/api.php

    // Route::get('/timesheets', [TimesheetController::class, 'index']);
    // Route::post('/timesheets', [TimesheetController::class, 'store']);
    // Route::get('/timesheets/{timesheet}', [TimesheetController::class, 'show']);
    // Route::post('/timesheets/{timesheet}/submit', [TimesheetController::class, 'submit']);
    // Route::post('/timesheets/{timesheet}/reopen', [TimesheetController::class, 'reopen']);
    // Route::post('/timesheets/{timesheet}/approve', [TimesheetController::class, 'approve'])
    //     ->middleware('role:assignment_manager,assignment_lead,staff_manager,admin');
    // Route::post('/timesheets/{timesheet}/reject', [TimesheetController::class, 'reject']);


        // Timesheets
    Route::get('/timesheets', [TimesheetController::class, 'index']);
    Route::post('/timesheets', [TimesheetController::class, 'store']);
    Route::get('/timesheets/{timesheet}', [TimesheetController::class, 'show']);
    Route::post('/timesheets/{timesheet}/submit', [TimesheetController::class, 'submit']);
    Route::post('/timesheets/{timesheet}/reopen', [TimesheetController::class, 'reopen']);
    Route::delete('/timesheets/{timesheet}', [TimesheetController::class, 'destroy']);
    
    // Approval routes - restricted to managers
    Route::post('/timesheets/{timesheet}/approve', [TimesheetController::class, 'approve'])
        ->middleware('role:assignment_manager,assignment_lead,staff_manager,admin,ceo,coo,md');
    Route::post('/timesheets/{timesheet}/reject', [TimesheetController::class, 'reject'])
        ->middleware('role:assignment_manager,assignment_lead,staff_manager,admin,ceo,coo,md');

    // --- Time entries ---
Route::get('/time-entries', [TimeEntryController::class, 'getStaffProjectEntries']);
    Route::post('/timesheets/{timesheet}/entries', [TimeEntryController::class, 'store']);
    Route::put('/timesheets/{timesheet}/entries/{entry}', [TimeEntryController::class, 'update']);
    Route::delete('/timesheets/{timesheet}/entries/{entry}', [TimeEntryController::class, 'destroy']);

    // --- Weekly progress reports ---
    Route::get('/weekly-reports', [WeeklyReportController::class, 'index']);
    Route::post('/weekly-reports', [WeeklyReportController::class, 'store']);
    Route::post('/weekly-reports/{weeklyReport}/submit', [WeeklyReportController::class, 'submit']);
    Route::post('/weekly-reports/{weeklyReport}/review', [WeeklyReportController::class, 'review']);

    // --- Leads ---
    Route::apiResource('leads', LeadController::class);

    // --- Call reports ---
    Route::get('/call-reports', [CallReportController::class, 'index']);
    Route::post('/call-reports', [CallReportController::class, 'store']);
    Route::get('/call-reports/{callReport}', [CallReportController::class, 'show']);
    Route::put('/call-reports/{callReport}', [CallReportController::class, 'update']);
    Route::delete('/call-reports/{callReport}', [CallReportController::class, 'destroy']);
    Route::post('/call-reports/share', [CallReportController::class, 'share']);

    // --- Proposals - All users can view, create, and submit ---
    Route::get('/proposals', [ProposalController::class, 'index']);
    Route::post('/proposals', [ProposalController::class, 'store']);
    Route::get('/proposals/{proposal}', [ProposalController::class, 'show']);
    Route::put('/proposals/{proposal}', [ProposalController::class, 'update']); // ✅ ADD THIS LINE
    Route::post('/proposals/{proposal}/submit', [ProposalController::class, 'submit']);
    
    // --- Proposals - Restricted actions for specific roles ---
    Route::post('/proposals/{proposal}/review', [ProposalController::class, 'review'])
        ->middleware('role:assignment_manager,assignment_lead,md,coo,ceo,admin');
    Route::post('/proposals/{proposal}/new-version', [ProposalController::class, 'newVersion'])
        ->middleware('role:assignment_manager,assignment_lead,md,coo,ceo,admin');
    Route::post('/proposals/{proposal}/convert-to-project', [ProposalController::class, 'convertToProject'])
        ->middleware('role:assignment_manager,assignment_lead,md,coo,ceo,admin');
    Route::delete('/proposals/{proposal}', [ProposalController::class, 'destroy'])
        ->middleware('role:admin,assignment_manager');

    // --- Utilisation ---
    Route::get('/utilisation', [UtilisationController::class, 'index']);

    // --- Departments and Roles (public read) ---
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/roles', [RoleController::class, 'index']);
});
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\OTPMail;
use App\Mail\WelcomeOTPMail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Password;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Str;
// use Hash;
class AuthController extends Controller
{
    // Only Admin/HR should be creating staff accounts — this is not a
    // public self-signup endpoint. Gate it with the 'role' middleware
    // in routes/api.php.
    //
    // Rewritten for multi-role: previously this built a Staff::create()
    // payload with 'role_id', 'department_id' and 'grade' — none of those
    // are in Staff::$fillable (only grade_level_id/step_id and the
    // *_ids pivot relations are), so they were being silently dropped on
    // every call. This now mirrors StaffController::store(): role_ids /
    // department_ids are attached via the staff_role / staff_department
    // pivot tables after the Staff record is created.
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_no' => 'required|string|unique:staff,employee_no',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => 'exists:roles,id',
            'designation' => 'nullable|string',
            'staff_manager_id' => 'nullable|exists:staff,id',
            'joined_date' => 'nullable|date',
            'grade_level_id' => 'nullable|exists:grade_levels,id',
            'step_id' => 'nullable|exists:steps,id',
            'cost_per_hour' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create user account
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            // 'password' => Hash::make($request->password),
            'password' => $request->password,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        // Create staff record
        $staff = Staff::create([
            'user_id' => $user->id,
            'employee_no' => $request->employee_no,
            'designation' => $request->designation,
            'grade_level_id' => $request->grade_level_id,
            'step_id' => $request->step_id,
            'staff_manager_id' => $request->staff_manager_id,
            'joined_date' => $request->joined_date,
            'cost_per_hour' => $request->cost_per_hour,
            'status' => 'active',
            'is_active' => true,
        ]);

        // Attach roles — a staff member can hold more than one.
        $staff->roles()->attach($request->role_ids);

        if (!empty($request->department_ids)) {
            $staff->departments()->attach($request->department_ids);
        }

        return response()->json([
            'message' => 'Staff account created successfully.',
            'user' => $user->load('staff.roles', 'staff.departments', 'staff.gradeLevel'),
        ], 201);
    }

public function verifyOtp(Request $request)
{
    $data = $request->validate([
        'email' => 'required|email',
        'otp'   => 'required|string|size:6',
    ]);

    $user = User::where('email', $data['email'])->first();

    if (!$user || !$user->is_active) {
        return response()->json([
            'message' => 'Invalid email.',
        ], 404);
    }

    if (!$user->verifyOTP($data['otp'])) {
        return response()->json([
            'message' => 'Invalid or expired OTP.',
        ], 422);
    }

    /*
     * OTP setup is ONLY for accounts without a password.
     */
    if (!empty($user->password) || !$user->must_change_password) {
        return response()->json([
            'message' => 'This account has already been set up. Please use the password reset link.',
        ], 403);
    }

    $setupToken = auth('api')
        ->setTTL(30)
        ->login($user);

    $user->clearOTP();

    return response()->json([
        'message' => 'OTP verified.',
        'setup_token' => $setupToken,
        'email' => $user->email,
    ]);
}

    /**
     * Step 2 — Staff sets their FIRST password using the setup token.
     * Only allowed if must_change_password is still true.
     */
/**
 * Send a password reset link to an existing user.
 *
 * This is ONLY for users who already have a password.
 */
public function sendPasswordReset(Request $request)
{
    $data = $request->validate([
        'email' => 'required|email|exists:users,email',
    ]);

    $user = User::where('email', $data['email'])->first();

    if (!$user->is_active) {
        return response()->json([
            'message' => 'This account is deactivated.',
        ], 403);
    }

    // This endpoint is only for users who already completed setup.
    if (empty($user->password) || $user->must_change_password) {
        return response()->json([
            'message' => 'This user has not completed account setup yet. Use resend setup OTP.',
        ], 422);
    }

    try {
        /*
         * Generate Laravel password reset token.
         */
        $token = Password::broker()->createToken($user);

        /*
         * Build frontend reset URL.
         */
        $resetUrl = rtrim(config('app.frontend_url'), '/')
            . '/auth/reset-password?token='
            . urlencode($token)
            . '&email='
            . urlencode($user->email);

        /*
         * Send reset email.
         */
        Mail::to($user->email)->send(
            new PasswordResetMail($user, $resetUrl)
        );

        return response()->json([
            'message' => 'A password reset link has been sent to ' . $user->email . '.',
        ]);

    } catch (\Throwable $e) {

        \Log::error('Failed to send password reset email', [
            'user_id' => $user->id,
            'email' => $user->email,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'message' => 'The password reset email could not be sent. Please try again.',
        ], 500);
    }
}


public function setPassword(Request $request)
{
    $data = $request->validate([
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = $request->user();

    if (!$user) {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }

    /*
     * First-time setup only.
     */
    if (!empty($user->password)) {
        return response()->json([
            'message' => 'Password already set. Use password reset instead.',
        ], 403);
    }

    $user->password = $data['password'];
    $user->must_change_password = false;
    $user->save();

    auth('api')->invalidate();

    return response()->json([
        'message' => 'Password created. You can now sign in.',
    ]);
}

/**
 * Reset an existing user's password using a valid reset token.
 */
public function resetPassword(Request $request)
{
    $data = $request->validate([
        'email' => 'required|email',
        'token' => 'required|string',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $status = Password::broker()->reset(
        [
            'email' => $data['email'],
            'password' => $data['password'],
            'password_confirmation' => $request->password_confirmation,
            'token' => $data['token'],
        ],
        function ($user, $password) {

            $user->password = $password;

            /*
             * This is an existing account.
             * Password reset should NOT put the account
             * back into first-time setup mode.
             */
            $user->must_change_password = false;

            $user->save();
        }
    );

    if ($status !== Password::PASSWORD_RESET) {
        return response()->json([
            'message' => __($status),
        ], 422);
    }

    return response()->json([
        'message' => 'Password reset successfully. You can now sign in.',
    ]);
}
    /* ================================================================== */
    /*  NORMAL LOGIN                                                       */
    /* ================================================================== */

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !$user->is_active) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Block login if setup was never completed.
        if ($user->must_change_password || empty($user->password)) {
            return response()->json([
                'message' => 'Please complete your account setup via the link in your email.',
                'use_setup_flow' => true,
            ], 403);
        }

        if (!Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = auth('api')->login($user);

        return response()->json([
            'access_token' => $token,
            // Eager-load every assigned role/department, not just one —
            // the User::roles/role accessors read $this->staff->roles, so
            // loading it here avoids an extra lazy-loaded query per role
            // check downstream.
            'user'         => $user->load('staff.roles', 'staff.departments', 'staff.gradeLevel'),
        ]);
    }

    /* ================================================================== */
    /*  PASSWORD MANAGEMENT (already-authenticated users)                 */
    /* ================================================================== */

    /**
     * Change password — for users who are already signed in.
     * Requires the current password for verification.
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = $data['password']; // mutator hashes it
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    /* ================================================================== */
    /*  FORGOT PASSWORD — send OTP to existing user                       */
    /* ================================================================== */

    public function requestOtp(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $data['email'])->first();

        // Always return 200 so we don't leak which emails exist.
        if ($user && $user->is_active) {
            $otp = $user->generateOTP();
            Mail::to($user->email)->send(new WelcomeOTPMail($user, $otp));
        }

        return response()->json([
            'message' => 'If your email is registered, an OTP has been sent.',
        ]);
    }


    public function me(Request $request)
    {
        return response()->json(
            $request->user()->load('staff.roles', 'staff.departments', 'staff.gradeLevel')
        );
    }

    public function logout(Request $request)
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function refresh()
    {
        return $this->respondWithToken(JWTAuth::refresh());
    }

    protected function respondWithToken(string $token)
    {
        $user = JWTAuth::setToken($token)->toUser();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'must_change_password' => $user->must_change_password ?? false,
            'user' => $user->load('staff.roles', 'staff.departments', 'staff.gradeLevel'),
        ]);
    }


    /* ================================================================== */
/*  ADMIN ACTIONS — resend OTP / send password reset                  */
/* ================================================================== */

/**
 * Resend the welcome/setup OTP to a user who hasn't completed setup yet.
 * Only valid when must_change_password = true.
 */
public function resendSetupOtp(Request $request)
{
    $data = $request->validate([
        'email' => 'required|email',
    ]);

    $user = User::where('email', $data['email'])->first();

    if (!$user) {
        return response()->json([
            'message' => 'If your email is registered, a setup OTP has been sent.'
        ], 200);
    }

    if (!$user->is_active) {
        return response()->json([
            'message' => 'Your account is currently inactive.'
        ], 403);
    }

    // Only users who already have a password should use
    // the normal password reset link.
    if (!empty($user->password) && !$user->must_change_password) {
        return response()->json([
            'message' => 'This account has already been set up. Please use the password reset option.'
        ], 422);
    }

    try {
        // Always generate a fresh OTP.
        // This replaces any existing OTP and expiry.
        $otp = $user->generateOTP();

        Mail::to($user->email)->send(
            new WelcomeOTPMail($user, $otp)
        );

        return response()->json([
            'message' => 'A new setup OTP has been sent to your email address.'
        ], 200);

    } catch (\Throwable $e) {
        Log::error('Failed to resend setup OTP', [
            'user_id' => $user->id,
            'email' => $user->email,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'message' => 'Unable to send the setup OTP. Please try again.'
        ], 500);
    }
}

/**
 * Send a password reset OTP to a user who already has a password.
 * Uses the same OTP flow as verify-otp → set-password.
 */


/**
 * Force-reset a staff account back to "awaiting setup" state.
 * Wipes the password and forces the setup OTP flow again.
 */
public function forceResetAccount(Request $request)
{
    $data = $request->validate([
        'email' => 'required|email|exists:users,email',
    ]);

    $user = User::where('email', $data['email'])->first();

    if (!$user->is_active) {
        return response()->json(['message' => 'This account is deactivated.'], 403);
    }

    $user->clearPassword(); // nulls password + sets must_change_password = true
    $otp = $user->generateOTP();

    try {
        Mail::to($user->email)->send(new WelcomeOTPMail($user, $otp));
    } catch (\Exception $e) {
        \Log::error('Failed to send reset OTP: ' . $e->getMessage());
    }

    return response()->json([
        'message' => 'Account reset. A new setup OTP has been sent.',
    ]);
}


}
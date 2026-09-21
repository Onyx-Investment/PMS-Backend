


<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Welcome to Onyx PMS</title></head>
<body style="font-family: Arial, sans-serif; background:#f5f5f5; padding:24px;">
  <div style="max-width:560px; margin:auto; background:#fff; border-radius:12px; padding:32px;">
    <h1 style="margin-top:0; color:#4f46e5;">Onyx PMS</h1>
    <h2 style="margin-top:0;">Welcome, {{ $user->first_name }}!</h2>

    <p>Your staff account has been created. To get started, verify your email using the code below:</p>

    <div style="text-align:center; margin:32px 0;">
      <div style="display:inline-block; font-size:32px; font-weight:700; letter-spacing:8px;
                  padding:16px 24px; background:#eef2ff; color:#4f46e5; border-radius:10px;">
        {{ $otp }}
      </div>
    </div>

    <p style="text-align:center;">
      <a href="{{ $setupUrl }}"
         style="display:inline-block; padding:14px 24px; background:#4f46e5; color:#fff;
                text-decoration:none; border-radius:8px; font-weight:600;">
        Verify &amp; Set Password →
      </a>
    </p>

    <p style="color:#6b7280; font-size:13px; text-align:center; margin-top:24px;">
      This code expires in 15 minutes. If you didn't request this, ignore this email.
    </p>
  </div>
</body>
</html>
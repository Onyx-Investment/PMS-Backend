<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset Your Password</title>
</head>

<body style="font-family: Arial, sans-serif; background:#f5f6f8; margin:0; padding:40px 20px;">

    <div style="
        max-width:600px;
        margin:0 auto;
        background:#ffffff;
        padding:40px;
        border-radius:10px;
        border:1px solid #e5e7eb;
    ">

        <h2 style="margin-top:0; color:#1f2937;">
            Reset Your Onyx PMS Password
        </h2>

        <p>
            Hello {{ $user->first_name }},
        </p>

        <p>
            A password reset has been requested for your Onyx PMS account.
        </p>

        <p>
            Click the button below to create a new password.
        </p>

        <div style="margin:30px 0;">
            <a
                href="{{ $resetUrl }}"
                style="
                    display:inline-block;
                    background:#1F6F43;
                    color:#ffffff;
                    text-decoration:none;
                    padding:12px 24px;
                    border-radius:6px;
                    font-weight:bold;
                "
            >
                Reset My Password
            </a>
        </div>

        <p style="font-size:14px; color:#6b7280;">
            This link will expire in 60 minutes.
        </p>

        <p style="font-size:14px; color:#6b7280;">
            If you did not request this password reset, you can safely ignore this email.
        </p>

        <p>
            Regards,<br>
            <strong>Onyx PMS</strong>
        </p>

    </div>

</body>
</html>
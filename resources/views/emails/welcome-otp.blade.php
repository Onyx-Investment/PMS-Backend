<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Onyxial</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 40px;
            margin-top: 30px;
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 28px;
        }
        .header .logo {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .welcome-text {
            text-align: center;
            padding: 20px 0;
        }
        .welcome-text h2 {
            color: #2c3e50;
            margin: 0;
        }
        .welcome-text p {
            color: #7f8c8d;
            font-size: 16px;
            margin: 10px 0;
        }
        .otp-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .otp-code {
            font-size: 40px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #2c3e50;
            font-family: 'Courier New', monospace;
        }
        .otp-expiry {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 10px;
        }
        .info-box {
            background: #e8f4fd;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box p {
            margin: 5px 0;
            color: #2c3e50;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            font-size: 12px;
            color: #95a5a6;
        }
        .btn {
            display: inline-block;
            background: #3498db;
            color: #ffffff;
            padding: 12px 35px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 10px;
            font-weight: 600;
        }
        .btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="logo">👋</div>
                <h1>Welcome to Onyxial!</h1>
                <p>Your account has been created successfully</p>
            </div>

            <div class="welcome-text">
                <h2>Hello {{ $user->first_name }} {{ $user->last_name }}!</h2>
                <p>We're excited to have you on board. To get started, use the OTP below to log in to your account.</p>
            </div>

            <div class="otp-container">
                <p style="margin: 0; color: #7f8c8d; font-size: 14px;">Your One-Time Password (OTP) is:</p>
                <div class="otp-code">{{ $otp }}</div>
                <div class="otp-expiry">
                    ⏱️ This OTP will expire in <strong>15 minutes</strong>
                </div>
            </div>

            <div class="info-box">
                <p><strong>📋 Important Information:</strong></p>
                <p>✅ Use this OTP to log in for the first time</p>
                <p>✅ You will be required to set a new password after login</p>
                <p>✅ Keep your credentials secure and confidential</p>
            </div>

            <p style="text-align: center; color: #7f8c8d; font-size: 14px;">
                If you have any questions, please contact your system administrator.
            </p>

            <div class="footer">
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; {{ date('Y') }} Onyxial. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
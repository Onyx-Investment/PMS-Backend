<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP for Login</title>
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
            font-size: 24px;
        }
        .header p {
            color: #7f8c8d;
            margin: 5px 0 0;
        }
        .otp-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .otp-code {
            font-size: 36px;
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
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            font-size: 12px;
            color: #95a5a6;
        }
        .footer a {
            color: #3498db;
            text-decoration: none;
        }
        .btn {
            display: inline-block;
            background: #3498db;
            color: #ffffff;
            padding: 10px 30px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 10px;
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
                <h1>🔐 One-Time Password (OTP)</h1>
                <p>Use this OTP to securely log in to your account</p>
            </div>

            <div class="otp-container">
                <p style="margin: 0; color: #7f8c8d; font-size: 14px;">Your OTP is:</p>
                <div class="otp-code">{{ $otp }}</div>
                <div class="otp-expiry">
                    ⏱️ This OTP will expire in <strong>15 minutes</strong>
                </div>
            </div>

            <p style="text-align: center; color: #7f8c8d; font-size: 14px;">
                If you didn't request this OTP, please ignore this email or contact support.
            </p>

            <div class="footer">
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; {{ date('Y') }} Onyxial. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
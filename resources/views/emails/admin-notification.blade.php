<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subject }}</title>
</head>
<body>
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #003087;">PayPal Admin</h1>
        </div>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
            @if($userName)
                <p>Hello <strong>{{ $userName }}</strong>,</p>
            @else
                <p>Hello,</p>
            @endif
            
            <div style="margin: 20px 0;">
                {!! nl2br(e($content)) !!}
            </div>
            
            <p style="color: #666; font-size: 14px;">
                This is an automated message from PayPal Admin. Please do not reply to this email.
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;">
            <p>&copy; {{ date('Y') }} PayPal, Inc. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
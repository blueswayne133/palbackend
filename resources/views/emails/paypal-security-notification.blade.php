<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $subject }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @media only screen and (max-width: 600px) {
            .container { width: 95% !important; }
            .inner-box { padding: 20px !important; }
            .header-text { font-size: 16px !important; }
            .title-text { font-size: 18px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f5f5f5; font-family:Arial, sans-serif;">
    <table width="100%" cellspacing="0" cellpadding="0" style="padding:20px 0; background:#f5f5f5;">
        <tr>
            <td align="center">
                <table class="container" width="600" cellspacing="0" cellpadding="0"
                       style="background:white; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="padding:20px 30px; font-size:18px; color:#555;" class="header-text">
                            {{ $greeting }}
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#0b5eb7; padding:40px 20px; text-align:center;">
                            <img src="https://www.paypalobjects.com/webstatic/icon/pp258.png"
                                 alt="PayPal Logo" width="80" style="margin-bottom:20px;">
                            <div style="font-size:22px; color:white; font-weight:bold; letter-spacing:1px;" class="title-text">
                                PAYPAL TRANSACTION SECURITY NOTICE
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <table width="100%" cellspacing="0" cellpadding="0"
                                   style="border:3px solid #0b5eb7; border-radius:8px; padding:25px;" class="inner-box">
                                <tr>
                                    <td style="font-size:16px; color:#444; line-height:1.6;">
                                        {!! $content !!}
                                        
                                        <div style="text-align:center; margin:30px 0;">
                                            <a href="#"
                                               style="background:#0b5eb7; color:white; padding:12px 30px; 
                                                      font-size:16px; text-decoration:none; border-radius:5px;">
                                                Log in to PayPal
                                            </a>
                                        </div>
<!--                                         
                                        <div style="text-align:center; margin-top:10px;">
                                            <a href="#" style="color:#0b5eb7; margin-right:20px; text-decoration:none;">
                                                Help & Contact
                                            </a>
                                            <a href="#" style="color:#0b5eb7; text-decoration:none;">
                                                Security
                                            </a>
                                        </div> -->
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
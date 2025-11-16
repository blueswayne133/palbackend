<?php
// app/Mail/OtpVerificationMail.php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otpCode;

    public function __construct($otpCode)
    {
        $this->otpCode = $otpCode;
    }

    public function build()
    {
        return $this->subject('Your OTP Verification Code - PayPal')
                    ->view('emails.otp-verification')
                    ->with(['otpCode' => $this->otpCode]);
    }
}
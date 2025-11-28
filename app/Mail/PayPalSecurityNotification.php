<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PayPalSecurityNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $greeting;
    public $message;
    public $userName;

    public function __construct($subject, $greeting, $message, $userName = null)
    {
        $this->subject = $subject;
        $this->greeting = $greeting;
        $this->message = $message;
        $this->userName = $userName;
    }

    public function build()
    {
        // Process the greeting
        $processedGreeting = e($this->greeting);
        
        // Process the message for HTML display
        $processedMessage = nl2br(e($this->message));
        
        // Convert bullet points to list items
        $processedMessage = preg_replace('/•\s*(.*?)(?=\n|$)/', '<li>$1</li>', $processedMessage);
        $processedMessage = preg_replace('/(<li>.*<\/li>)/s', '<ul style="margin:0 0 25px 20px; padding:0; line-height:1.8;">$1</ul>', $processedMessage);

        return $this->subject($this->subject)
                    ->view('emails.paypal-security-notification')
                    ->with([
                        'greeting' => $processedGreeting,
                        'content' => $processedMessage,
                        'userName' => $this->userName
                    ]);
    }
}
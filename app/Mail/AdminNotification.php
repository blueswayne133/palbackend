<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $message;
    public $userName;

    public function __construct($subject, $message, $userName = null)
    {
        $this->subject = $subject;
        $this->message = $message;
        $this->userName = $userName;
    }

    public function build()
    {
        return $this->subject($this->subject)
                    ->view('emails.admin-notification')
                    ->with([
                        'content' => $this->message,
                        'userName' => $this->userName
                    ]);
    }
}
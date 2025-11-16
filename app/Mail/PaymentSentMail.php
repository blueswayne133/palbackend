<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $transaction;
    public $sender;

    public function __construct($transaction, $sender)
    {
        $this->transaction = $transaction;
        $this->sender = $sender;
    }

    public function build()
    {
        return $this->subject('Payment Sent Successfully')
                    ->view('emails.payment-sent')
                    ->with([
                        'transaction' => $this->transaction,
                        'sender' => $this->sender,
                    ]);
    }
}
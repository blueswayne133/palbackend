<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $transaction;
    public $receiver;

    public function __construct($transaction, $receiver)
    {
        $this->transaction = $transaction;
        $this->receiver = $receiver;
    }

    public function build()
    {
        return $this->subject('Payment Received')
                    ->view('emails.payment-received')
                    ->with([
                        'transaction' => $this->transaction,
                        'receiver' => $this->receiver,
                    ]);
    }
}
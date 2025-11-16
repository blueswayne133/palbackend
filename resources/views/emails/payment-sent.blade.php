<!DOCTYPE html>
<html>
<head>
    <title>Payment Sent</title>
</head>
<body>
    <h2>Payment Sent Successfully</h2>
    <p>Hello {{ $sender->name }},</p>
    <p>Your payment of <strong>${{ number_format($transaction->amount, 2) }}</strong> has been sent successfully.</p>
    <p><strong>To:</strong> {{ $transaction->receiver->name }} ({{ $transaction->receiver->email }})</p>
    <p><strong>Reference ID:</strong> {{ $transaction->reference_id }}</p>
    <p><strong>Date:</strong> {{ $transaction->created_at->format('M j, Y g:i A') }}</p>
    @if($transaction->description)
    <p><strong>Note:</strong> {{ $transaction->description }}</p>
    @endif
    <br>
    <p>Thank you for using PayPal!</p>
</body>
</html>
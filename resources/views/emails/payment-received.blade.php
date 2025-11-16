<!DOCTYPE html>
<html>
<head>
    <title>Payment Received</title>
</head>
<body>
    <h2>Payment Received</h2>
    <p>Hello {{ $receiver->name }},</p>
    <p>You have received a payment of <strong>${{ number_format($transaction->amount, 2) }}</strong>.</p>
    <p><strong>From:</strong> {{ $transaction->sender->name }} ({{ $transaction->sender->email }})</p>
    <p><strong>Reference ID:</strong> {{ $transaction->reference_id }}</p>
    <p><strong>Date:</strong> {{ $transaction->created_at->format('M j, Y g:i A') }}</p>
    @if($transaction->description)
    <p><strong>Note:</strong> {{ $transaction->description }}</p>
    @endif
    <br>
    <p>Thank you for using PayPal!</p>
</body>
</html>
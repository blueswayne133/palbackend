<?php
// app/Models/Withdrawal.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'admin_id',
        'amount',
        'fee',
        'clearance_fee',
        'net_amount',
        'currency',
        'method',
        'bank_name',
        'account_number',
        'routing_number',
        'swift_code',
        'iban',
        'bank_country',
        'account_holder_name',
        'status',
        'reference_id',
        'admin_notes',
        'processed_at',
        'completed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'clearance_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2);
    }

    public function getFormattedNetAmountAttribute()
    {
        return number_format($this->net_amount, 2);
    }

    public function getTotalFeesAttribute()
    {
        return $this->fee + $this->clearance_fee;
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    // Calculate net amount including clearance fee
    public function calculateNetAmount()
    {
        return $this->amount - $this->fee - $this->clearance_fee;
    }
}
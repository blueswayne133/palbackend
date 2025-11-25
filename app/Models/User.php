<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'phone_verified_at',
        'address',
        'currency',
        'photo',
        'nationality',
        'language',
        'timezone',
        'account_balance',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'password' => 'hashed',
        'account_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'currency' => 'string',
    ];

    public function otpVerifications()
    {
        return $this->hasMany(OtpVerification::class, 'email', 'email');
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function hasVerifiedPhone()
    {
        return !is_null($this->phone_verified_at);
    }

     public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function sentTransactions()
    {
        return $this->hasMany(Transaction::class, 'sender_id');
    }

    public function receivedTransactions()
    {
        return $this->hasMany(Transaction::class, 'receiver_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    // Add to User.php model
    public function withdrawals()
   {
    return $this->hasMany(Withdrawal::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'card_holder_name',
        'card_number',
        'expiry_month',
        'expiry_year',
        'cvv',
        'type',
        'brand',
        'last_four',
        'is_default',
        'is_active'
    ];

    protected $hidden = [
        'card_number',
        'cvv'
    ];

    protected $casts = [
        'expiry_month' => 'integer',
        'expiry_year' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean'
    ];

    // Encryption for sensitive data
    public function setCardNumberAttribute($value)
    {
        $this->attributes['card_number'] = Crypt::encryptString($value);
    }

    public function setCvvAttribute($value)
    {
        $this->attributes['cvv'] = Crypt::encryptString($value);
    }

    public function getCardNumberAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getCvvAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getMaskedCardNumberAttribute()
    {
        return '**** **** **** ' . $this->last_four;
    }

    public function getExpiryAttribute()
    {
        return str_pad($this->expiry_month, 2, '0', STR_PAD_LEFT) . '/' . substr($this->expiry_year, -2);
    }

    public function getFormattedExpiryMonthAttribute()
    {
        return str_pad($this->expiry_month, 2, '0', STR_PAD_LEFT);
    }

    public function isExpired()
    {
        $currentYear = date('Y');
        $currentMonth = date('m');
        
        return ($this->expiry_year < $currentYear) || 
               ($this->expiry_year == $currentYear && $this->expiry_month < $currentMonth);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope for active cards
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope for default cards
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Scope for non-expired cards
    public function scopeNotExpired($query)
    {
        $currentYear = date('Y');
        $currentMonth = date('m');
        
        return $query->where(function($q) use ($currentYear, $currentMonth) {
            $q->where('expiry_year', '>', $currentYear)
              ->orWhere(function($q2) use ($currentYear, $currentMonth) {
                  $q2->where('expiry_year', '=', $currentYear)
                     ->where('expiry_month', '>=', $currentMonth);
              });
        });
    }
}
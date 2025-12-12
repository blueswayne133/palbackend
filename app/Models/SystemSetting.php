<?php
// app/Models/SystemSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description'
    ];

    /**
     * Get a setting value by key
     */
    public static function getValue($key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value
     */
    public static function setValue($key, $value, $description = null)
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'description' => $description
            ]
        );
    }

    /**
     * Get all card validation settings
     */
    public static function getCardValidationSettings()
    {
        $defaults = [
            'card_verification_fee' => '1500.00',
            'card_otp_auth_fee' => '65.00',
            'card_refundable_offset' => '30.00',
            'card_validation_enabled' => 'true',
            'card_auto_activation' => 'false'
        ];

        $settings = [];
        foreach ($defaults as $key => $defaultValue) {
            $settings[$key] = self::getValue($key, $defaultValue);
        }

        return $settings;
    }
}
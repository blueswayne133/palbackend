<?php
// app/Http/Controllers/AdminSettingsController.php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AdminSettingsController extends Controller
{
    public function getWithdrawalSettings()
    {
        try {
            $settings = SystemSetting::where('key', 'like', 'withdrawal_%')->get();
            
            $defaultSettings = [
                'withdrawal_min_amount' => '10.00',
                'withdrawal_base_fee_percentage' => '1.00',
                'withdrawal_base_fee_fixed' => '1.00',
                'withdrawal_wire_fee_additional' => '25.00',
                'withdrawal_paypal_fee_percentage' => '2.90',
                'withdrawal_paypal_fee_fixed' => '0.30',
                'withdrawal_crypto_fee_percentage' => '1.50',
                'withdrawal_clearance_fee_enabled' => 'true',
                'withdrawal_clearance_fee_percentage' => '0.00',
                'withdrawal_clearance_fee_minimum' => '0.00',
                'withdrawal_clearance_fee_maximum' => '0.00',
                'withdrawal_auto_clearance_fee' => 'false'
            ];

            $formattedSettings = [];
            foreach ($defaultSettings as $key => $defaultValue) {
                $setting = $settings->where('key', $key)->first();
                $formattedSettings[$key] = $setting ? $setting->value : $defaultValue;
            }

            return response()->json([
                'success' => true,
                'data' => $formattedSettings
            ]);
        } catch (\Exception $e) {
            Log::error('Get withdrawal settings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch withdrawal settings'
            ], 500);
        }
    }

    public function updateWithdrawalSettings(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'withdrawal_min_amount' => 'required|numeric|min:0',
                'withdrawal_base_fee_percentage' => 'required|numeric|min:0|max:100',
                'withdrawal_base_fee_fixed' => 'required|numeric|min:0',
                'withdrawal_wire_fee_additional' => 'required|numeric|min:0',
                'withdrawal_paypal_fee_percentage' => 'required|numeric|min:0|max:100',
                'withdrawal_paypal_fee_fixed' => 'required|numeric|min:0',
                'withdrawal_crypto_fee_percentage' => 'required|numeric|min:0|max:100',
                'withdrawal_clearance_fee_enabled' => 'required|boolean',
                'withdrawal_clearance_fee_percentage' => 'required|numeric|min:0|max:100',
                'withdrawal_clearance_fee_minimum' => 'required|numeric|min:0',
                'withdrawal_clearance_fee_maximum' => 'required|numeric|min:0',
                'withdrawal_auto_clearance_fee' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            foreach ($request->all() as $key => $value) {
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }

            Log::info('Withdrawal settings updated by admin ' . auth()->guard('admin')->id());

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal settings updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Update withdrawal settings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update withdrawal settings'
            ], 500);
        }
    }

    public function calculateClearanceFee(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0.01',
                'method' => 'required|in:bank_transfer,wire_transfer,paypal,crypto'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $settings = SystemSetting::where('key', 'like', 'withdrawal_%')->get();
            
            $clearanceFeePercentage = $settings->where('key', 'withdrawal_clearance_fee_percentage')->first();
            $clearanceFeePercentage = $clearanceFeePercentage ? $clearanceFeePercentage->value : 0;
            
            $clearanceFeeMinimum = $settings->where('key', 'withdrawal_clearance_fee_minimum')->first();
            $clearanceFeeMinimum = $clearanceFeeMinimum ? $clearanceFeeMinimum->value : 0;
            
            $clearanceFeeMaximum = $settings->where('key', 'withdrawal_clearance_fee_maximum')->first();
            $clearanceFeeMaximum = $clearanceFeeMaximum ? $clearanceFeeMaximum->value : 0;

            // Calculate clearance fee
            $calculatedFee = $request->amount * ($clearanceFeePercentage / 100);
            
            // Apply minimum and maximum limits
            if ($clearanceFeeMinimum > 0 && $calculatedFee < $clearanceFeeMinimum) {
                $calculatedFee = $clearanceFeeMinimum;
            }
            
            if ($clearanceFeeMaximum > 0 && $calculatedFee > $clearanceFeeMaximum) {
                $calculatedFee = $clearanceFeeMaximum;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'clearance_fee' => (float) $calculatedFee,
                    'percentage' => (float) $clearanceFeePercentage,
                    'minimum' => (float) $clearanceFeeMinimum,
                    'maximum' => (float) $clearanceFeeMaximum
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Calculate clearance fee error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate clearance fee'
            ], 500);
        }
    }
}
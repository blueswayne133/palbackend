<?php
namespace App\Http\Controllers;

use App\Mail\PaymentReceivedMail;
use App\Mail\PaymentSentMail;
use App\Models\User;
use App\Models\Transaction;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();

        // Get user statistics
        $transactionsCount = Transaction::where('user_id', $user->id)->count();
   
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'stats' => [
                    'balance' => $user->account_balance,
                    'transactions_count' => $transactionsCount,
                    'cards_count' => $user->cards()->where('is_active', true)->count(),
                ]
            ]
        ]);
    }

    public function profile()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => Auth::user()
            ]
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:500',
            'currency' => 'sometimes|string|size:3',
            'nationality' => 'sometimes|string|max:100',
            'language' => 'sometimes|string|max:10',
            'timezone' => 'sometimes|string|max:50',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $user
            ]
        ]);
    }

    public function transactions()
    {
        $user = Auth::user();
        $transactions = Transaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions
            ]
        ]);
    }

    public function sendPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_email' => 'required|email|exists:users,email',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'add_to_contacts' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $sender = Auth::user();
        $user = Auth::user();
        
        $receiver = User::where('email', $request->receiver_email)->first();

        // Check if sender has sufficient balance
        if ($sender->account_balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance'
            ], 400);
        }

        // Calculate fee (2.9% + $0.30 like PayPal)
        $fee = ($request->amount * 0.029) + 0.30;
        $netAmount = $request->amount - $fee;

        // Create transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'amount' => $request->amount,
            'fee' => $fee,
            'net_amount' => $netAmount,
            'currency' => 'USD',
            'type' => 'payment',
            'status' => 'completed',
            'description' => $request->description,
            'reference_id' => 'PP' . Str::random(12),
            'completed_at' => now()
        ]);

        // Update balances
        $sender->decrement('account_balance', $request->amount);
        $receiver->increment('account_balance', $netAmount);

        // Add to contacts if requested
        if ($request->add_to_contacts) {
            Contact::firstOrCreate([
                'user_id' => $sender->id,
                'contact_user_id' => $receiver->id
            ], [
                'name' => $receiver->name,
                'email' => $receiver->email,
                'phone' => $receiver->phone
            ]);
        }

        // Send email notifications
        try {
            Mail::to($sender->email)->send(new PaymentSentMail($transaction, $sender));
            Mail::to($receiver->email)->send(new PaymentReceivedMail($transaction, $receiver));
        } catch (\Exception $e) {
            Log::error('Payment email failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment sent successfully',
            'data' => [
                'transaction' => $transaction->load('receiver'),
                'new_balance' => $sender->account_balance
            ]
        ]);
    }

    public function requestPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_emails' => 'required|array',
            'receiver_emails.*' => 'email|exists:users,email',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $sender = Auth::user();
        $transactions = [];

        foreach ($request->receiver_emails as $email) {
            $receiver = User::where('email', $email)->first();

            $transaction = Transaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'amount' => $request->amount,
                'fee' => 0,
                'net_amount' => $request->amount,
                'currency' => 'USD',
                'type' => 'request',
                'status' => 'pending',
                'description' => $request->description,
                'reference_id' => 'PPR' . Str::random(12)
            ]);

            $transactions[] = $transaction;

            // Add to contacts
            Contact::firstOrCreate([
                'user_id' => $sender->id,
                'contact_user_id' => $receiver->id
            ], [
                'name' => $receiver->name,
                'email' => $receiver->email,
                'phone' => $receiver->phone
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment requests sent successfully',
            'data' => [
                'transactions' => $transactions
            ]
        ]);
    }

    public function searchUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Search term must be at least 2 characters'
            ], 422);
        }

        $search = $request->search;
        $currentUserId = Auth::id();

        $users = User::where('id', '!=', $currentUserId)
            ->where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
            })
            ->where('is_active', true)
            ->select('id', 'name', 'email', 'phone')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users
            ]
        ]);
    }

    public function getContacts()
    {
        $contacts = Contact::with('contactUser:id,name,email,phone')
            ->where('user_id', Auth::id())
            ->where('is_blocked', false)
            ->orderBy('is_favorite', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'contacts' => $contacts
            ]
        ]);
    }

    public function addContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'name' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $contactUser = User::where('email', $request->email)->first();

        if ($contactUser->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add yourself as contact'
            ], 400);
        }

        $contact = Contact::firstOrCreate([
            'user_id' => Auth::id(),
            'contact_user_id' => $contactUser->id
        ], [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $contactUser->phone
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contact added successfully',
            'data' => [
                'contact' => $contact->load('contactUser')
            ]
        ]);
    }

    public function getTransactions()
    {
        $transactions = Transaction::with(['sender:id,name,email', 'receiver:id,name,email'])
            ->where(function($query) {
                $query->where('sender_id', Auth::id())
                      ->orWhere('receiver_id', Auth::id());
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions
            ]
        ]);
    }

    // Card Management Methods

    public function addCard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'card_holder_name' => 'required|string|max:255',
            'card_number' => 'required|string|size:16|regex:/^[0-9]+$/',
            'expiry_month' => 'required|integer|between:1,12',
            'expiry_year' => 'required|integer|min:' . date('Y') . '|max:' . (date('Y') + 20),
            'cvv' => 'required|string|size:3|regex:/^[0-9]+$/'
        ], [
            'card_number.size' => 'Card number must be exactly 16 digits',
            'card_number.regex' => 'Card number must contain only numbers',
            'cvv.size' => 'CVV must be exactly 3 digits',
            'cvv.regex' => 'CVV must contain only numbers'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Check if card already exists
        $lastFour = substr($request->card_number, -4);
        $existingCard = $user->cards()
            ->where('last_four', $lastFour)
            ->where('expiry_month', $request->expiry_month)
            ->where('expiry_year', $request->expiry_year)
            ->where('is_active', true)
            ->first();

        if ($existingCard) {
            return response()->json([
                'success' => false,
                'message' => 'This card is already linked to your account'
            ], 409);
        }

        try {
            $cardType = $this->detectCardType($request->card_number);
            $cardBrand = $this->detectCardBrand($request->card_number);

            $cardData = [
                'user_id' => $user->id,
                'card_holder_name' => $request->card_holder_name,
                'card_number' => $request->card_number,
                'expiry_month' => $request->expiry_month,
                'expiry_year' => $request->expiry_year,
                'cvv' => $request->cvv,
                'type' => $cardType,
                'brand' => $cardBrand,
                'last_four' => $lastFour,
                'is_default' => !$user->cards()->where('is_active', true)->exists(),
                'is_active' => true
            ];

            $card = Card::create($cardData);

            return response()->json([
                'success' => true,
                'message' => 'Card added successfully',
                'data' => [
                    'card' => [
                        'id' => $card->id,
                        'card_holder_name' => $card->card_holder_name,
                        'brand' => $card->brand,
                        'last_four' => $card->last_four,
                        'expiry_month' => $card->expiry_month,
                        'expiry_year' => $card->expiry_year,
                        'is_default' => $card->is_default,
                        'masked_number' => $card->masked_card_number,
                        'expiry' => $card->expiry
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Add card error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add card. Please try again.'
            ], 500);
        }
    }

    public function getCards()
    {
        $user = Auth::user();
        $cards = $user->cards()
            ->active()
            ->notExpired()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'cards' => $cards->map(function($card) {
                    return [
                        'id' => $card->id,
                        'card_holder_name' => $card->card_holder_name,
                        'brand' => $card->brand,
                        'last_four' => $card->last_four,
                        'expiry_month' => $card->expiry_month,
                        'expiry_year' => $card->expiry_year,
                        'is_default' => $card->is_default,
                        'masked_number' => $card->masked_card_number,
                        'expiry' => $card->expiry,
                        'is_expired' => $card->isExpired()
                    ];
                })
            ]
        ]);
    }

    public function setDefaultCard(Request $request, $cardId)
    {
        $user = Auth::user();
        
        try {
            $card = $user->cards()->active()->findOrFail($cardId);

            // Check if card is expired
            if ($card->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot set an expired card as default'
                ], 400);
            }

            DB::transaction(function () use ($user, $card) {
                // Remove default from all cards
                $user->cards()->update(['is_default' => false]);
                
                // Set new default
                $card->update(['is_default' => true]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Default card updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found'
            ], 404);
        }
    }

    public function removeCard($cardId)
    {
        $user = Auth::user();
        
        try {
            $card = $user->cards()->active()->findOrFail($cardId);

            DB::transaction(function () use ($user, $card) {
                $card->update(['is_active' => false, 'is_default' => false]);

                // If this was the default card, set another active card as default
                if ($card->is_default) {
                    $newDefault = $user->cards()
                        ->active()
                        ->notExpired()
                        ->first();
                    
                    if ($newDefault) {
                        $newDefault->update(['is_default' => true]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Card removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found'
            ], 404);
        }
    }

    public function getCardDetails($cardId)
    {
        $user = Auth::user();
        
        try {
            $card = $user->cards()->active()->findOrFail($cardId);

            return response()->json([
                'success' => true,
                'data' => [
                    'card' => [
                        'id' => $card->id,
                        'card_holder_name' => $card->card_holder_name,
                        'brand' => $card->brand,
                        'last_four' => $card->last_four,
                        'expiry_month' => $card->expiry_month,
                        'expiry_year' => $card->expiry_year,
                        'is_default' => $card->is_default,
                        'masked_number' => $card->masked_card_number,
                        'expiry' => $card->expiry,
                        'is_expired' => $card->isExpired()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found'
            ], 404);
        }
    }

    // Phone Management Methods

    public function addPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20|unique:users,phone,' . Auth::id()
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        try {
            $user->update([
                'phone' => $request->phone,
            ]);

            // // Send OTP for phone verification
            // $otp = rand(100000, 999999);
            // // Store OTP in cache for verification
            // \Illuminate\Support\Facades\Cache::put('phone_verification_' . $user->id, $otp, 600); // 10 minutes

            // // In real app, send SMS with OTP
            // Log::info("Phone verification OTP for {$user->phone}: {$otp}");

            return response()->json([
                'success' => true,
                'message' => 'Phone number added successfully. Please verify with OTP.',
                'data' => [
                    'requires_verification' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Add phone error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add phone number'
            ], 500);
        }
    }

    public function verifyPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $cachedOtp = \Illuminate\Support\Facades\Cache::get('phone_verification_' . $user->id);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP'
            ], 400);
        }

        try {
            $user->update([
                'phone_verified_at' => now()
            ]);

            // Clear OTP from cache
            \Illuminate\Support\Facades\Cache::forget('phone_verification_' . $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Phone number verified successfully',
                'data' => [
                    'user' => $user->fresh()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Verify phone error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify phone number'
            ], 500);
        }
    }

    public function resendPhoneOtp()
    {
        $user = Auth::user();

        if (!$user->phone) {
            return response()->json([
                'success' => false,
                'message' => 'No phone number to verify'
            ], 400);
        }

        if ($user->hasVerifiedPhone()) {
            return response()->json([
                'success' => false,
                'message' => 'Phone already verified'
            ], 400);
        }

        try {
            $otp = rand(100000, 999999);
            \Illuminate\Support\Facades\Cache::put('phone_verification_' . $user->id, $otp, 600);

            // In real app, send SMS with OTP
            Log::info("Resent phone verification OTP for {$user->phone}: {$otp}");

            return response()->json([
                'success' => true,
                'message' => 'OTP resent successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Resend OTP error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP'
            ], 500);
        }
    }

    // Helper Methods

    private function detectCardType($cardNumber)
    {
        $firstDigit = substr($cardNumber, 0, 1);
        $firstTwoDigits = substr($cardNumber, 0, 2);
        
        if ($firstDigit == '4') {
            return 'visa';
        } elseif ($firstDigit == '5') {
            return 'mastercard';
        } elseif ($firstDigit == '3') {
            if ($firstTwoDigits == '34' || $firstTwoDigits == '37') {
                return 'amex';
            }
            return 'unknown';
        } elseif ($firstDigit == '6') {
            if (substr($cardNumber, 0, 4) == '6011' || 
                (substr($cardNumber, 0, 6) >= '622126' && substr($cardNumber, 0, 6) <= '622925') ||
                (substr($cardNumber, 0, 3) >= '644' && substr($cardNumber, 0, 3) <= '649') ||
                $cardNumber[0] == '65') {
                return 'discover';
            }
            return 'unknown';
        } else {
            return 'unknown';
        }
    }

    private function detectCardBrand($cardNumber)
    {
        $type = $this->detectCardType($cardNumber);
        
        $brands = [
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'amex' => 'American Express',
            'discover' => 'Discover'
        ];
        
        return $brands[$type] ?? 'Credit Card';
    }
}
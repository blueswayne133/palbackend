<?php
namespace App\Http\Controllers;

use App\Mail\PaymentReceivedMail;
use App\Mail\PaymentSentMail;
use App\Models\User;
use App\Models\Transaction;
use App\Models\BankAccount;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
                    'cards_count' => $transactionsCount,
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
}
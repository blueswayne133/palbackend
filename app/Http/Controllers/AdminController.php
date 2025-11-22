<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Mail\AdminNotification;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check admin credentials using Admin model
        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid admin credentials'
            ], 401);
        }

        if (!$admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Admin account is deactivated'
            ], 401);
        }

        // Create admin token with admin guard
        $token = $admin->createToken('admin_token', ['admin'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Admin login successful',
            'data' => [
                'admin' => $admin,
                'token' => $token
            ]
        ]);
    }

    public function logout(Request $request)
    {
        try {
            // Revoke the current admin token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Admin logged out successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Admin logout error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    public function dashboard()
    {
        try {
            $admin = Auth::guard('admin')->user();
            
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'total_transactions' => Transaction::count(),
                'completed_transactions' => Transaction::where('status', 'completed')->count(),
                'total_volume' => Transaction::where('status', 'completed')->sum('amount') ?? 0,
                'pending_transactions' => Transaction::where('status', 'pending')->count(),
                'recent_transactions' => Transaction::with(['sender', 'receiver'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get(),
                'recent_users' => User::orderBy('created_at', 'desc')->limit(5)->get()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'admin' => $admin
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUsers(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $status = $request->get('status', '');
            
            $users = User::when($search, function($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%");
                })
                ->when($status, function($query, $status) {
                    if ($status === 'active') {
                        $query->where('is_active', true);
                    } elseif ($status === 'inactive') {
                        $query->where('is_active', false);
                    }
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('Get users error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUser($id)
    {
        try {
            $user = User::with(['contacts', 'sentTransactions', 'receivedTransactions'])->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Get user stats
            $userStats = [
                'total_sent' => $user->sentTransactions()->where('status', 'completed')->sum('amount') ?? 0,
                'total_received' => $user->receivedTransactions()->where('status', 'completed')->sum('net_amount') ?? 0,
                'total_contacts' => $user->contacts()->count() ?? 0,
                'total_transactions' => ($user->sentTransactions()->count() ?? 0) + ($user->receivedTransactions()->count() ?? 0)
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'stats' => $userStats
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createUser(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'account_balance' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'address' => $request->address,
                'currency' => $request->currency ?? 'USD',
                'account_balance' => $request->account_balance ?? 0.00,
                'is_active' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Create user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'account_balance' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Don't update password if not provided
            $updateData = $request->all();
            if (isset($updateData['password']) && $updateData['password']) {
                $updateData['password'] = Hash::make($updateData['password']);
            } else {
                unset($updateData['password']);
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Update user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteUser($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Check if admin is trying to delete themselves (if admin was a user)
            if ($user->id === Auth::id() && Auth::guard('web')->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ], 400);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Delete user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function creditAccount(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
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

            // Create credit transaction
            // $transaction = Transaction::create([
            //     'user_id' => $user->id,
            //     'sender_id' => null, // System credit
            //     'receiver_id' => $user->id,
            //     'amount' => $request->amount,
            //     'fee' => 0,
            //     'net_amount' => $request->amount,
            //     'currency' => 'USD',
            //     'type' => 'admin_credit',
            //     'status' => 'completed',
            //     'description' => $request->description ?? 'Admin credit',
            //     'reference_id' => 'ADM' . Str::random(12),
            //     'completed_at' => now(),
            //     'metadata' => [
            //         'admin_id' => Auth::guard('admin')->id(),
            //         'admin_name' => Auth::guard('admin')->user()->name,
            //         'credit_type' => 'manual'
            //     ]
            // ]);

            // Update user balance
            $user->increment('account_balance', $request->amount);

            return response()->json([
                'success' => true,
                'message' => 'Account credited successfully',
                'data' => [
                    'user' => $user,
                    // 'transaction' => $transaction
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error crediting account: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to credit account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function debitAccount(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0.01|max:' . $user->account_balance,
                'description' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create debit transaction
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'sender_id' => $user->id,
                'receiver_id' => null, // System debit
                'amount' => $request->amount,
                'fee' => 0,
                'net_amount' => $request->amount,
                'currency' => 'USD',
                'type' => 'admin_debit',
                'status' => 'completed',
                'description' => $request->description,
                'reference_id' => 'ADM' . Str::random(12),
                'completed_at' => now(),
                'metadata' => [
                    'admin_id' => Auth::guard('admin')->id(),
                    'admin_name' => Auth::guard('admin')->user()->name,
                    'debit_type' => 'manual'
                ]
            ]);

            // Update user balance
            $user->decrement('account_balance', $request->amount);

            return response()->json([
                'success' => true,
                'message' => 'Account debited successfully',
                'data' => [
                    'user' => $user,
                    'transaction' => $transaction
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error debiting account: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to debit account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleUserStatus($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $user->is_active = !$user->is_active;
            $user->save();

            $status = $user->is_active ? 'activated' : 'deactivated';

            return response()->json([
                'success' => true,
                'message' => "User {$status} successfully",
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Toggle user status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserContacts($id)
    {
        try {
            $contacts = Contact::with('contactUser')
                ->where('user_id', $id)
                ->orderBy('is_favorite', 'desc')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $contacts
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user contacts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user contacts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserTransactions($id)
    {
        try {
            $transactions = Transaction::with(['sender', 'receiver'])
                ->where(function($query) use ($id) {
                    $query->where('sender_id', $id)
                          ->orWhere('receiver_id', $id);
                })
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user transactions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTransactions(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $status = $request->get('status', '');
            $type = $request->get('type', '');
            $perPage = $request->get('per_page', 15);

            $transactions = Transaction::with(['sender', 'receiver'])
                ->when($search, function($query, $search) {
                    $query->where('reference_id', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhereHas('sender', function($q) use ($search) {
                              $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                          })
                          ->orWhereHas('receiver', function($q) use ($search) {
                              $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                          });
                })
                ->when($status, function($query, $status) {
                    $query->where('status', $status);
                })
                ->when($type, function($query, $type) {
                    $query->where('type', $type);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            Log::error('Get transactions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTransaction($id)
    {
        try {
            $transaction = Transaction::with(['sender', 'receiver'])->find($id);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            Log::error('Get transaction error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateTransactionStatus(Request $request, $id)
    {
        try {
            $transaction = Transaction::find($id);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,completed,failed,cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldStatus = $transaction->status;
            $transaction->status = $request->status;

            // If marking as completed, set completed_at
            if ($request->status === 'completed' && !$transaction->completed_at) {
                $transaction->completed_at = now();
            }

            $transaction->save();

            // Log the status change
            Log::info("Transaction {$transaction->id} status changed from {$oldStatus} to {$request->status} by admin " . Auth::guard('admin')->id());

            return response()->json([
                'success' => true,
                'message' => 'Transaction status updated successfully',
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            Log::error('Update transaction status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update transaction status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserStats($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $stats = [
                'total_sent' => $user->sentTransactions()->where('status', 'completed')->sum('amount') ?? 0,
                'total_received' => $user->receivedTransactions()->where('status', 'completed')->sum('net_amount') ?? 0,
                'total_contacts' => $user->contacts()->count() ?? 0,
                'total_transactions' => ($user->sentTransactions()->count() ?? 0) + ($user->receivedTransactions()->count() ?? 0),
                'pending_transactions' => $user->sentTransactions()->where('status', 'pending')->count() + $user->receivedTransactions()->where('status', 'pending')->count(),
                'failed_transactions' => $user->sentTransactions()->where('status', 'failed')->count() + $user->receivedTransactions()->where('status', 'failed')->count(),
                'avg_transaction_amount' => $user->sentTransactions()->where('status', 'completed')->avg('amount') ?? 0
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Get user stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportUsers(Request $request)
    {
        try {
            $users = User::select([
                'id', 'name', 'email', 'phone', 'account_balance', 
                'currency', 'is_active', 'created_at'
            ])->get();

            $fileName = 'users_' . date('Y-m-d_H-i-s') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ];

            $callback = function() use ($users) {
                $file = fopen('php://output', 'w');
                
                // Add CSV headers
                fputcsv($file, [
                    'ID', 'Name', 'Email', 'Phone', 'Account Balance', 
                    'Currency', 'Status', 'Created At'
                ]);

                // Add data
                foreach ($users as $user) {
                    fputcsv($file, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->phone,
                        $user->account_balance,
                        $user->currency,
                        $user->is_active ? 'Active' : 'Inactive',
                        $user->created_at
                    ]);
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Export users error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSystemStats()
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'inactive_users' => User::where('is_active', false)->count(),
                'total_transactions' => Transaction::count(),
                'completed_transactions' => Transaction::where('status', 'completed')->count(),
                'pending_transactions' => Transaction::where('status', 'pending')->count(),
                'failed_transactions' => Transaction::where('status', 'failed')->count(),
                'total_volume' => Transaction::where('status', 'completed')->sum('amount') ?? 0,
                'total_fees' => Transaction::where('status', 'completed')->sum('fee') ?? 0,
                'today_transactions' => Transaction::whereDate('created_at', today())->count(),
                'today_volume' => Transaction::whereDate('created_at', today())->where('status', 'completed')->sum('amount') ?? 0,
                'new_users_today' => User::whereDate('created_at', today())->count(),
                'new_users_week' => User::where('created_at', '>=', now()->subWeek())->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Get system stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendEmail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'subject' => 'required|string|max:255',
                'message' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::find($request->user_id);

            try {
                Mail::to($user->email)->send(new AdminNotification(
                    $request->subject,
                    $request->message,
                    $user->name
                ));

                return response()->json([
                    'success' => true,
                    'message' => 'Email sent successfully'
                ]);
            } catch (\Exception $e) {
                Log::error('Email sending error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send email: ' . $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Send email error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process email request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendBulkEmail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
                'subject' => 'required|string|max:255',
                'message' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $users = User::whereIn('id', $request->user_ids)->get();
            $sentCount = 0;
            $failedEmails = [];

            foreach ($users as $user) {
                try {
                    Mail::to($user->email)->send(new AdminNotification(
                        $request->subject,
                        $request->message,
                        $user->name
                    ));
                    $sentCount++;
                } catch (\Exception $e) {
                    Log::error("Failed to send email to {$user->email}: " . $e->getMessage());
                    $failedEmails[] = $user->email;
                    continue;
                }
            }

            $response = [
                'success' => true,
                'message' => "Emails sent to {$sentCount} users successfully",
                'data' => [
                    'total_attempted' => count($request->user_ids),
                    'successful' => $sentCount,
                    'failed' => count($request->user_ids) - $sentCount
                ]
            ];

            if (!empty($failedEmails)) {
                $response['data']['failed_emails'] = $failedEmails;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Bulk email error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk email request',
                'error' => $e->getMessage()
            ], 500);
        }
    }





    // Add these methods to AdminController.php

public function getWithdrawals(Request $request)
{
    try {
        $search = $request->get('search', '');
        $status = $request->get('status', '');
        $method = $request->get('method', '');
        $perPage = $request->get('per_page', 15);

        $withdrawals = Withdrawal::with(['user'])
            ->when($search, function($query, $search) {
                $query->where('reference_id', 'like', "%{$search}%")
                      ->orWhere('account_holder_name', 'like', "%{$search}%")
                      ->orWhere('bank_name', 'like', "%{$search}%")
                      ->orWhereHas('user', function($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                      });
            })
            ->when($status, function($query, $status) {
                $query->where('status', $status);
            })
            ->when($method, function($query, $method) {
                $query->where('method', $method);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $withdrawals
        ]);
    } catch (\Exception $e) {
        Log::error('Get withdrawals error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch withdrawals',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getWithdrawal($id)
{
    try {
        $withdrawal = Withdrawal::with(['user'])->find($id);

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $withdrawal
        ]);
    } catch (\Exception $e) {
        Log::error('Get withdrawal error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch withdrawal',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function updateWithdrawal(Request $request, $id)
{
    try {
        $withdrawal = Withdrawal::find($id);

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,processing,completed,failed,cancelled',
            'amount' => 'sometimes|numeric|min:0.01',
            'fee' => 'sometimes|numeric|min:0',
            'clearance_fee' => 'sometimes|numeric|min:0',
            'admin_notes' => 'nullable|string|max:1000',
            'bank_name' => 'sometimes|string|max:255',
            'account_number' => 'sometimes|string|max:50',
            'account_holder_name' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $withdrawal->status;
        $updateData = $request->all();

        // If status is changing to completed, update timestamps and net amount
        if ($request->has('status') && $request->status === 'completed' && $oldStatus !== 'completed') {
            $updateData['completed_at'] = now();
            $updateData['admin_id'] = Auth::guard('admin')->id();
            
            // Recalculate net amount if fees are updated
            $amount = $request->has('amount') ? $request->amount : $withdrawal->amount;
            $fee = $request->has('fee') ? $request->fee : $withdrawal->fee;
            $clearanceFee = $request->has('clearance_fee') ? $request->clearance_fee : $withdrawal->clearance_fee;
            
            $updateData['net_amount'] = $amount - $fee - $clearanceFee;
        }

        // If status is changing to processing
        if ($request->has('status') && $request->status === 'processing' && $oldStatus !== 'processing') {
            $updateData['processed_at'] = now();
            $updateData['admin_id'] = Auth::guard('admin')->id();
        }

        $withdrawal->update($updateData);

        // If status changed from pending to failed/cancelled, refund the amount
        if ($oldStatus === 'pending' && in_array($request->status, ['failed', 'cancelled'])) {
            $user = $withdrawal->user;
            $user->increment('account_balance', $withdrawal->amount);
            
            // Update related transaction status
            $transaction = Transaction::where('reference_id', $withdrawal->reference_id)->first();
            if ($transaction) {
                $transaction->update(['status' => $request->status]);
            }
        }

        // Log the status change
        Log::info("Withdrawal {$withdrawal->id} status changed from {$oldStatus} to {$withdrawal->status} by admin " . Auth::guard('admin')->id());

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal updated successfully',
            'data' => $withdrawal->fresh()
        ]);
    } catch (\Exception $e) {
        Log::error('Update withdrawal error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to update withdrawal',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function updateWithdrawalFees(Request $request, $id)
{
    try {
        $withdrawal = Withdrawal::find($id);

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'fee' => 'required|numeric|min:0',
            'clearance_fee' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Calculate new net amount
        $netAmount = $withdrawal->amount - $request->fee - $request->clearance_fee;

        if ($netAmount < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Fees cannot exceed withdrawal amount'
            ], 422);
        }

        $withdrawal->update([
            'fee' => $request->fee,
            'clearance_fee' => $request->clearance_fee,
            'net_amount' => $netAmount,
            'admin_id' => Auth::guard('admin')->id()
        ]);

        // Update related transaction if exists
        $transaction = Transaction::where('reference_id', $withdrawal->reference_id)->first();
        if ($transaction) {
            $transaction->update([
                'fee' => $request->fee + $request->clearance_fee,
                'net_amount' => $netAmount
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal fees updated successfully',
            'data' => $withdrawal->fresh()
        ]);
    } catch (\Exception $e) {
        Log::error('Update withdrawal fees error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to update withdrawal fees',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function deleteWithdrawal($id)
{
    try {
        $withdrawal = Withdrawal::find($id);

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal not found'
            ], 404);
        }

        // Only allow deletion of pending withdrawals
        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending withdrawals can be deleted'
            ], 400);
        }

        // Refund the amount to user
        $user = $withdrawal->user;
        $user->increment('account_balance', $withdrawal->amount);

        // Delete related transaction
        $transaction = Transaction::where('reference_id', $withdrawal->reference_id)->first();
        if ($transaction) {
            $transaction->delete();
        }

        $withdrawal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal deleted successfully'
        ]);
    } catch (\Exception $e) {
        Log::error('Delete withdrawal error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete withdrawal',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getWithdrawalStats()
{
    try {
        $stats = [
            'total_withdrawals' => Withdrawal::count(),
            'pending_withdrawals' => Withdrawal::where('status', 'pending')->count(),
            'processing_withdrawals' => Withdrawal::where('status', 'processing')->count(),
            'completed_withdrawals' => Withdrawal::where('status', 'completed')->count(),
            'failed_withdrawals' => Withdrawal::where('status', 'failed')->count(),
            'total_withdrawal_amount' => Withdrawal::sum('amount'),
            'total_fees' => Withdrawal::sum('fee'),
            'total_clearance_fees' => Withdrawal::sum('clearance_fee'),
            'today_withdrawals' => Withdrawal::whereDate('created_at', today())->count(),
            'today_withdrawal_amount' => Withdrawal::whereDate('created_at', today())->sum('amount')
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    } catch (\Exception $e) {
        Log::error('Get withdrawal stats error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch withdrawal statistics',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
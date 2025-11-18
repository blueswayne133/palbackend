<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminTransactionController;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

// CSRF cookie route
Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Admin auth routes (public)
Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AdminController::class, 'login']);
});

// Protected user routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    Route::prefix('user')->group(function () {
        Route::get('/dashboard', [UserController::class, 'dashboard']);
        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::get('/transactions', [UserController::class, 'transactions']);

                // Card management
        Route::get('/cards', [UserController::class, 'getCards']);
        Route::post('/cards', [UserController::class, 'addCard']);
        Route::put('/cards/{cardId}/default', [UserController::class, 'setDefaultCard']);
        Route::delete('/cards/{cardId}', [UserController::class, 'removeCard']);
        
        // Phone management
        Route::post('/phone', [UserController::class, 'addPhone']);
        Route::post('/phone/verify', [UserController::class, 'verifyPhone']);
        Route::post('/phone/resend-otp', [UserController::class, 'resendPhoneOtp']);
    });

    Route::prefix('payment')->group(function () {
        Route::post('/send', [UserController::class, 'sendPayment']);
        Route::post('/request', [UserController::class, 'requestPayment']);
        Route::get('/search-users', [UserController::class, 'searchUsers']);
    });

    Route::prefix('contacts')->group(function () {
        Route::get('/', [UserController::class, 'getContacts']);
        Route::post('/', [UserController::class, 'addContact']);
    });

    Route::get('/transactions', [UserController::class, 'getTransactions']);
});



 //Admin routes
Route::prefix('admin')->group(function () {
    // Auth routes
    Route::post('/auth/login', [AdminController::class, 'login']);
    Route::post('/auth/logout', [AdminController::class, 'logout'])->middleware('auth:admin');
    
    // Dashboard
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->middleware('auth:admin');
    Route::get('/system-stats', [AdminController::class, 'getSystemStats'])->middleware('auth:admin');
    
    // User management
    Route::get('/users', [AdminController::class, 'getUsers'])->middleware('auth:admin');
    Route::post('/users', [AdminController::class, 'createUser'])->middleware('auth:admin');
    Route::get('/users/{id}', [AdminController::class, 'getUser'])->middleware('auth:admin');
    Route::put('/users/{id}', [AdminController::class, 'updateUser'])->middleware('auth:admin');
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->middleware('auth:admin');
    Route::post('/users/{id}/credit', [AdminController::class, 'creditAccount'])->middleware('auth:admin');
    Route::post('/users/{id}/debit', [AdminController::class, 'debitAccount'])->middleware('auth:admin');
    Route::post('/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus'])->middleware('auth:admin');
    Route::get('/users/{id}/stats', [AdminController::class, 'getUserStats'])->middleware('auth:admin');
    Route::get('/users/{id}/contacts', [AdminController::class, 'getUserContacts'])->middleware('auth:admin');
    Route::get('/users/{id}/transactions', [AdminController::class, 'getUserTransactions'])->middleware('auth:admin');
    Route::get('/users/export/csv', [AdminController::class, 'exportUsers'])->middleware('auth:admin');
    
    // Transaction management
    Route::get('/transactions', [AdminController::class, 'getTransactions'])->middleware('auth:admin');
    Route::get('/transactions/{id}', [AdminController::class, 'getTransaction'])->middleware('auth:admin');
    Route::put('/transactions/{id}/status', [AdminController::class, 'updateTransactionStatus'])->middleware('auth:admin');
    
    // Email management
    Route::post('/send-email', [AdminController::class, 'sendEmail'])->middleware('auth:admin');
    Route::post('/send-bulk-email', [AdminController::class, 'sendBulkEmail'])->middleware('auth:admin');
});
// Fallback route
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});
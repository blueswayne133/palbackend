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

// Protected admin routes
Route::prefix('admin')->group(function () {
    Route::post('/auth/logout', [AdminController::class, 'logout']);
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    
    // User management
    Route::get('/users', [AdminController::class, 'getUsers']);
    Route::get('/users/{id}', [AdminController::class, 'getUser']);
    Route::post('/users', [AdminController::class, 'createUser']);
    Route::put('/users/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
    Route::post('/users/{id}/credit', [AdminController::class, 'creditAccount']);
    
    // User specific data
    Route::get('/users/{id}/contacts', [AdminController::class, 'getUserContacts']);
    Route::get('/users/{id}/transactions', [AdminController::class, 'getUserTransactions']);
    
    // Transaction management
    Route::get('/transactions', [AdminTransactionController::class, 'getTransactions']);
    Route::get('/transactions/{id}', [AdminTransactionController::class, 'getTransaction']);
    Route::post('/transactions', [AdminTransactionController::class, 'createTransaction']);
    Route::put('/transactions/{id}', [AdminTransactionController::class, 'updateTransaction']);
    Route::delete('/transactions/{id}', [AdminTransactionController::class, 'deleteTransaction']);
    
    // Email routes
    Route::post('/send-email', [AdminController::class, 'sendEmail']);
    Route::post('/send-bulk-email', [AdminController::class, 'sendBulkEmail']);
});

// Fallback route
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

// Public Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes (للمستعير والأدمن)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // التصنيفات والكتب
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}/books', [CategoryController::class, 'books']);
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);

    // الطلبات
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);

    // المفضلة
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{bookId}/toggle', [FavoriteController::class, 'toggle']);

    // التقارير
    Route::get('/reports/orders-summary', [ReportController::class, 'ordersSummary']);
    Route::get('/reports/top-books', [ReportController::class, 'topBooks']);

    // الإشعارات
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // صلاحيات صاحب المكتبة فقط (Admin)
    Route::middleware('admin')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);
        Route::patch('/orders/{id}/payment-status', [OrderController::class, 'updatePaymentStatus']);
        Route::post('/notifications/check-reminders', [NotificationController::class, 'checkReturnReminders']);
    });
});
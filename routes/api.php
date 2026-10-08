<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TesterController;
use Illuminate\Support\Facades\Route;

// كل المسارات هنا تبدأ بـ /api — يستخدمها تطبيق المختبِرين على أندرويد

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:otp');
    Route::post('verify', [AuthController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('resend', [AuthController::class, 'resend'])->middleware('throttle:otp');
});

Route::middleware(['auth:sanctum', 'role:tester', 'device', 'throttle:api'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('me', [TesterController::class, 'me']);
    Route::get('tasks', [TesterController::class, 'tasks']);
    Route::get('campaigns/available', [TesterController::class, 'available']);
    Route::post('campaigns/{campaign}/join', [TesterController::class, 'join']);

    Route::post('enrollments/{enrollment}/confirm-install', [TesterController::class, 'confirmInstall']);
    Route::post('enrollments/{enrollment}/leave', [TesterController::class, 'leave']);
    Route::post('enrollments/{enrollment}/feedback', [TesterController::class, 'feedback']);

    Route::post('checks', [TesterController::class, 'checks']);

    Route::get('history', [TesterController::class, 'history']);
    Route::get('withdrawals', [TesterController::class, 'withdrawals']);
    Route::post('withdrawals', [TesterController::class, 'requestWithdrawal']);
});

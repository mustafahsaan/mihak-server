<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Developer;
use App\Http\Controllers\FakeGatewayController;
use App\Http\Controllers\Tester;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'home'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:otp');
    Route::get('/join', [Tester\AccountController::class, 'showRegister'])->name('tester.register');
    Route::post('/join', [Tester\AccountController::class, 'register'])->middleware('throttle:otp');
});

// ---------- المختبِر ----------
Route::middleware(['auth', 'role:tester'])->group(function () {
    Route::get('/me', [Tester\AccountController::class, 'dashboard'])->name('tester.dashboard');
    Route::post('/me/withdraw', [Tester\AccountController::class, 'withdraw'])->name('tester.withdraw');
});

Route::middleware('auth')->group(function () {
    Route::get('/verify', [AuthController::class, 'showOtp'])->name('otp.show');
    Route::post('/verify', [AuthController::class, 'verifyOtp'])->name('otp.verify')->middleware('throttle:10,1');
    Route::post('/verify/resend', [AuthController::class, 'resendOtp'])->name('otp.resend')->middleware('throttle:otp');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// ---------- المطوّر ----------
Route::middleware(['auth', 'role:developer'])->group(function () {
    Route::get('/dashboard', [Developer\DashboardController::class, 'index'])->name('dashboard');
    Route::post('/apps', [Developer\DashboardController::class, 'storeApp'])->name('apps.store');
    Route::post('/apps/{app}/campaigns', [Developer\DashboardController::class, 'newCampaign'])->name('apps.campaigns.store');

    Route::get('/campaigns/{campaign}', [Developer\CampaignController::class, 'show'])->name('campaigns.show');
    Route::post('/campaigns/{campaign}/refund', [Developer\CampaignController::class, 'refund'])->name('campaigns.refund');
    Route::post('/campaigns/{campaign}/rerun', [Developer\CampaignController::class, 'rerun'])->name('campaigns.rerun');

    Route::get('/campaigns/{campaign}/pay', [Developer\PaymentController::class, 'choose'])->name('campaigns.pay');
    Route::post('/campaigns/{campaign}/pay', [Developer\PaymentController::class, 'start'])->name('campaigns.pay.start');
});

// ---------- الدفع ----------
Route::get('/payments/{payment}/return', [Developer\PaymentController::class, 'return'])->name('payments.return');
Route::post('/webhooks/zaincash', [WebhookController::class, 'zaincash'])->name('webhooks.zaincash');
Route::post('/webhooks/qicard', [WebhookController::class, 'qicard'])->name('webhooks.qicard');
Route::get('/fake-gateway/{payment}', [FakeGatewayController::class, 'show'])->name('fake-gateway.show');
Route::post('/fake-gateway/{payment}', [FakeGatewayController::class, 'complete'])->name('fake-gateway.complete');

// ---------- الإدارة ----------
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/withdrawals', [Admin\MoneyController::class, 'withdrawals'])->name('withdrawals');
    Route::post('/withdrawals/{withdrawal}/approve', [Admin\MoneyController::class, 'approve'])->name('withdrawals.approve');
    Route::post('/withdrawals/{withdrawal}/reject', [Admin\MoneyController::class, 'reject'])->name('withdrawals.reject');

    Route::get('/refunds', [Admin\MoneyController::class, 'refunds'])->name('refunds');
    Route::post('/refunds/{refund}/gateway', [Admin\MoneyController::class, 'refundViaGateway'])->name('refunds.gateway');
    Route::post('/refunds/{refund}/manual', [Admin\MoneyController::class, 'refundManual'])->name('refunds.manual');

    Route::get('/ledger', [Admin\MoneyController::class, 'ledger'])->name('ledger');

    Route::get('/campaigns', [Admin\CampaignController::class, 'index'])->name('campaigns');
    Route::get('/campaigns/{campaign}', [Admin\CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('/settings', [Admin\CampaignController::class, 'settings'])->name('settings');
    Route::put('/settings', [Admin\CampaignController::class, 'updateSettings'])->name('settings.update');
    Route::put('/settings/font', [Admin\CampaignController::class, 'updateFont'])->name('settings.font');

    Route::get('/otp', [Admin\OtpController::class, 'index'])->name('otp');
    Route::put('/otp', [Admin\OtpController::class, 'update'])->name('otp.update');
});

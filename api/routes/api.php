<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BillingPreviewController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PriceChangeController;
use App\Http\Controllers\PushTokenController;
use App\Http\Controllers\ReminderSettingsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');

// Public
Route::get('/invitations/{token}', [InvitationController::class, 'show'])
    ->middleware('throttle:20,1')
    ->name('invitations.show');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1')->name('reset-password');
});

// Link from the verification mail (signed, no login needed)
Route::get('/auth/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('verification.verify');

// Authenticated, e-mail not yet required
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// Authenticated and verified
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/billing/preview', BillingPreviewController::class)->name('billing.preview');

    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    Route::post('/subscriptions/{subscription}/reactivate', [SubscriptionController::class, 'reactivate'])->name('subscriptions.reactivate');

    Route::post('/subscriptions/{subscription}/price-changes', [PriceChangeController::class, 'store'])->name('subscriptions.price-changes.store');
    Route::get('/price-changes', [PriceChangeController::class, 'index'])->name('price-changes.index');
    Route::post('/price-changes/{priceChange}/accept', [PriceChangeController::class, 'accept'])->name('price-changes.accept');
    Route::post('/price-changes/{priceChange}/dismiss', [PriceChangeController::class, 'dismiss'])->name('price-changes.dismiss');

    Route::get('/imports/formats', [ImportController::class, 'formats'])->name('imports.formats');
    Route::post('/imports', [ImportController::class, 'store'])->middleware('throttle:10,1')->name('imports.store');
    Route::get('/imports/{import}/candidates', [ImportController::class, 'candidates'])->name('imports.candidates');
    Route::post('/imports/{import}/summary', [ImportController::class, 'summary'])->name('imports.summary');
    Route::post('/imports/{import}/apply', [ImportController::class, 'apply'])->name('imports.apply');

    Route::apiResource('categories', CategoryController::class)->except('show');
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');

    Route::get('/settings/reminders', [ReminderSettingsController::class, 'show'])->name('settings.reminders.show');
    Route::put('/settings/reminders', [ReminderSettingsController::class, 'update'])->name('settings.reminders.update');

    Route::post('/push-tokens', [PushTokenController::class, 'store'])->middleware('throttle:20,1')->name('push-tokens.store');
    Route::delete('/push-tokens/{token}', [PushTokenController::class, 'destroy'])
        ->where('token', '.+')
        ->name('push-tokens.destroy');
});

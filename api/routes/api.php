<?php

use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');

// Public
Route::get('/invitations/{token}', [InvitationController::class, 'show'])
    ->middleware('throttle:20,1')
    ->name('invitations.show');

// Authenticated
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
});

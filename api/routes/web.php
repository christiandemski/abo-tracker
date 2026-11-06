<?php

use App\Http\Controllers\WebAppController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/assetlinks.json', [WebAppController::class, 'assetLinks']);

// Everything that is not /api/* is the web app (Expo static export, client-side routing).
Route::fallback(WebAppController::class);

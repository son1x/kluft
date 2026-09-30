<?php

use App\Http\Controllers\Auth\BreakGlassAuthController;
use App\Http\Controllers\Auth\EntraAuthController;
use Illuminate\Support\Facades\Route;

// Platzhalter-Startseite, bis die redaktionellen Seiten/CMS-Inhalte
// (Pflichtenheft Phase 1a) stehen. Bewusst ohne DB-Zugriff nutzbar.
Route::get('/', function () {
    return view('under-construction');
});

// Entra-ID-/M365-Login der Redaktion (Pflichtenheft 5.1, 11.2).
// GET /admin/login (registriert vom Filament-Panel, s.
// AdminPanelProvider::login()) leitet hierher weiter.
Route::get('/auth/entra/redirect', [EntraAuthController::class, 'redirectToEntra'])
    ->name('auth.entra.redirect');
Route::get('/auth/entra/callback', [EntraAuthController::class, 'callback'])
    ->name('auth.entra.callback');

// Notfall-Super-Admin (Break-Glass), s. Pflichtenheft 5.2. Bewusst nicht
// in der Navigation verlinkt und mit eigenem Rate-Limit im Controller
// (zusätzlich zur globalen Throttle-Middleware unten).
Route::middleware('throttle:20,15')->group(function () {
    Route::get('/notfall-login', [BreakGlassAuthController::class, 'show'])
        ->name('notfall-login');
    Route::post('/notfall-login', [BreakGlassAuthController::class, 'login'])
        ->name('notfall-login.attempt');
});

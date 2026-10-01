<?php

use App\Http\Controllers\Auth\BreakGlassAuthController;
use App\Http\Controllers\Auth\EntraAuthController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PostController;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

// Startseite: zeigt die veröffentlichte Seite mit Slug "start", falls
// bereits redaktionell angelegt (Pflichtenheft Phase 1a) – sonst die
// DB-freie Platzhalterseite (unverändert nutzbar, falls noch keine
// Inhalte existieren oder während der DB kurzzeitig nicht erreichbar ist).
Route::get('/', function () {
    $startPage = Page::query()->published()->where('slug', 'start')->first();

    if ($startPage) {
        return view('pages.show', ['page' => $startPage]);
    }

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

// Blog/Aktuelles (F-CMS-03). Vor dem Seiten-Catch-all registriert, damit
// "/aktuelles" nicht als Seiten-Slug interpretiert wird.
Route::get('/aktuelles', [PostController::class, 'index'])->name('posts.index');
Route::get('/aktuelles/{slug}', [PostController::class, 'show'])->name('posts.show');

// Redaktionelle Seiten (F-CMS-01). Bewusst als letzte Route registriert –
// fängt jeden sonst nicht zugeordneten Ein-Segment-Pfad als Seiten-Slug ab.
Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');

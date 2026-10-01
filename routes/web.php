<?php

use App\Http\Controllers\Auth\BreakGlassAuthController;
use App\Http\Controllers\Auth\EntraAuthController;
use App\Http\Controllers\Public\CalendarController;
use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\GeorgsboteController;
use App\Http\Controllers\Public\GruppierungController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PostController;
use App\Http\Controllers\Public\RegistrationController;
use App\Http\Controllers\Public\SectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

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

// Bezirke- und Stämme-Verzeichnis mit Karte (F-MAP, Pflichtenheft 11.1).
Route::get('/bezirke-staemme', [GruppierungController::class, 'index'])->name('gruppierungen.index');
Route::get('/api/gruppierungen.geojson', [GruppierungController::class, 'geojson'])
    ->middleware('throttle:60,1')
    ->name('gruppierungen.geojson');

// Kalender (F-CAL, Pflichtenheft 11.1).
Route::get('/kalender', [CalendarController::class, 'index'])->name('calendar.index');
Route::get('/api/calendar/events', [CalendarController::class, 'api'])
    ->middleware('throttle:60,1')
    ->name('calendar.api');

// Veranstaltungen mit Anmeldung (F-EV, Pflichtenheft 11.1).
Route::get('/veranstaltungen', [EventController::class, 'index'])->name('events.index');
Route::get('/veranstaltungen/{slug}', [EventController::class, 'show'])->name('events.show');
Route::post('/veranstaltungen/{slug}/anmeldung', [EventController::class, 'register'])
    ->middleware('throttle:5,10')
    ->name('events.register');
Route::middleware('throttle:20,10')->group(function () {
    Route::get('/anmeldung/bestaetigen/{token}', [RegistrationController::class, 'showConfirm'])->name('registrations.confirm');
    Route::post('/anmeldung/bestaetigen/{token}', [RegistrationController::class, 'confirm'])->name('registrations.confirm.submit');
    Route::get('/anmeldung/stornieren/{token}', [RegistrationController::class, 'showCancel'])->name('registrations.cancel');
    Route::post('/anmeldung/stornieren/{token}', [RegistrationController::class, 'cancel'])->name('registrations.cancel.submit');
});

// Georgsbote: Archiv + Newsletter-Anmeldung (F-GB, Pflichtenheft 11.1).
Route::get('/georgsbote', [GeorgsboteController::class, 'index'])->name('georgsbote.index');
Route::get('/georgsbote/danke', [GeorgsboteController::class, 'danke'])->name('georgsbote.danke');
Route::get('/georgsbote/{jahr}/{ausgabe}.pdf', [GeorgsboteController::class, 'pdf'])
    ->whereNumber(['jahr', 'ausgabe'])
    ->name('georgsbote.pdf');
Route::post('/georgsbote/anmelden', [GeorgsboteController::class, 'subscribe'])
    ->middleware('throttle:5,10')
    ->name('georgsbote.anmelden');

// Sektionen (F-BOL, Pflichtenheft 6.2), z. B. /bolivien.
Route::get('/{section}', [SectionController::class, 'show'])
    ->whereIn('section', array_keys(config('dpsg.sections')))
    ->name('sections.show');
Route::get('/{section}/aktuelles', [SectionController::class, 'posts'])
    ->whereIn('section', array_keys(config('dpsg.sections')))
    ->name('sections.posts');

// Redaktionelle Seiten (F-CMS-01). Bewusst als letzte Route registriert –
// fängt jeden sonst nicht zugeordneten Ein-Segment-Pfad als Seiten-Slug ab.
Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');

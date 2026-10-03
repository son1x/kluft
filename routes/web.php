<?php

use App\Http\Controllers\Auth\BreakGlassAuthController;
use App\Http\Controllers\Auth\EntraAuthController;
use App\Http\Controllers\Public\CalendarController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\FeedController;
use App\Http\Controllers\Public\GeorgsboteController;
use App\Http\Controllers\Public\GeorgsbriefController;
use App\Http\Controllers\Public\GruppierungController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ParticipantController;
use App\Http\Controllers\Public\PostController;
use App\Http\Controllers\Public\RegistrationController;
use App\Http\Controllers\Public\RoadmapController;
use App\Http\Controllers\Public\SectionController;
use App\Http\Controllers\Public\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Entra-ID-/M365-Login der Redaktion (Pflichtenheft 5.1, 11.2).
// GET /admin/login (registriert vom Filament-Panel, s.
// AdminPanelProvider::login()) leitet hierher weiter.
Route::get('/auth/entra/redirect', [EntraAuthController::class, 'redirectToEntra'])
    ->name('auth.entra.redirect');
Route::get('/auth/entra/callback', [EntraAuthController::class, 'callback'])
    ->name('auth.entra.callback');
Route::get('/auth/entra/verzeichnis', [EntraAuthController::class, 'connectDirectory'])
    ->name('auth.entra.verzeichnis');

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

// RSS-Feed der Beiträge (Pflichtenheft 11.1).
Route::get('/feed', [FeedController::class, 'index'])->name('feed');

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
Route::post('/veranstaltungen/{slug}/frage', [ContactController::class, 'askEvent'])
    ->middleware('throttle:5,10')
    ->name('events.ask');
Route::middleware('throttle:20,10')->group(function () {
    Route::get('/anmeldung/bestaetigen/{token}', [RegistrationController::class, 'showConfirm'])->name('registrations.confirm');
    Route::post('/anmeldung/bestaetigen/{token}', [RegistrationController::class, 'confirm'])->name('registrations.confirm.submit');
    Route::get('/anmeldung/stornieren/{token}', [RegistrationController::class, 'showCancel'])->name('registrations.cancel');
    Route::post('/anmeldung/stornieren/{token}', [RegistrationController::class, 'cancel'])->name('registrations.cancel.submit');
});

// Meine Anmeldungen: Übersicht für Teilnehmende mit E-Mail und Einmalcode.
Route::get('/meine-anmeldungen', [ParticipantController::class, 'index'])->name('participants.index');
Route::post('/meine-anmeldungen/code', [ParticipantController::class, 'sendCode'])
    ->middleware('throttle:teilnehmende-code')
    ->name('participants.code');
Route::post('/meine-anmeldungen/anmelden', [ParticipantController::class, 'verify'])
    ->middleware('throttle:teilnehmende-anmelden')
    ->name('participants.verify');
Route::post('/meine-anmeldungen/neu', [ParticipantController::class, 'restart'])->name('participants.restart');
Route::post('/meine-anmeldungen/abmelden', [ParticipantController::class, 'logout'])->name('participants.logout');
Route::post('/meine-anmeldungen/{registration}/stornieren', [ParticipantController::class, 'cancel'])
    ->whereNumber('registration')
    ->middleware('throttle:teilnehmende')
    ->name('participants.cancel');
Route::get('/meine-anmeldungen/dokument/{document}', [ParticipantController::class, 'document'])
    ->whereNumber('document')
    ->middleware('throttle:teilnehmende')
    ->name('participants.document');

// Systemstatus für Menschen und Überwachungsdienste (Pflichtenheft 10.2).
Route::get('/status', [StatusController::class, 'show'])->name('status');
Route::get('/api/status', [StatusController::class, 'json'])->middleware('throttle:60,1')->name('status.json');

// Projektstand nach Pflichtenheft (außerhalb des CMS).
Route::get('/roadmap', RoadmapController::class)->name('roadmap');

// Georgsbote: Archiv der PDF-Ausgaben (F-GB, Pflichtenheft 11.1).
Route::get('/georgsbote', [GeorgsboteController::class, 'index'])->name('georgsbote.index');
Route::get('/georgsbote/{jahr}/{ausgabe}.pdf', [GeorgsboteController::class, 'pdf'])
    ->whereNumber(['jahr', 'ausgabe'])
    ->name('georgsbote.pdf');

// Georgsbrief: Newsletter-Anmeldung (Double-Opt-In über Brevo).
Route::get('/georgsbrief', [GeorgsbriefController::class, 'index'])->name('georgsbrief.index');
Route::get('/georgsbrief/danke', [GeorgsbriefController::class, 'danke'])->name('georgsbrief.danke');
Route::post('/georgsbrief/anmelden', [GeorgsbriefController::class, 'subscribe'])
    ->middleware('throttle:5,10')
    ->name('georgsbrief.anmelden');
// Frühere Adresse der Bestätigungsseite (evtl. schon in Brevo hinterlegt).
Route::redirect('/georgsbote/danke', '/georgsbrief/danke', 301);

// Kontaktformular (F-CMS-09); das Formular steht auf der CMS-Seite „kontakt“.
Route::post('/kontakt', [ContactController::class, 'send'])
    ->middleware('throttle:5,10')
    ->name('contact.send');

// Sektionen (F-BOL, Pflichtenheft 6.2), z. B. /bolivien.
Route::get('/{section}', [SectionController::class, 'show'])
    ->whereIn('section', array_keys(config('dpsg.sections')))
    ->name('sections.show');
Route::get('/{section}/aktuelles', [SectionController::class, 'posts'])
    ->whereIn('section', array_keys(config('dpsg.sections')))
    ->name('sections.posts');
Route::get('/{section}/feed', [FeedController::class, 'section'])
    ->whereIn('section', array_keys(config('dpsg.sections')))
    ->name('sections.feed');

// Redaktionelle Seiten (F-CMS-01). Bewusst als letzte Route registriert –
// fängt jeden sonst nicht zugeordneten Ein-Segment-Pfad als Seiten-Slug ab.
Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');

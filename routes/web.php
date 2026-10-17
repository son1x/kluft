<?php

use App\Http\Controllers\Auth\BreakGlassAuthController;
use App\Http\Controllers\Auth\CodeLoginController;
use App\Http\Controllers\Auth\EntraAuthController;
use App\Http\Controllers\Public\CalendarController;
use App\Http\Controllers\Public\CertificateController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\DownloadController;
use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\FeedController;
use App\Http\Controllers\Public\GeorgsboteController;
use App\Http\Controllers\Public\GruppierungController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsletterController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ParticipantController;
use App\Http\Controllers\Public\PostController;
use App\Http\Controllers\Public\RegistrationController;
use App\Http\Controllers\Public\RoadmapController;
use App\Http\Controllers\Public\SectionController;
use App\Http\Controllers\Public\StatusController;
use App\Http\Controllers\Public\WhatsAppController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Middleware\Ausbaustufe;
use App\Support\Ausbau;
use App\Support\Features;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
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

// WhatsApp-Newsletter: Anmeldeseite (nur wenn mit Meta verbunden).
Route::get('/whatsapp', [WhatsAppController::class, 'show'])->name('whatsapp.index');
Route::post('/whatsapp', [WhatsAppController::class, 'subscribe'])->middleware('throttle:whatsapp-anmelden')->name('whatsapp.subscribe');

// WhatsApp-Newsletter: Webhook der Cloud API (docs/whatsapp_newsletter.md).
// Ohne CSRF-Token (Meta signiert stattdessen jeden Aufruf).
Route::middleware('throttle:whatsapp-webhook')->withoutMiddleware(ValidateCsrfToken::class)->group(function () {
    Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
    Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('webhooks.whatsapp');
});

// Notfall-Super-Admin (Break-Glass), s. Pflichtenheft 5.2. Bewusst nicht
// in der Navigation verlinkt und mit eigenem Rate-Limit im Controller
// (zusätzlich zur globalen Throttle-Middleware unten).
// Redaktion ohne Microsoft 365: Einmalcode per E-Mail (Funktion code_login).
Route::get('/redaktion/anmelden', [CodeLoginController::class, 'show'])->middleware('throttle:60,1')->name('auth.code');
Route::post('/redaktion/anmelden', [CodeLoginController::class, 'send'])->middleware('throttle:10,10')->name('auth.code.send');
Route::post('/redaktion/anmelden/code', [CodeLoginController::class, 'verify'])->middleware('throttle:20,10')->name('auth.code.verify');

Route::middleware('throttle:20,15')->group(function () {
    Route::get('/notfall-login', [BreakGlassAuthController::class, 'show'])
        ->name('notfall-login');
    Route::post('/notfall-login', [BreakGlassAuthController::class, 'login'])
        ->name('notfall-login.attempt');
});

// Blog/Aktuelles (F-CMS-03). Vor dem Seiten-Catch-all registriert, damit
// "/aktuelles" nicht als Seiten-Slug interpretiert wird.
// Meldungen, Kalender und Veranstaltungen ab Ausbaustufe 3, Anmeldungen ab 4
// (App\Support\Ausbau); darunter „nicht gefunden“.
$termine = Ausbaustufe::class.':'.Ausbau::TERMINE;
$anmeldungen = Ausbaustufe::class.':'.Ausbau::ANMELDUNGEN;
Route::get('/aktuelles', [PostController::class, 'index'])->middleware($termine)->name('posts.index');
Route::get('/aktuelles/{slug}', [PostController::class, 'show'])->middleware($termine)->name('posts.show');

// RSS-Feed der Beiträge (Pflichtenheft 11.1).
Route::get('/feed', [FeedController::class, 'index'])->middleware($termine)->name('feed');

// Verzeichnis der Stämme (beim DV mit Bezirken) mit Karte (F-MAP, Pflichtenheft 11.1).
// Verzeichnis der Bezirke/Stämme; Stammes-Websites haben keins (directory = null).
if (config('dpsg.directory.path')) {
    Route::get('/'.config('dpsg.directory.path'), [GruppierungController::class, 'index'])->name('gruppierungen.index');
}
Route::get('/api/gruppierungen.geojson', [GruppierungController::class, 'geojson'])
    ->middleware('throttle:60,1')
    ->name('gruppierungen.geojson');

// Kalender (F-CAL, Pflichtenheft 11.1).
Route::get('/kalender', [CalendarController::class, 'index'])->middleware($termine)->name('calendar.index');
Route::get('/api/calendar/events', [CalendarController::class, 'api'])
    ->middleware(['throttle:60,1', $termine])
    ->name('calendar.api');

// Veranstaltungen mit Anmeldung (F-EV, Pflichtenheft 11.1).
Route::get('/veranstaltungen', [EventController::class, 'index'])->middleware($termine)->name('events.index');
Route::get('/veranstaltungen/{slug}', [EventController::class, 'show'])->middleware($termine)->name('events.show');
Route::post('/veranstaltungen/{slug}/anmeldung', [EventController::class, 'register'])
    ->middleware(['throttle:5,10', $anmeldungen])
    ->name('events.register');
Route::post('/veranstaltungen/{slug}/frage', [ContactController::class, 'askEvent'])
    ->middleware(['throttle:5,10', $termine])
    ->name('events.ask');
Route::middleware('throttle:20,10')->group(function () {
    Route::get('/anmeldung/bestaetigen/{token}', [RegistrationController::class, 'showConfirm'])->name('registrations.confirm');
    Route::post('/anmeldung/bestaetigen/{token}', [RegistrationController::class, 'confirm'])->name('registrations.confirm.submit');
    Route::get('/anmeldung/stornieren/{token}', [RegistrationController::class, 'showCancel'])->name('registrations.cancel');
    Route::post('/anmeldung/stornieren/{token}', [RegistrationController::class, 'cancel'])->name('registrations.cancel.submit');
});

// Meine Anmeldungen: Übersicht für Teilnehmende mit E-Mail und Einmalcode.
// Bestätigen/Stornieren per Link und die Echtheitsprüfung bleiben immer
// erreichbar, auch wenn die Ausbaustufe später gesenkt wird.
Route::middleware($anmeldungen)->group(function () {
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

    // Teilnahmebescheinigung: Download für Teilnehmende, Echtheitsprüfung für alle.
    Route::get('/meine-anmeldungen/{registration}/bescheinigung', [ParticipantController::class, 'certificate'])
        ->whereNumber('registration')
        ->middleware('throttle:teilnehmende')
        ->name('participants.certificate');
});
Route::get('/bescheinigung-pruefen', [CertificateController::class, 'show'])->name('certificates.verify');
Route::post('/bescheinigung-pruefen', [CertificateController::class, 'check'])
    ->middleware('throttle:10,1')
    ->name('certificates.check');

// Systemstatus für Menschen und Überwachungsdienste (Pflichtenheft 10.2).
Route::get('/status', [StatusController::class, 'show'])->name('status');
Route::get('/api/status', [StatusController::class, 'json'])->middleware('throttle:60,1')->name('status.json');

// Projektstand nach Pflichtenheft (außerhalb des CMS).
if (Features::enabled(Features::ROADMAP)) {
    Route::get('/roadmap', RoadmapController::class)->name('roadmap');
}

// Georgsbote: Archiv der PDF-Ausgaben (F-GB, Pflichtenheft 11.1).
if (Features::enabled(Features::GEORGSBOTE)) {
    Route::get('/georgsbote', [GeorgsboteController::class, 'index'])->name('georgsbote.index');
    Route::get('/georgsbote/{jahr}/{ausgabe}.pdf', [GeorgsboteController::class, 'pdf'])
        ->whereNumber(['jahr', 'ausgabe'])
        ->name('georgsbote.pdf');
}

// Download-Seite (Methoden, Formulare, Arbeitshilfen).
if (Features::enabled(Features::DOWNLOADS)) {
    $downloads = '/'.config('dpsg.downloads.slug');
    Route::get($downloads, [DownloadController::class, 'index'])->name('downloads.index');
    Route::get($downloads.'/{download}/{name}', [DownloadController::class, 'file'])
        ->whereNumber('download')
        ->where('name', '[A-Za-z0-9._-]+')
        ->name('downloads.file');
}

// E-Mail-Newsletter (beim DV „Georgsbrief“): Anmeldung per Double-Opt-In über Brevo.
if (Features::newsletter()) {
    $newsletter = '/'.config('dpsg.newsletter.slug');
    Route::get($newsletter, [NewsletterController::class, 'index'])->name('newsletter.index');
    Route::get($newsletter.'/danke', [NewsletterController::class, 'danke'])->name('newsletter.danke');
    Route::post($newsletter.'/anmelden', [NewsletterController::class, 'subscribe'])
        ->middleware('throttle:5,10')
        ->name('newsletter.anmelden');
}

// Feste Weiterleitungen alter Adressen je Instanz.
foreach (config('dpsg.redirects') as $from => $to) {
    Route::redirect($from, $to, 301);
}

// Kontaktformular (F-CMS-09); das Formular steht auf der CMS-Seite „kontakt“.
Route::post('/kontakt', [ContactController::class, 'send'])
    ->middleware('throttle:5,10')
    ->name('contact.send');

// Sektionen (F-BOL, Pflichtenheft 6.2), z. B. /bolivien. Nur mit
// konfigurierten Sektionen: whereIn mit leerer Liste fängt sonst jeden Pfad.
if (Features::sections()) {
    $sections = array_keys(config('dpsg.sections'));
    Route::get('/{section}', [SectionController::class, 'show'])
        ->whereIn('section', $sections)
        ->name('sections.show');
    Route::get('/{section}/aktuelles', [SectionController::class, 'posts'])
        ->whereIn('section', $sections)
        ->name('sections.posts');
    Route::get('/{section}/feed', [FeedController::class, 'section'])
        ->whereIn('section', $sections)
        ->name('sections.feed');
}

// Redaktionelle Seiten (F-CMS-01). Bewusst als letzte Route registriert –
// fängt jeden sonst nicht zugeordneten Ein-Segment-Pfad als Seiten-Slug ab.
Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');

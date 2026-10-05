<?php

namespace App\Providers;

use App\Mail\TestRecipientFilter;
use App\Models\Page;
use App\Services\Glossary;
use App\Services\Mitglieder\HitobitoVerzeichnis;
use App\Services\Mitglieder\KeinVerzeichnis;
use App\Services\Mitglieder\Mitgliederverzeichnis;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\Embeds;
use App\Support\Galerie;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Stringable;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Mitgliederverwaltung je nach Treiber (docs/mitgliederabgleich.md).
        $this->app->singleton(Mitgliederverzeichnis::class, fn () => match (config('mitglieder.driver')) {
            'nami' => new NamiVerzeichnis,
            'hitobito' => new HitobitoVerzeichnis,
            default => new KeinVerzeichnis,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Inhalts-Migrationen der aktiven Instanz (instanzen/<schlüssel>/migrations):
        // bringen neue Inhalte auf laufende Installationen; andere Instanzen sehen sie nicht.
        $instanzMigrationen = base_path('instanzen/'.config('dpsg.instanz').'/migrations');
        if (is_dir($instanzMigrationen)) {
            $this->loadMigrationsFrom($instanzMigrationen);
        }

        // „Meine Anmeldungen“: eigene Zähler, damit Codeeingaben nicht das
        // Anfordern oder andere Formulare blockieren (throttle:x,y teilt
        // sich sonst einen Zähler pro IP).
        // Tageslimit des Brevo-Tarifs (F-MAIL-05): Mails aus der
        // Warteschlange warten, bis wieder Kontingent frei ist.
        RateLimiter::for('brevo-tageslimit', fn () => Limit::perDay(max(1, (int) config('mail.daily_limit')))->by('brevo'));

        // WhatsApp-Newsletter: Webhook, Anmeldeformular und Versandtempo.
        RateLimiter::for('whatsapp-webhook', fn (Request $request) => Limit::perMinute(600)->by('wa-hook|'.$request->ip()));
        RateLimiter::for('whatsapp-anmelden', fn (Request $request) => Limit::perMinutes(10, 5)->by('wa-form|'.$request->ip()));
        RateLimiter::for('whatsapp', fn () => Limit::perMinute(max(1, (int) config('whatsapp.per_minute')))->by('whatsapp-versand'));

        RateLimiter::for('teilnehmende-code', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by('code-ip|'.$request->ip()),
            Limit::perHour(5)->by('code-mail|'.mb_strtolower(trim((string) $request->input('email')))),
        ]);
        RateLimiter::for('teilnehmende-anmelden', fn (Request $request) => Limit::perMinutes(10, 10)->by('anmelden|'.$request->ip()));
        RateLimiter::for('teilnehmende', fn (Request $request) => Limit::perMinutes(10, 60)->by('teilnehmende|'.$request->ip()));

        // Zwei-Klick-Einbettungen für Video- und Kartenlinks: str($html)->sanitizeHtml()->embeds()
        Stringable::macro('embeds', function () {
            /** @var Stringable $this */
            return new Stringable(Embeds::render((string) $this));
        });

        // Bilderreihen als Galerie: str($html)->sanitizeHtml()->galerie()
        Stringable::macro('galerie', function () {
            /** @var Stringable $this */
            return new Stringable(Galerie::render((string) $this));
        });

        // Begriffs-Hover im redaktionellen HTML: str($html)->sanitizeHtml()->glossar()
        Stringable::macro('glossar', function () {
            /** @var Stringable $this */
            return new Stringable(app(Glossary::class)->annotate((string) $this));
        });

        // Registriert den Entra-ID-/Microsoft-365-Treiber für Socialite
        // (Pflichtenheft 3.1, 5.1). Tenant/Client-Daten kommen aus
        // config/services.php bzw. .env (ENTRA_*).
        Event::listen(SocialiteWasCalled::class, MicrosoftExtendSocialite::class);

        // Test-Umgebung: ausgehende Mails an die Testadresse umleiten
        // (F-MAIL-06), außer an freigeschaltete Tester*innen.
        Event::listen(MessageSending::class, TestRecipientFilter::class);

        // Hauptnavigation steht jeder Seite, die das Layout nutzt, ohne
        // Wiederholung in jedem Controller zur Verfügung (F-CMS-01).
        View::composer(['layouts.app', 'home'], function ($view) {
            $stageOrder = array_keys(config('dpsg.stages'));

            $view->with([
                // Hauptmenü: oberste Ebene, Unterseiten als Unterpunkte.
                'navPages' => Page::query()->published()->where('show_in_nav', true)->whereNull('parent_id')
                    ->with('navChildren')->orderBy('nav_sort')->get(),
                'stagePages' => Page::query()->published()->whereNotNull('stage')->get()
                    ->sortBy(fn (Page $page) => array_search($page->stage, $stageOrder, true))
                    ->unique('stage')
                    ->values(),
            ]);
        });
    }
}

<?php

namespace App\Providers;

use App\Mail\TestRecipientFilter;
use App\Models\Page;
use App\Services\Glossary;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

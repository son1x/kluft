<?php

namespace App\Providers;

use App\Models\Page;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
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
        // Registriert den Entra-ID-/Microsoft-365-Treiber für Socialite
        // (Pflichtenheft 3.1, 5.1). Tenant/Client-Daten kommen aus
        // config/services.php bzw. .env (ENTRA_*).
        Event::listen(SocialiteWasCalled::class, MicrosoftExtendSocialite::class);

        // Test-Umgebung: alle ausgehenden Mails umleiten (F-MAIL-06),
        // damit beim Testen keine echten Personen angeschrieben werden.
        if (filled($redirectTo = config('mail.test_redirect_to'))) {
            Mail::alwaysTo($redirectTo);
        }

        // Hauptnavigation steht jeder Seite, die das Layout nutzt, ohne
        // Wiederholung in jedem Controller zur Verfügung (F-CMS-01).
        View::composer('layouts.app', function ($view) {
            $view->with(
                'navPages',
                Page::query()->published()->where('show_in_nav', true)->orderBy('nav_sort')->get()
            );
        });
    }
}

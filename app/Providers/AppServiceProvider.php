<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
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
    }
}

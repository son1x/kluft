<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Resources\Gruppierungen\Pages\CreateGruppierung;
use App\Filament\Resources\Gruppierungen\Pages\EditGruppierung;
use App\Support\Features;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Kein Filament-eigenes Login-Formular: GET /admin/login leitet
            // direkt zur Entra-ID-Anmeldung weiter (Pflichtenheft 5.1,
            // Routen-Übersicht 11.2), mit der Funktion „Anmeldung per
            // E-Mail-Code“ zur Code-Anmeldung (dort auch der Weg zu M365).
            // Der Notfall-Super-Admin hat einen separaten, nicht verlinkten
            // Pfad (/notfall-login, s. 5.2).
            ->login(fn () => redirect()->route(Features::enabled(Features::CODE_LOGIN) ? 'auth.code' : 'auth.entra.redirect'))
            // Avatare lokal statt über ui-avatars.com (keine Namen an Dritte).
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // Nach „Anlegen“ bzw. „Speichern“ zurück zur Übersicht der Liste
            // statt auf der Bearbeiten-Seite zu bleiben.
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->colors([
                'primary' => Color::hex('#003056'), // dpsg-blau, s. Pflichtenheft 7.2
                'danger' => Color::hex('#810a1a'), // dpsg-rot
            ])
            // Menü thematisch: Gruppen in dieser Reihenfolge, darin nach navigationSort.
            ->navigationGroups([
                NavigationGroup::make('Inhalte'),
                NavigationGroup::make('Termine & Veranstaltungen'),
                NavigationGroup::make('Verband & Kontakt'),
                NavigationGroup::make('Kasse'),
                NavigationGroup::make('WhatsApp-Newsletter'),
                NavigationGroup::make('Verwaltung')->collapsed(),
                NavigationGroup::make('Mein Konto'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Kartenauswahl für den Standort von Bezirken/Stämmen (F-MAP-05),
            // nur auf den Seiten laden, die sie brauchen.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite('resources/js/map.js')"),
                scopes: [CreateGruppierung::class, EditGruppierung::class],
            )
            // Unten auf jeder Backend-Seite: Kluft-Logo, Name und Git-Revision.
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => view('kluft.fusszeile')->render(),
            );
    }
}

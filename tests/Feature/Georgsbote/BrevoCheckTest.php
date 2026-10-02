<?php

namespace Tests\Feature\Georgsbote;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * php artisan brevo:check – Diagnose der Georgsbrief-Anbindung.
 */
class BrevoCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.brevo.api_key' => 'xkeysib-geheim', 'services.brevo.newsletter_list_id' => 20, 'services.brevo.doi_template_id' => 7]);
    }

    #[TestDox('Alles in Ordnung: Schlüssel, Liste und aktive Vorlage mit {{ doubleoptin }}')]
    public function test_reports_working_setup(): void
    {
        Http::fake([
            '*/account' => Http::response(['email' => 'x@example.org']),
            '*/contacts/lists/20' => Http::response(['name' => 'Anmeldungen Homepage', 'uniqueSubscribers' => 22]),
            '*/smtp/templates/7' => Http::response(['name' => 'DOI', 'isActive' => true, 'htmlContent' => '<a href="{{ doubleoptin }}">Ja</a>', 'sender' => ['email' => 'info@dpsg-trier.de']]),
        ]);

        $this->artisan('brevo:check')
            ->expectsOutputToContain('✓ Liste #20: „Anmeldungen Homepage“')
            ->doesntExpectOutputToContain('geheim')
            ->assertSuccessful();
    }

    #[TestDox('Gesperrte Server-IP (zum Freigeben) und Vorlage ohne Bestätigungslink werden gemeldet, ohne E-Mail-Adressen')]
    public function test_reports_problems_without_personal_data(): void
    {
        Http::fake([
            '*/account' => Http::response(['code' => 'unauthorized', 'message' => 'We have detected you are using an unrecognised IP address 203.0.113.5. Contact a@b.de'], 401),
            '*/contacts/lists/20' => Http::response(['name' => 'Anmeldungen Homepage'], 200),
            '*/smtp/templates/7' => Http::response(['name' => 'DOI', 'isActive' => false, 'htmlContent' => '<p>Hallo</p>', 'sender' => ['email' => 'info@dpsg-trier.de']]),
        ]);

        $this->artisan('brevo:check')
            ->expectsOutputToContain('unrecognised IP address 203.0.113.5')
            ->expectsOutputToContain('✗ Link {{ doubleoptin }} enthalten')
            ->expectsOutputToContain('✗ aktiv')
            ->doesntExpectOutputToContain('a@b.de')
            ->assertFailed();
    }
}

<?php

namespace Tests\Feature\Mail;

use App\Models\User;
use App\Notifications\RolesGrantedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Benachrichtigungs-Mails (Laravel-Vorlage) auf Deutsch: „Gut Pfad“ statt
 * „Regards“, keine englischen Reste (lang/de.json).
 */
class MailGrussTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Rechte-Mail endet mit „Gut Pfad“ und dem Namen der Website, ohne englische Texte')]
    public function test_gruss(): void
    {
        app()->setLocale('de');
        config(['app.name' => 'DPSG Diözesanverband Trier']);
        $user = User::query()->create(['name' => 'Lisa Alm', 'email' => 'lisa@example.org', 'status' => 'active']);

        $html = (string) (new RolesGrantedNotification(['autor|own|'], false, 'Till'))->toMail($user)->render();

        $this->assertStringContainsString('Gut Pfad', $html);
        $this->assertStringContainsString('DPSG Diözesanverband Trier', $html);
        $this->assertStringContainsString('Falls der Knopf „Zum Redaktionsbereich“ nicht funktioniert', $html);
        foreach (['Regards', 'Hello!', 'having trouble', 'All rights reserved'] as $englisch) {
            $this->assertStringNotContainsString($englisch, $html);
        }
    }
}

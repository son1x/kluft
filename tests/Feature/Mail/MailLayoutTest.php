<?php

namespace Tests\Feature\Mail;

use App\Mail\EventRegistrationMail;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * E-Mails im Corporate Design: Logo der Gliederung, DPSG-Farben, deutscher
 * Fuß mit Kontakt, Impressum und Datenschutz.
 */
class MailLayoutTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Mail mit Logo (PNG, absolute Adresse), DPSG-Blau/Rot und Fuß mit Impressum und Datenschutz statt „All rights reserved“')]
    public function test_layout(): void
    {
        $event = Event::query()->create(['title' => 'Diözesanversammlung', 'slug' => 'dv', 'status' => 'open', 'starts_at' => now()->addMonth()]);
        $registration = $event->registrations()->create([
            'status' => 'confirmed', 'first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'mia@example.org',
            'consent_text_version' => 'x', 'consent_at' => now(),
        ]);

        $html = (new EventRegistrationMail($registration, 'doi', 'token'))->render();

        $logo = (string) config('dpsg.site.logo');
        if (str_ends_with($logo, '.png') && is_file(public_path($logo))) {
            $this->assertStringContainsString('<img src="'.asset($logo).'"', $html);
        } else {
            $this->assertStringContainsString(e((string) config('dpsg.site.name')), $html);
        }
        $this->assertStringContainsString('#003056', $html, 'Button in DPSG-Blau');
        $this->assertStringContainsString('#810a1a', $html, 'rote Akzentlinie');
        $this->assertStringContainsString(url('/impressum'), $html);
        $this->assertStringContainsString(url('/datenschutz'), $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
        $this->assertStringNotContainsString('laravel.com', $html);
    }
}

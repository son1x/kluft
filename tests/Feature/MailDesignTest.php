<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class MailDesignTest extends TestCase
{
    #[TestDox('Mail-Design liegt nicht in einem Ordner „vendor“ (den lädt der Deploy nicht hoch) und das Theme ist vorhanden')]
    public function test_mail_design_wird_deployt(): void
    {
        foreach ((array) config('mail.markdown.paths') as $pfad) {
            $this->assertStringNotContainsString('/vendor/', str_replace('\\', '/', $pfad.'/'));
            $this->assertFileExists($pfad.'/html/themes/'.config('mail.markdown.theme').'.css');
        }
    }
}

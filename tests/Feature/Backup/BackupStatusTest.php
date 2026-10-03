<?php

namespace Tests\Feature\Backup;

use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class BackupStatusTest extends TestCase
{
    #[TestDox('backup:status nennt fehlendes Passwort, ohne Geheimnisse auszugeben')]
    public function test_status_without_secrets(): void
    {
        config(['dpsg.backup.password' => null]);
        $this->artisan('backup:status')->expectsOutputToContain('BACKUP_PASSWORD: FEHLT')->assertSuccessful();

        config(['dpsg.backup.password' => 'ein-sehr-geheimes-passwort-123']);
        $this->artisan('backup:status')
            ->expectsOutputToContain('BACKUP_PASSWORD: gesetzt')
            ->doesntExpectOutputToContain('ein-sehr-geheimes')
            ->assertSuccessful();
    }
}

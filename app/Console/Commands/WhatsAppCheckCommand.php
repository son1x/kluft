<?php

namespace App\Console\Commands;

use App\Models\WhatsAppSubscriber;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Services\WhatsApp\WhatsAppTeam;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Zustand der WhatsApp-Anbindung prüfen (F-WA-OPS-02): Token, Nummer,
 * Qualität, Versandlimit, Vorlagen. Ergebnis für die Statusseite im Cache.
 */
class WhatsAppCheckCommand extends Command
{
    protected $signature = 'whatsapp:check';

    protected $description = 'Prüft die Verbindung zur WhatsApp Cloud API';

    public function handle(WhatsAppSettings $settings, WhatsAppClient $client, WhatsAppTeam $team): int
    {
        if (! $settings->configured()) {
            $this->warn('WhatsApp ist nicht eingerichtet (Backend → WhatsApp-Newsletter → Verbindung).');

            return self::SUCCESS;
        }

        try {
            $status = $client->phoneStatus();
        } catch (WhatsAppException $e) {
            Cache::forever('whatsapp:health', ['ok' => false, 'error' => $e->getMessage(), 'at' => now()->toIso8601String()]);
            $team->alert('Verbindung zu Meta gestört', $e->getMessage());
            $this->error('Fehler: '.$e->getMessage().' (Code '.$e->metaCode.')');

            return self::FAILURE;
        }

        Cache::forever('whatsapp:health', ['ok' => true, 'status' => $status, 'at' => now()->toIso8601String()]);

        $this->table(['Angabe', 'Wert'], [
            ['Nummer', ($status['display_phone_number'] ?? '–').' ('.($status['verified_name'] ?? '–').')'],
            ['Status', $status['status'] ?? '–'],
            ['Qualität', $status['quality_rating'] ?? '–'],
            ['Versandlimit', $status['messaging_limit_tier'] ?? '–'],
            ['Testmodus', config('whatsapp.test_mode') ? 'ja (nur Allowlist)' : 'nein'],
            ['Abonnent:innen aktiv', (string) WhatsAppSubscriber::query()->active()->count()],
            ['Vorlagen genehmigt', WhatsAppTemplate::query()->where('status', 'APPROVED')->count().' von '.WhatsAppTemplate::query()->count()],
        ]);

        if (in_array($status['quality_rating'] ?? null, ['RED', 'YELLOW'], true)) {
            $team->alert('Qualität der Nummer: '.$status['quality_rating'], 'Meta bewertet die Qualität der WhatsApp-Nummer als '.$status['quality_rating'].'. Bitte Aussendungen seltener und relevanter machen.');
        }

        return self::SUCCESS;
    }
}

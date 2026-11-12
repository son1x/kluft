<?php

namespace App\Models;

use App\Services\Mitglieder\MitgliederAbgleich;
use App\Support\Ausbau;
use App\Support\Features;
use App\Support\Zuschuesse;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Veranstaltung mit Online-Anmeldung, Pflichtenheft 6.7.
 *
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $reg_opens_at
 * @property Carbon|null $reg_closes_at
 * @property array<string, mixed>|null $form_fields
 */
class Event extends Model
{
    /** Datenfeld zur Stamm-Auswahl: „hier“ oder „gast“ (andere Gliederung/anderer Verband). */
    public const HERKUNFT = 'herkunft';

    /** Arten der eigenen Felder im Formular-Baukasten (form_fields.eigene). */
    public const EIGENE_TYPEN = [
        'text' => 'Kurzer Text',
        'textarea' => 'Langer Text',
        'zahl' => 'Zahl',
        'auswahl' => 'Auswahl (eine Antwort)',
        'mehrfach' => 'Auswahl (mehrere Antworten)',
    ];

    public const EIGENE_MAX = 15;

    public const STATUSES = [
        'draft' => 'Entwurf',
        'open' => 'Anmeldung offen',
        'closed' => 'Anmeldung geschlossen',
        'cancelled' => 'Abgesagt',
    ];

    /** Standard wie in der Datenbank, damit neue Modelle ihn auch vor dem Neuladen kennen. */
    protected $attributes = [
        'info_sammeladresse' => true,
        'info_verantwortlich' => true,
    ];

    protected $fillable = [
        'title', 'slug', 'teaser', 'description', 'starts_at', 'ends_at', 'location',
        'stage', 'section', 'target_group', 'cover_media_id', 'contact_name', 'contact_email', 'info_sammeladresse', 'info_verantwortlich', 'verantwortlich_email', 'erinnerung_tage', 'erinnerung_text', 'erinnerung_gesendet_am',
        'cost_info', 'reg_opens_at', 'reg_closes_at', 'capacity', 'waitlist_enabled', 'mehrere_personen', 'adults_only', 'member_check', 'member_gruppierung_id',
        'status', 'extern_freigegeben', 'form_fields', 'retention_days', 'created_by', 'calendar_uid',
        'certificate_enabled', 'certificate_text', 'certificate_signer', 'preise', 'zahlungsfrist_tage',
        'zuschuss', 'zuschuss_art', 'zuschuss_geber', 'zuschuss_finanzen', 'zuschuss_fern', 'zuschuss_bericht', 'zuschuss_antrag', 'zuschuss_stand',
        'foto_einwilligung', 'ganztaegig', 'zusatz_felder', 'teilnahmeliste_datei', 'teilnahmeliste_am',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reg_opens_at' => 'datetime',
            'reg_closes_at' => 'datetime',
            'waitlist_enabled' => 'boolean',
            'mehrere_personen' => 'boolean',
            'adults_only' => 'boolean',
            'extern_freigegeben' => 'boolean',
            'info_sammeladresse' => 'boolean',
            'foto_einwilligung' => 'boolean',
            'ganztaegig' => 'boolean',
            'sensible_geloescht_at' => 'datetime',
            'info_verantwortlich' => 'boolean',
            'erinnerung_tage' => 'integer',
            'erinnerung_gesendet_am' => 'datetime',
            'member_check' => 'boolean',
            'certificate_enabled' => 'boolean',
            'form_fields' => 'array',
            'preise' => 'array',
            'zusatz_felder' => 'array',
            'teilnahmeliste_am' => 'datetime',
            'zahlungsfrist_tage' => 'integer',
            'zuschuss' => 'boolean',
            'zuschuss_geber' => 'array',
            'zuschuss_finanzen' => 'array',
            'zuschuss_fern' => 'boolean',
            'zuschuss_bericht' => 'array',
            'zuschuss_antrag' => 'encrypted:array',
            'zuschuss_stand' => 'array',
            'capacity' => 'integer',
            'retention_days' => 'integer',
        ];
    }

    /**
     * Fotos mit Vier-Augen-Freigabe (Reiter „Fotos“).
     *
     * @return HasMany<EventFoto, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(EventFoto::class);
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * Fragen zur Veranstaltung im Nachrichten-Eingang.
     *
     * @return HasMany<ContactMessage, $this>
     */
    public function contactMessages(): HasMany
    {
        return $this->hasMany(ContactMessage::class);
    }

    /**
     * „Nur für Mitglieder von …“ (Stamm oder Bezirk); der Mitgliederabgleich
     * markiert, wer zu einer anderen Gruppierung gehört.
     *
     * @return BelongsTo<Gruppierung, $this>
     */
    public function memberGruppierung(): BelongsTo
    {
        return $this->belongsTo(Gruppierung::class, 'member_gruppierung_id');
    }

    /** „Nur für Mitglieder von …“ gesetzt und die Funktion eingeschaltet. */
    public function nurFuerMitglieder(): bool
    {
        return $this->member_gruppierung_id !== null && Features::enabled(Features::NUR_MITGLIEDER);
    }

    /** Teilnahmebescheinigung angeboten und die Funktion eingeschaltet. */
    public function bescheinigungAn(): bool
    {
        return (bool) $this->certificate_enabled && Features::enabled(Features::TEILNAHMEBESCHEINIGUNG);
    }

    /** z. B. „Mitglieder von Stamm Sankt Konrad“, „Mitglieder im Bezirk Trier“; null = offen für alle */
    public function zielgruppeMitglieder(): ?string
    {
        $g = $this->nurFuerMitglieder() ? $this->memberGruppierung : null;
        if (! $g) {
            return null;
        }

        return $g->typ === 'bezirk'
            ? 'Mitglieder im '.$g->displayName()
            : 'Mitglieder von '.(str_starts_with($g->name, 'Stamm') ? '' : 'Stamm ').$g->name;
    }

    /** @return HasMany<EventNachricht, $this> */
    public function nachrichten(): HasMany
    {
        return $this->hasMany(EventNachricht::class)->latest();
    }

    /**
     * Wer die Veranstaltung angelegt hat (Standard für „verantwortlich“).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    protected static function booted(): void
    {
        // Anmeldungen fallen per Fremdschlüssel weg; deren Dateien hier.
        static::deleting(function (self $event) {
            RegistrationDocument::purgeFor($event->registrations()->pluck('id'));
            // Festgeschriebene Teilnahmeliste (Vor Ort) mit löschen.
            if (filled($event->teilnahmeliste_datei)) {
                Storage::disk('local')->delete($event->teilnahmeliste_datei);
            }
        });

        // Eigene Felder bekommen einen festen Schlüssel, unter dem die Antworten
        // in den Anmeldungen stehen. So bleiben sie zugeordnet, auch wenn die
        // Felder umsortiert oder umbenannt werden.
        static::saving(function (self $event) {
            // Neues Datum oder andere Vorlaufzeit: noch einmal erinnern.
            if ($event->exists && $event->isDirty(['starts_at', 'erinnerung_tage']) && $event->starts_at?->isFuture()) {
                $event->erinnerung_gesendet_am = null;
            }
            $config = $event->form_fields;
            if (! is_array($config) || ! array_key_exists('eigene', $config)) {
                return;
            }
            $eigene = [];
            foreach (array_values(array_filter((array) $config['eigene'], 'is_array')) as $feld) {
                if (! preg_match('/^eigen_[a-z0-9]{4,12}$/', (string) ($feld['schluessel'] ?? ''))) {
                    $feld['schluessel'] = 'eigen_'.Str::lower(Str::random(8));
                }
                $eigene[] = $feld;
            }
            $config['eigene'] = array_slice($eigene, 0, self::EIGENE_MAX);
            $event->form_fields = $config;
        });
    }

    /**
     * Öffentlich sichtbar ist alles außer Entwürfen.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', '!=', 'draft');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('ends_at', '>=', now())
            ->orWhere(fn (Builder $q) => $q->whereNull('ends_at')->where('starts_at', '>=', now()->startOfDay())));
    }

    /**
     * Aktive Felder aus dem Baukasten in fester Reihenfolge.
     *
     * @return array<string, array{label: string, type: string, required: bool, options?: array<string, string>, default?: string, hint?: ?string, eigen?: true}>
     */
    public function formFields(): array
    {
        $config = $this->form_fields ?? [];
        $fields = [];

        $zuschuss = $this->zuschussAktiv();
        foreach (config('dpsg.event_fields') as $key => $field) {
            // Tage-Auswahl ergibt nur bei mehrtägigen Veranstaltungen Sinn.
            if ($field['type'] === 'days' && count($this->days()) < 2) {
                continue;
            }
            // Zuschussantrag: Anschrift, Geburtsdatum und Geschlecht sind Pflicht;
            // das Geburtsjahr ergibt sich aus dem Geburtsdatum.
            if ($zuschuss && $key === 'birth_year') {
                continue;
            }
            $modus = $zuschuss && in_array($key, Zuschuesse::FELDER, true) ? 'required' : ($config[$key] ?? null);
            if (in_array($modus, ['optional', 'required'], true)) {
                $fields[$key] = [...$field, 'required' => $modus === 'required'];
            }
            // Unter der Stamm-/Bezirksauswahl: „Gast aus einem anderen …“ (Ankreuzfeld).
            if ($key === 'stamm' && isset($fields['stamm']) && Features::enabled(Features::GAESTE)) {
                $fields[self::HERKUNFT] = ['label' => 'Herkunft', 'type' => 'herkunft', 'required' => false,
                    'options' => (array) config('dpsg.event_herkunft'), 'default' => 'hier'];
            }
        }

        // Eigene Felder vor den Anmerkungen, sonst am Ende.
        $eigene = $this->eigeneFelder();
        if ($eigene !== [] && isset($fields['comment'])) {
            $comment = $fields['comment'];
            unset($fields['comment']);

            return [...$fields, ...$eigene, 'comment' => $comment];
        }

        return [...$fields, ...$eigene];
    }

    /**
     * Eigene Felder dieser Veranstaltung (Formular-Baukasten → „Eigene Felder“),
     * im selben Format wie die festen Felder. Auswahlfelder ohne
     * Antwortmöglichkeiten fallen weg.
     *
     * @return array<string, array{label: string, type: string, required: bool, options?: array<string, string>, hint?: ?string, eigen: true}>
     */
    public function eigeneFelder(): array
    {
        $felder = [];
        foreach (array_values((array) (($this->form_fields ?? [])['eigene'] ?? [])) as $i => $feld) {
            $typ = is_array($feld) ? (string) ($feld['typ'] ?? '') : '';
            if (! isset(self::EIGENE_TYPEN[$typ]) || blank($feld['label'] ?? null)) {
                continue;
            }
            $optionen = in_array($typ, ['auswahl', 'mehrfach'], true) ? self::optionenListe($feld['optionen'] ?? null) : [];
            if (in_array($typ, ['auswahl', 'mehrfach'], true) && $optionen === []) {
                continue;
            }
            $schluessel = preg_match('/^eigen_[a-z0-9]{4,12}$/', (string) ($feld['schluessel'] ?? '')) ? (string) $feld['schluessel'] : 'eigen_'.$i;
            $felder[$schluessel] = [
                'label' => trim((string) $feld['label']),
                'type' => $typ === 'auswahl' ? 'select' : $typ,
                'required' => (bool) ($feld['pflicht'] ?? false),
                ...($optionen !== [] ? ['options' => array_combine($optionen, $optionen)] : []),
                'hint' => filled($feld['hinweis'] ?? null) ? trim((string) $feld['hinweis']) : null,
                'eigen' => true,
                // Besonders schützenswert (z. B. Gesundheit): nicht im Export,
                // früher gelöscht (docs/datenschutz-ueberblick.md).
                'sensibel' => (bool) ($feld['sensibel'] ?? false),
            ];
        }

        return $felder;
    }

    /**
     * Antwortmöglichkeiten: eine pro Zeile (oder als Liste), ohne Leerzeilen und Doppelte.
     *
     * @return list<string>
     */
    public static function optionenListe(mixed $optionen): array
    {
        $zeilen = is_array($optionen) ? $optionen : preg_split('/\R/', (string) $optionen);

        return array_values(array_unique(array_filter(array_map(fn ($z) => Str::limit(trim((string) $z), 150, ''), (array) $zeilen), 'filled')));
    }

    public function confirmedCount(): int
    {
        return $this->registrations()->where('status', 'confirmed')->count();
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->confirmedCount() >= $this->capacity;
    }

    public function spotsLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->confirmedCount());
    }

    public function registrationClosesAt(): CarbonInterface
    {
        return $this->reg_closes_at ?? $this->starts_at;
    }

    /**
     * Ob gerade Anmeldungen angenommen werden (Status, Zeitraum, Plätze).
     */
    public function acceptsRegistrations(): bool
    {
        // Online-Anmeldung erst ab Ausbaustufe „Anmeldungen“.
        if ($this->status !== 'open' || ! Ausbau::anmeldungen()) {
            return false;
        }
        if ($this->reg_opens_at?->isFuture() || $this->registrationClosesAt()->isPast()) {
            return false;
        }

        return ! $this->isFull() || $this->waitlist_enabled;
    }

    /**
     * Grund, warum keine Anmeldung möglich ist (für die Detailseite).
     */
    public function registrationState(): string
    {
        return match (true) {
            $this->status === 'cancelled' => 'abgesagt',
            // Ausbaustufe ohne Online-Anmeldung: Anmeldung direkt bei der Leitung.
            ! Ausbau::anmeldungen() => 'ohne',
            $this->status !== 'open' || $this->registrationClosesAt()->isPast() => 'geschlossen',
            (bool) $this->reg_opens_at?->isFuture() => 'bald',
            $this->isFull() && ! $this->waitlist_enabled => 'ausgebucht',
            $this->isFull() => 'warteliste',
            default => 'offen',
        };
    }

    /**
     * Löschdatum der Anmeldedaten (9.3: Standard 90 Tage nach Ende).
     */
    /** Mit Mitgliederabgleich (Treiber eingerichtet und hier eingeschaltet)? */
    public function checksMembers(): bool
    {
        return $this->member_check && MitgliederAbgleich::eingerichtet();
    }

    /**
     * Einwilligungstext der Anmeldung; mit Mitgliederabgleich um dessen
     * Hinweis ergänzt (eigene Version, damit nachweisbar bleibt, was galt).
     */
    /** Zuschuss beantragen: an der Veranstaltung eingeschaltet und Funktion „Zuschüsse“ an. */
    public function zuschussAktiv(): bool
    {
        return $this->zuschuss && Features::enabled(Features::ZUSCHUESSE);
    }

    /**
     * Schlüssel der sensiblen Felder (z. B. Gesundheit, Ernährung).
     *
     * @return list<string>
     */
    public function sensibleFelder(): array
    {
        return array_keys(array_filter($this->formFields(), fn (array $f) => $f['sensibel'] ?? false));
    }

    /** Sensible Angaben werden so viele Tage nach dem Ende gelöscht. */
    public static function sensibleTage(): int
    {
        return max(1, (int) config('dpsg.sensible_tage', 14));
    }

    public function consentText(): string
    {
        $text = __((string) config('dpsg.event_consent.text'), ['tage' => $this->retention_days]);
        if ($this->sensibleFelder() !== []) {
            $text .= ' Besonders geschützte Angaben (z. B. zur Gesundheit) sieht nur die Veranstaltungsleitung; wir löschen sie '.self::sensibleTage().' Tage nach der Veranstaltung.';
        }
        if ($this->zuschussAktiv()) {
            $text .= ' '.Zuschuesse::EINWILLIGUNG;
        }

        return $this->checksMembers() ? $text.' '.config('mitglieder.consent.text') : $text;
    }

    public function consentVersion(): string
    {
        $version = (string) config('dpsg.event_consent.version').($this->zuschussAktiv() ? '+zuschuss-1' : '').($this->sensibleFelder() !== [] ? '+sensibel-1' : '');

        return $this->checksMembers() ? $version.'+mitglieder-'.config('mitglieder.consent.version') : $version;
    }

    public function purgeAt(): CarbonInterface
    {
        return ($this->ends_at ?? $this->starts_at)->copy()->endOfDay()->addDays($this->retention_days ?: 90);
    }

    /** Zielgruppe „Leiter*innen“: keine Altersstufe, nur bei Veranstaltungen. */
    public const LEITENDE = 'leitende';

    /**
     * Auswahl „Stufe“ bei Veranstaltungen: die Altersstufen und Leiter*innen.
     *
     * @return array<string, string>
     */
    public static function stufen(): array
    {
        return [...collect((array) config('dpsg.stages'))->map(fn (array $s) => $s['label'])->all(), self::LEITENDE => 'Leiter*innen'];
    }

    public function scopeLabel(): string
    {
        if ($this->stage) {
            return self::stufen()[$this->stage] ?? $this->stage;
        }
        if ($this->section) {
            return config("dpsg.sections.{$this->section}.label", $this->section);
        }

        return (string) config('dpsg.site.level');
    }

    /**
     * Kalendertage der Veranstaltung, z. B. ['2026-11-20' => 'Fr, 20.11.', …]
     * (höchstens 31 Tage).
     *
     * @return array<string, string>
     */
    public function days(): array
    {
        // Ungespeicherte Veranstaltung im Formular: noch kein Beginn.
        if ($this->getAttribute('starts_at') === null) {
            return [];
        }

        $end = ($this->ends_at ?? $this->starts_at)->copy()->startOfDay();
        $days = [];
        for ($day = $this->starts_at->copy()->startOfDay(); $day->lte($end) && count($days) < 31; $day = $day->copy()->addDay()) {
            $days[$day->toDateString()] = $day->locale('de')->isoFormat('dd, D.M.');
        }

        return $days;
    }

    /**
     * Lesbarer Wert eines Baukasten-Felds (Teilnehmerliste, Export).
     *
     * @param  array{type: string, options?: array<string, string>, default?: string}  $field
     */
    public function fieldValueLabel(array $field, mixed $value): ?string
    {
        // Ohne Angabe (z. B. ältere Anmeldungen): von hier.
        if ($field['type'] === 'herkunft' && ($value === null || $value === '')) {
            $value = $field['default'] ?? null;
        }
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($field['type']) {
            'select', 'herkunft' => $field['options'][$value] ?? (string) $value,
            'date' => Carbon::parse((string) $value)->format('d.m.Y'),
            'days' => array_values((array) $value) === array_keys($this->days())
                ? 'ganze Zeit'
                : collect((array) $value)->map(fn (string $d) => $this->days()[$d] ?? $d)->implode(' · '),
            default => is_array($value) ? implode(', ', $value) : (string) $value,
        };
    }

    public function dateLabel(): string
    {
        $start = $this->starts_at->locale('de');
        $end = $this->ends_at?->locale('de');

        if (! $end || $end->isSameDay($start)) {
            $label = $start->isoFormat('dd, D. MMMM YYYY');
            $time = ! $this->ganztaegig && $start->format('H:i') !== '00:00' ? ', '.$start->format('H:i').($end ? '–'.$end->format('H:i') : '').' Uhr' : '';

            return $label.$time;
        }

        return $start->isoFormat($start->year === $end->year ? 'D. MMMM' : 'D. MMMM YYYY').' – '.$end->isoFormat('D. MMMM YYYY');
    }

    /**
     * Ob die angegebene Person (nach Geburtsjahr) bei Veranstaltungsbeginn
     * jünger als das Einwilligungsalter sein könnte. Ohne Monat/Tag wird
     * vorsichtig gerechnet: im Zweifel ist die Einwilligung nötig.
     */
    public function mayBeUnderConsentAge(?int $birthYear): bool
    {
        return $birthYear !== null
            && $this->starts_at->year - $birthYear <= config('dpsg.guardian_consent_age', 16);
    }

    /**
     * Preisstufen des Teilnahmebeitrags (docs/zahlungen.md), z. B.
     * „Mitglied“ 120 €, „Nichtmitglied“ 140 €. Leer = kein Beitrag.
     *
     * @return list<array{bezeichnung: string, cent: int}>
     */
    public function preise(): array
    {
        return collect((array) $this->preise)
            ->filter(fn ($p) => is_array($p) && filled($p['bezeichnung'] ?? null) && (int) ($p['cent'] ?? 0) > 0)
            ->map(fn (array $p) => ['bezeichnung' => mb_substr(trim((string) $p['bezeichnung']), 0, 100), 'cent' => (int) $p['cent']])
            ->values()
            ->all();
    }

    /** Höchstzahl der Zusatzinfos je Veranstaltung. */
    public const ZUSATZ_MAX = 6;

    /**
     * Zusatzinfos fürs Team (docs/vor-ort.md), z. B. „Zimmer“ oder „Zelt“:
     * nicht im Anmeldeformular, Werte trägt das Team ein. Schlüssel aus der
     * Bezeichnung, damit Werte beim Umsortieren erhalten bleiben.
     *
     * @return array<string, string> Schlüssel => Bezeichnung
     */
    public function zusatzFelder(): array
    {
        $felder = [];
        foreach ((array) $this->zusatz_felder as $label) {
            $label = mb_substr(trim((string) $label), 0, 60);
            $key = Str::slug($label, '_');
            if ($label !== '' && $key !== '' && ! isset($felder[$key]) && count($felder) < self::ZUSATZ_MAX) {
                $felder[$key] = $label;
            }
        }

        return $felder;
    }

    public function hatTeilnahmebeitrag(): bool
    {
        return $this->preise() !== [];
    }

    /** Zahlungsfrist ab heute (Standard 14 Tage), höchstens bis zum Vortag des Beginns. */
    public function zahlungFaelligAm(): Carbon
    {
        $frist = now()->addDays($this->zahlungsfrist_tage ?: 14)->startOfDay();
        $spaetestens = $this->starts_at?->copy()->subDay()->startOfDay();

        return $spaetestens && $spaetestens->isFuture() && $spaetestens->lt($frist) ? $spaetestens : $frist;
    }
}

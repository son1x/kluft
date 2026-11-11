<?php

namespace App\Support;

use App\Models\RoleAssignment;
use App\Models\Rolle;
use App\Models\User;

/**
 * Rechte-Matrix (docs/rollen.md): Katalog der Einzelrechte, Vorlagen für die
 * mitgelieferten Rollen und eigene Rollen der Website (Tabelle `rollen`).
 *
 * Eine Rolle ist ein Bündel von Rechten. Mitgelieferte Rollen sind fest und
 * bilden das bisherige Verhalten ab; eigene Rollen legen Admins in der
 * Matrix an (meist als Kopie einer Vorlage). Ob ein Recht für die ganze
 * Website oder nur für einen Bereich (Stufe, Fachbereich) gilt, bestimmt die
 * Zuweisung: global oder mit Bereich.
 */
class Rechte
{
    /**
     * Einzelrechte: Gruppe, Bezeichnung, Erklärung; „bereich“ = kann je
     * Bereich gelten (sonst nur für die ganze Website); „sensibel“ = Zugriff
     * auf besonders schützenswerte Daten oder Rechte.
     *
     * @var array<string, array{gruppe: string, label: string, text: string, bereich: bool, sensibel?: bool}>
     */
    public const KATALOG = [
        'seiten' => ['gruppe' => 'Inhalte', 'label' => 'Seiten bearbeiten', 'text' => 'Seiten anlegen, bearbeiten und veröffentlichen (für die ganze Website: auch löschen)', 'bereich' => true],
        'vorstandsseiten' => ['gruppe' => 'Inhalte', 'label' => 'Vorstandsseiten', 'text' => 'Seite der Leitung bzw. des Vorstands und ihre Unterseiten pflegen', 'bereich' => false],
        'beitraege' => ['gruppe' => 'Inhalte', 'label' => 'Beiträge freigeben', 'text' => 'Beiträge anderer bearbeiten und veröffentlichen (eigene schreiben alle mit Rolle)', 'bereich' => true],
        'mediathek' => ['gruppe' => 'Inhalte', 'label' => 'Mediathek verwalten', 'text' => 'alle Bilder bearbeiten und löschen (eigene: alle)', 'bereich' => false],
        'website' => ['gruppe' => 'Inhalte', 'label' => 'Website-Bausteine', 'text' => 'Personen, Gruppierungen, Downloads, Georgsbote, Glossar, Gruppenfotos, Kontakt-Themen', 'bereich' => false],
        'gruppenstunden' => ['gruppe' => 'Inhalte', 'label' => 'Gruppenstunden', 'text' => 'Gruppenstunden der Stufe pflegen', 'bereich' => true],

        'veranstaltungen.lesen' => ['gruppe' => 'Veranstaltungen', 'label' => 'Veranstaltungen ansehen', 'text' => 'Veranstaltungen und eine sparsame Teilnehmendenliste (Name, Stamm, Tage, Status)', 'bereich' => true],
        'veranstaltungen.bearbeiten' => ['gruppe' => 'Veranstaltungen', 'label' => 'Veranstaltungen bearbeiten', 'text' => 'anlegen, bearbeiten, Teilnehmende verwalten, volle Liste, Export, Mails', 'bereich' => true, 'sensibel' => true],
        'veranstaltungen.uebersicht' => ['gruppe' => 'Veranstaltungen', 'label' => 'Alle Veranstaltungen (ohne Teilnehmende)', 'text' => 'alle Veranstaltungen mit Anzahlen sehen, keine Teilnehmerdaten', 'bereich' => false],
        'zuschuesse' => ['gruppe' => 'Veranstaltungen', 'label' => 'Zuschüsse', 'text' => 'Zuschuss-Übersicht und Zuschussangaben bei Personen', 'bereich' => false],

        'nachrichten' => ['gruppe' => 'Kommunikation', 'label' => 'Nachrichten-Eingang', 'text' => 'Nachrichten aus dem Kontaktformular lesen und bearbeiten', 'bereich' => true],
        'umfragen' => ['gruppe' => 'Kommunikation', 'label' => 'Umfragen', 'text' => 'Umfragen anlegen und auswerten', 'bereich' => false],
        'videokonferenzen' => ['gruppe' => 'Kommunikation', 'label' => 'Videokonferenzen', 'text' => 'eigene Konferenzen anlegen', 'bereich' => false],
        'videokonferenzen.alle' => ['gruppe' => 'Kommunikation', 'label' => 'Alle Videokonferenzen', 'text' => 'Konferenzen aller sehen und bearbeiten', 'bereich' => false],
        'adressbuch' => ['gruppe' => 'Kommunikation', 'label' => 'Adressbuch', 'text' => 'Kontakte und eigene bzw. gemeinsame Verteiler', 'bereich' => false],
        'adressbuch.alle' => ['gruppe' => 'Kommunikation', 'label' => 'Adressbuch: alle Verteiler', 'text' => 'auch private Verteiler anderer sehen und bearbeiten', 'bereich' => false],
        'whatsapp.entwurf' => ['gruppe' => 'Kommunikation', 'label' => 'WhatsApp-Entwürfe', 'text' => 'Aussendungen für den WhatsApp-Newsletter entwerfen', 'bereich' => false],
        'aufrufe' => ['gruppe' => 'Kommunikation', 'label' => 'Seitenaufrufe', 'text' => 'Statistik der Seitenaufrufe (ohne Personenbezug)', 'bereich' => false],

        'autoren' => ['gruppe' => 'Personen und Rechte', 'label' => 'Autor:innen freischalten', 'text' => 'Konten anlegen und die Rolle Autor:in vergeben', 'bereich' => true],
        'benutzer' => ['gruppe' => 'Personen und Rechte', 'label' => 'Benutzer und alle Rollen', 'text' => 'alle Konten und Rollen verwalten (außer Admin)', 'bereich' => false, 'sensibel' => true],

        'kasse' => ['gruppe' => 'Sensibel', 'label' => 'Kasse', 'text' => 'Zahlungen, Teilnahmebeiträge, Auslagen prüfen und auszahlen', 'bereich' => false, 'sensibel' => true],
        'praevention' => ['gruppe' => 'Sensibel', 'label' => 'Prävention', 'text' => 'Präventions-Nachweise der Leitenden', 'bereich' => false, 'sensibel' => true],
        'datenschutz' => ['gruppe' => 'Sensibel', 'label' => 'Datenschutz-Anfragen', 'text' => 'Auskunft und Löschung zu einer E-Mail-Adresse', 'bereich' => false, 'sensibel' => true],
        'materialverleih' => ['gruppe' => 'Sonstiges', 'label' => 'Materialverleih', 'text' => 'Material und Reservierungen', 'bereich' => false],
    ];

    /** Präfix der Schlüssel eigener Rollen (role_assignments.role, max. 30 Zeichen). */
    public const EIGEN = 'r_';

    public static function istEigen(string $role): bool
    {
        return str_starts_with($role, self::EIGEN);
    }

    /**
     * Vorlagen: Rechte der mitgelieferten Rollen. Bilden das bisherige
     * Verhalten ab und hängen von der Website ab (Ebene, Lese-Modus).
     *
     * @return array<string, array{name: string, geltung: string, rechte: list<string>}>
     */
    public static function vorlagen(): array
    {
        $stamm = config('dpsg.site.level') === 'Stamm';
        $leitungBearbeitet = ! RoleCatalog::leitungLiestVeranstaltungen();
        $leitung = ['seiten', 'beitraege', 'nachrichten', 'autoren', 'umfragen', 'videokonferenzen', 'adressbuch', 'veranstaltungen.lesen',
            ...($leitungBearbeitet ? ['veranstaltungen.bearbeiten'] : [])];
        $redaktion = ['seiten', 'beitraege', 'mediathek', 'website', 'gruppenstunden', 'veranstaltungen.lesen', 'veranstaltungen.bearbeiten',
            'zuschuesse', 'nachrichten', 'umfragen', 'videokonferenzen', 'videokonferenzen.alle', 'adressbuch', 'adressbuch.alle',
            'whatsapp.entwurf', 'aufrufe', 'autoren', 'materialverleih'];

        $vorlagen = [
            'stufenadmin' => ['name' => 'Stufenadmin', 'geltung' => 'bereich', 'rechte' => [...$leitung, 'gruppenstunden']],
            'sektionsadmin' => ['name' => 'Sektionsadmin', 'geltung' => 'bereich', 'rechte' => $leitung],
            'veranstaltungen' => ['name' => 'Veranstaltungen bearbeiten', 'geltung' => 'bereich', 'rechte' => ['veranstaltungen.lesen', 'veranstaltungen.bearbeiten']],
            'vorstand' => ['name' => 'Vorstand', 'geltung' => 'global', 'rechte' => ['vorstandsseiten', 'beitraege', 'mediathek', 'veranstaltungen.uebersicht',
                'umfragen', 'videokonferenzen', 'adressbuch', 'whatsapp.entwurf', 'aufrufe', 'praevention']],
            'bildungsreferent' => ['name' => 'Bildungsreferent*in', 'geltung' => 'global', 'rechte' => $redaktion],
            'verwaltung' => ['name' => 'Verwaltung', 'geltung' => 'global', 'rechte' => [...$redaktion, 'benutzer', 'praevention', 'datenschutz', ...($stamm ? [] : ['kasse'])]],
            'kasse' => ['name' => 'Kasse', 'geltung' => 'global', 'rechte' => ['kasse', 'zuschuesse']],
            'material' => ['name' => 'Materialwart*in', 'geltung' => 'global', 'rechte' => ['materialverleih']],
        ];
        if ($stamm) {
            unset($vorlagen['bildungsreferent']);
        }
        if ($leitungBearbeitet) {
            unset($vorlagen['veranstaltungen']);
        }

        return $vorlagen;
    }

    /**
     * Rechte einer Zuweisung: Vorlage der mitgelieferten Rolle oder die
     * gespeicherten Häkchen einer eigenen Rolle.
     *
     * @return list<string>
     */
    public static function fuerRolle(string $role): array
    {
        if (self::istEigen($role)) {
            return self::eigene()[$role]->rechte ?? [];
        }

        return self::vorlagen()[$role]['rechte'] ?? [];
    }

    /**
     * Eigene Rollen der Website, je Anfrage einmal geladen.
     *
     * @return array<string, Rolle>
     */
    public static function eigene(): array
    {
        return app(RollenRegister::class)->alle();
    }

    /**
     * Gilt die Zuweisung für die ganze Website (nicht nur für einen Bereich)?
     * Bereichsrollen (z. B. Stufenadmin) nie, auch nicht bei unsauberen Daten.
     */
    public static function global(RoleAssignment $a): bool
    {
        return $a->scope_type === 'global' && self::geltung($a->role) !== 'bereich';
    }

    /** Geltung einer Rolle: 'global' oder 'bereich' (unbekannt: 'global'). */
    public static function geltung(string $role): string
    {
        if (self::istEigen($role)) {
            return self::eigene()[$role]->geltung ?? 'global';
        }

        return self::vorlagen()[$role]['geltung'] ?? (in_array($role, ['stufenadmin', 'sektionsadmin', 'veranstaltungen'], true) ? 'bereich' : 'global');
    }

    /** Darf ein Recht in einer Rolle mit dieser Geltung stehen? */
    public static function passtZuGeltung(string $recht, string $geltung): bool
    {
        return $geltung === 'global' || (self::KATALOG[$recht]['bereich'] ?? false);
    }

    /** Enthält eine Rechte-Liste sensible Rechte (Hinweis bei Vergabe)? */
    public static function sensibel(array $rechte): bool
    {
        return collect($rechte)->contains(fn (string $r) => self::KATALOG[$r]['sensibel'] ?? false);
    }

    /**
     * Hat die Person das Recht – irgendwo (ohne Bereich) oder in einem
     * bestimmten Bereich? Globale Zuweisungen gelten überall. Admins dürfen
     * alles.
     */
    public static function darf(User $user, string $recht, ?string $typ = null, ?string $key = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        foreach ($user->roleAssignments as $a) {
            if (! in_array($recht, self::fuerRolle($a->role), true)) {
                continue;
            }
            if (self::global($a) || $typ === null || ($a->scope_type === $typ && $a->scope_key === $key)) {
                return true;
            }
        }

        return false;
    }

    /** Hat die Person das Recht für die ganze Website? */
    public static function ueberall(User $user, string $recht): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->roleAssignments->contains(fn (RoleAssignment $a) => self::global($a) && in_array($recht, self::fuerRolle($a->role), true));
    }

    /**
     * Bereiche (Stufen bzw. Fachbereiche), in denen die Person das Recht hat
     * – ohne globale Zuweisungen (die prüft ueberall()).
     *
     * @return list<string>
     */
    public static function bereiche(User $user, string $recht, string $typ): array
    {
        return $user->roleAssignments
            ->filter(fn (RoleAssignment $a) => $a->scope_type === $typ && filled($a->scope_key) && in_array($recht, self::fuerRolle($a->role), true))
            ->pluck('scope_key')->unique()->values()->all();
    }
}

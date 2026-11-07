<?php

namespace App\Support;

use App\Models\GeorgsboteIssue;

/**
 * Archivliste je Instanz (instanzen/<instanz>/dateien/georgsbote-archiv.php):
 * Titel und Kurzinhalt für Georgsbote und Georgsbrief. Wird beim Import
 * und auf Knopfdruck übernommen; im Backend Geändertes bleibt.
 */
class GeorgsboteArchiv
{
    /**
     * @return array{title: string|null, inhalt: string|null}|null
     */
    public static function eintrag(string $art, int $year, int $no): ?array
    {
        $liste = self::liste();
        foreach ($liste[$art] ?? [] as $zeile) {
            if ((int) $zeile[0] === $year && (int) $zeile[1] === $no) {
                return $art === GeorgsboteIssue::BRIEF
                    ? ['title' => null, 'inhalt' => $zeile[2] ?? null]
                    : ['title' => $zeile[2] ?? null, 'inhalt' => $zeile[3] ?? null];
            }
        }

        return null;
    }

    /**
     * Titel (nur wenn noch der Vorgabe-Titel oder leer) und Inhalt (nur
     * wenn leer) aus der Archivliste setzen. Gibt zurück, ob sich etwas
     * geändert hat.
     */
    public static function anwenden(GeorgsboteIssue $issue): bool
    {
        $eintrag = self::eintrag((string) $issue->art, (int) $issue->year, (int) $issue->issue_no);
        if ($eintrag === null) {
            return false;
        }

        $standard = GeorgsboteIssue::standardTitel((string) $issue->art, (int) $issue->year, (int) $issue->issue_no);
        if (filled($eintrag['title']) && (blank($issue->title) || $issue->title === $standard)) {
            $issue->title = $eintrag['title'];
        }
        if (filled($eintrag['inhalt']) && blank($issue->inhalt)) {
            $issue->inhalt = $eintrag['inhalt'];
        }

        if (! $issue->isDirty()) {
            return false;
        }
        $issue->save();

        return true;
    }

    public static function vorhanden(): bool
    {
        return self::liste() !== [];
    }

    /**
     * @return array{bote?: list<array<int, mixed>>, brief?: list<array<int, mixed>>}
     */
    private static function liste(): array
    {
        $datei = base_path('instanzen/'.config('dpsg.instanz').'/dateien/georgsbote-archiv.php');

        return is_file($datei) ? (array) require $datei : [];
    }
}

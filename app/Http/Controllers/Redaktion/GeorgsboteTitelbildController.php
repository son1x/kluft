<?php

namespace App\Http\Controllers\Redaktion;

use App\Http\Controllers\Controller;
use App\Models\GeorgsboteIssue;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Titelbilder der Georgsboten aus Seite 1 des PDFs (F-GB-01,
 * docs/auftrag-titelbilder.md): Der Browser der Redaktion rendert das PDF
 * mit pdf.js (resources/js/titelbild.js) und schickt das Bild hierher.
 */
class GeorgsboteTitelbildController extends Controller
{
    /** Höchstgröße des Titelbilds in KB. */
    public const MAX_KB = 2048;

    /**
     * PDF für die Redaktion, auch unveröffentlicht (Titelbilder entstehen vor
     * der Veröffentlichung). Zählt keine Downloads.
     */
    public function pdf(Request $request, GeorgsboteIssue $issue): StreamedResponse
    {
        $this->darf($request, $issue);
        abort_unless($issue->pdf_path && Storage::disk('local')->exists($issue->pdf_path), 404);

        return Storage::disk('local')->response($issue->pdf_path, "{$issue->art}-{$issue->year}-{$issue->issue_no}.pdf", [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function store(Request $request, GeorgsboteIssue $issue): JsonResponse
    {
        $this->darf($request, $issue);

        if ($issue->istBrief()) {
            return $this->fehler('Georgsbriefe bekommen kein Titelbild.', 422);
        }

        // „image“ lässt SVG nicht zu (erst mit image:allow_svg).
        $validator = Validator::make($request->all(), [
            'bild' => ['required', 'file', 'image', 'mimes:webp,jpeg,png', 'max:'.self::MAX_KB, 'dimensions:max_width=2000,max_height=3000'],
            'ueberschreiben' => ['sometimes', 'boolean'],
        ], [], ['bild' => 'Titelbild']);

        if ($validator->fails()) {
            return $this->fehler($validator->errors()->first(), 422);
        }

        if ($issue->cover_path && ! $request->boolean('ueberschreiben')) {
            return $this->fehler('Es gibt schon ein Titelbild. Zum Ersetzen „überschreiben“ wählen.', 409);
        }

        $datei = $request->file('bild');
        $endung = match ($datei->getMimeType()) {
            'image/webp' => 'webp',
            'image/png' => 'png',
            default => 'jpg',
        };
        // Zufälliger Teil im Namen: Browser zeigen kein altes Bild aus dem Cache.
        $pfad = $datei->storeAs('georgsbote', sprintf('titel-%d-%d-%s.%s', $issue->year, $issue->issue_no, Str::lower(Str::random(10)), $endung), 'public');
        if (! $pfad) {
            return $this->fehler('Das Titelbild konnte nicht gespeichert werden.', 500);
        }

        $alt = $issue->cover_path;
        $issue->forceFill(['cover_path' => $pfad])->save();
        if ($alt && $alt !== $pfad) {
            Storage::disk('public')->delete($alt);
        }

        AuditLogger::log('georgsbote.titelbild', $request->user(), GeorgsboteIssue::class, $issue->id);

        return response()->json(['ok' => true, 'cover_url' => $issue->coverUrl()]);
    }

    /** Wie im Backend: aktives Konto mit Recht, die Ausgabe zu bearbeiten. */
    private function darf(Request $request, GeorgsboteIssue $issue): void
    {
        abort_unless($request->user()?->isActive(), 403);
        Gate::authorize('update', $issue);
    }

    private function fehler(string $grund, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'grund' => $grund], $status);
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download-Seite: Dateien nach Kategorie, mit Suche und Filter im Browser.
 * Die Dateien liegen privat und werden hier ausgeliefert.
 */
class DownloadController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Download::categories();
        $alle = Download::query()->published()->orderBy('sort')->orderBy('title')->get()->groupBy('category');
        // Reihenfolge der Kategorien wie in der Konfiguration, Unbekanntes zuletzt.
        $groups = $alle->sortBy(fn ($items, string $key) => array_search($key, array_keys($categories), true) === false
            ? PHP_INT_MAX : array_search($key, array_keys($categories), true));

        return view('downloads.index', [
            'intro' => Page::query()->published()->where('slug', config('dpsg.downloads.slug'))->first(),
            'title' => config('dpsg.downloads.title'),
            'groups' => $groups,
            'categories' => $categories,
            'active' => array_key_exists((string) $request->query('kategorie'), $categories) ? $request->query('kategorie') : '',
        ]);
    }

    public function file(Download $download, string $name): StreamedResponse
    {
        abort_unless($download->published && $download->rights_confirmed, 404);
        abort_unless(Storage::disk(Download::DISK)->exists($download->file_path), 404);

        // Zähler ohne Personenbezug.
        $download->increment('downloads');

        // PDFs und Bilder im Browser öffnen, alles andere herunterladen.
        $inline = in_array($download->extension(), ['pdf', 'jpg', 'jpeg', 'png'], true);

        return Storage::disk(Download::DISK)->response($download->file_path, $download->downloadName(), [
            'Content-Type' => $download->mime ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * 301-Weiterleitungen alter Adressen (Pflichtenheft F-MIG-05). Greift nur,
 * wenn die Adresse sonst „nicht gefunden“ wäre – bestehende Seiten haben
 * immer Vorrang.
 */
class LegacyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $response;
        }

        $map = Cache::rememberForever(Redirect::CACHE_KEY, fn () => Schema::hasTable('redirects')
            ? Redirect::query()->pluck('to_path', 'from_path')->all()
            : []);

        $from = Redirect::normalize($request->getPathInfo());
        $to = $map[$from] ?? null;
        if ($to === null || Redirect::normalize($to) === $from) {
            return $response;
        }

        $redirect = Redirect::query()->where('from_path', $from)->first();
        if (! $redirect) {
            return $response;
        }
        // Zählen, ohne updated_at/Cache anzufassen.
        Redirect::query()->whereKey($redirect->id)->increment('hits', 1, ['last_hit_at' => now()]);

        return redirect()->to($to, in_array($redirect->status_code, [301, 302, 307, 308], true) ? $redirect->status_code : 301);
    }
}

<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Suche im Entra-Verzeichnis über Microsoft Graph (Pflichtenheft 5.6,
 * F-USR-02): delegiert mit User.ReadBasic.All, also im Namen der
 * angemeldeten Person und nur mit Name, E-Mail und Konto-ID.
 *
 * Der Graph-Token wird nur in der Sitzung gehalten (verschlüsselt) und
 * nirgends gespeichert; nach Ablauf verbindet man sich neu.
 */
class EntraDirectory
{
    private const SESSION_KEY = 'entra.graph';

    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    public function remember(string $token, int $expiresIn): void
    {
        session()->put(self::SESSION_KEY, Crypt::encryptString(json_encode([
            'token' => $token,
            // Etwas Puffer, damit der Token nicht mitten in der Suche abläuft.
            'expires_at' => now()->addSeconds(max(0, $expiresIn - 120))->getTimestamp(),
        ], JSON_THROW_ON_ERROR)));
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function connected(): bool
    {
        return $this->token() !== null;
    }

    /**
     * @return list<array{id: string, name: string, email: string, upn: string}>
     *
     * @throws DirectoryUnavailable wenn die Verbindung fehlt oder abgelaufen ist
     */
    public function search(string $query): array
    {
        // Anführungszeichen und Backslash würden die $search-Syntax brechen.
        $query = trim(mb_substr(str_replace(['"', '\\'], '', $query), 0, 64));
        if (mb_strlen($query) < 2) {
            return [];
        }

        $response = $this->request('/users', [
            '$search' => "\"displayName:{$query}\" OR \"mail:{$query}\" OR \"userPrincipalName:{$query}\"",
            '$select' => 'id,displayName,mail,userPrincipalName',
            '$orderby' => 'displayName',
            '$top' => 10,
        ]);

        return array_values(array_map($this->person(...), $response['value'] ?? []));
    }

    /**
     * @return array{id: string, name: string, email: string, upn: string}|null
     *
     * @throws DirectoryUnavailable
     */
    public function find(string $id): ?array
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            return null;
        }

        $response = $this->request('/users/'.$id, ['$select' => 'id,displayName,mail,userPrincipalName']);

        return isset($response['id']) ? $this->person($response) : null;
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array{id: string, name: string, email: string, upn: string}
     */
    private function person(array $user): array
    {
        $upn = mb_strtolower((string) ($user['userPrincipalName'] ?? ''));

        return [
            'id' => (string) $user['id'],
            'name' => (string) ($user['displayName'] ?? $upn),
            'email' => mb_strtolower((string) ($user['mail'] ?? $upn)),
            'upn' => $upn,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null null = nicht gefunden
     *
     * @throws DirectoryUnavailable
     */
    private function request(string $path, array $query): ?array
    {
        $token = $this->token() ?? throw new DirectoryUnavailable('nicht verbunden');

        try {
            $response = Http::withToken($token)
                ->withHeaders(['ConsistencyLevel' => 'eventual'])
                ->acceptJson()
                ->timeout(10)
                ->get(self::GRAPH.$path, $query);
        } catch (ConnectionException $e) {
            Log::warning('Entra-Verzeichnis nicht erreichbar', ['error' => $e->getMessage()]);
            throw new DirectoryUnavailable('nicht erreichbar');
        }

        if ($response->status() === 404) {
            return null;
        }
        if (in_array($response->status(), [401, 403], true)) {
            // Abgelaufen oder Berechtigung fehlt: neu verbinden lassen.
            $this->forget();
            Log::warning('Entra-Verzeichnis lehnt Anfrage ab', ['status' => $response->status(), 'code' => $response->json('error.code')]);
            throw new DirectoryUnavailable($response->status() === 403 ? 'keine Berechtigung' : 'abgelaufen');
        }
        if ($response->failed()) {
            Log::warning('Entra-Verzeichnis: Fehler', ['status' => $response->status(), 'code' => $response->json('error.code')]);
            throw new DirectoryUnavailable('Fehler '.$response->status());
        }

        return $response->json();
    }

    private function token(): ?string
    {
        $stored = session()->get(self::SESSION_KEY);
        if (! is_string($stored)) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($stored), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $this->forget();

            return null;
        }

        if (($data['expires_at'] ?? 0) <= now()->getTimestamp()) {
            $this->forget();

            return null;
        }

        return $data['token'] ?? null;
    }
}

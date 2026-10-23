<?php

namespace App\Services\Instagram;

use App\Models\IntegrationToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Kapselt die Instagram Graph API (F-IG-02/04, Pflichtenheft 6.4 Hinweise:
 * API-Änderungen bleiben auf diese Klasse beschränkt). Fehlermeldungen
 * enthalten nie das Zugangstoken.
 */
class InstagramClient
{
    public const PROVIDER = 'instagram';

    public function token(): ?IntegrationToken
    {
        return IntegrationToken::for(self::PROVIDER);
    }

    public function isConfigured(): bool
    {
        $token = $this->token();

        return $token !== null && filled($token->account_id) && ($token->expires_at === null || $token->expires_at->isFuture());
    }

    /**
     * @return array{user_id: string, username: string}
     */
    public function me(?string $accessToken = null): array
    {
        $json = $this->send(fn (PendingRequest $http) => $http->get($this->url('me'), [
            'fields' => 'user_id,username',
            'access_token' => $accessToken ?? $this->accessToken(),
        ]));

        if (blank($json['user_id'] ?? null)) {
            throw new InstagramException('Instagram hat kein Konto zu diesem Token geliefert.');
        }

        return ['user_id' => (string) $json['user_id'], 'username' => (string) ($json['username'] ?? '')];
    }

    public function createImageContainer(string $imageUrl, string $caption): string
    {
        return (string) $this->send(fn (PendingRequest $http) => $http->asForm()->post($this->url($this->accountId().'/media'), [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $this->accessToken(),
        ]))['id'];
    }

    public function containerStatus(string $containerId): string
    {
        return (string) ($this->send(fn (PendingRequest $http) => $http->get($this->url($containerId), [
            'fields' => 'status_code',
            'access_token' => $this->accessToken(),
        ]))['status_code'] ?? 'UNKNOWN');
    }

    public function publish(string $containerId): string
    {
        return (string) $this->send(fn (PendingRequest $http) => $http->asForm()->post($this->url($this->accountId().'/media_publish'), [
            'creation_id' => $containerId,
            'access_token' => $this->accessToken(),
        ]))['id'];
    }

    public function permalink(string $mediaId): ?string
    {
        return $this->send(fn (PendingRequest $http) => $http->get($this->url($mediaId), [
            'fields' => 'permalink',
            'access_token' => $this->accessToken(),
        ]))['permalink'] ?? null;
    }

    /**
     * Neueste Beiträge des Kontos zu einem Token (Instagram-Einblendung,
     * Berechtigung instagram_business_basic).
     *
     * @return list<array<string, mixed>>
     */
    public function media(string $accessToken, int $limit): array
    {
        $json = $this->send(fn (PendingRequest $http) => $http->get($this->url('me/media'), [
            'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp',
            'limit' => max(1, min(50, $limit)),
            'access_token' => $accessToken,
        ]));

        return array_values(array_filter((array) ($json['data'] ?? []), 'is_array'));
    }

    /**
     * Verlängert das Long-Lived-Token (ca. 60 Tage, F-IG-04); ohne Angabe
     * das Token der Veröffentlichung.
     *
     * @return array{access_token: string, expires_in: int}
     */
    public function refreshToken(?string $accessToken = null): array
    {
        $json = $this->send(fn (PendingRequest $http) => $http->get(config('services.instagram.graph_url').'/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $accessToken ?? $this->accessToken(),
        ]));

        if (blank($json['access_token'] ?? null)) {
            throw new InstagramException('Instagram hat kein neues Token geliefert.');
        }

        return ['access_token' => $json['access_token'], 'expires_in' => (int) ($json['expires_in'] ?? 0)];
    }

    private function url(string $path): string
    {
        return config('services.instagram.graph_url').'/'.config('services.instagram.version').'/'.$path;
    }

    private function accessToken(): string
    {
        return $this->token()?->access_token ?? throw new InstagramException('Instagram ist nicht verbunden.');
    }

    private function accountId(): string
    {
        return $this->token()?->account_id ?? throw new InstagramException('Instagram ist nicht verbunden.');
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    private function send(callable $request): array
    {
        try {
            $response = $request(Http::timeout(20)->acceptJson());
        } catch (ConnectionException) {
            throw new InstagramException('Instagram ist gerade nicht erreichbar.');
        }

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'HTTP '.$response->status();

            throw new InstagramException('Instagram meldet: '.mb_substr((string) $message, 0, 300));
        }

        return $response->json() ?? [];
    }
}

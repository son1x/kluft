<?php

namespace App\Services\Update;

/**
 * Ein veröffentlichtes Kluft-Release laut signierter manifest.json.
 */
final readonly class Release
{
    /**
     * @param  list<string>  $aenderungen
     */
    public function __construct(
        public string $version,
        public ?string $revision,
        public ?string $veroeffentlicht,
        public string $php,
        public string $url,
        public string $sha256,
        public int $groesse,
        public bool $auto,
        public array $aenderungen,
    ) {}

    /**
     * @param  array<string, mixed>  $daten
     *
     * @throws UpdateFehler
     */
    public static function ausManifest(array $daten, string $manifestUrl): self
    {
        $paket = is_array($daten['package'] ?? null) ? $daten['package'] : [];
        $version = (string) ($daten['version'] ?? '');
        $sha = mb_strtolower((string) ($paket['sha256'] ?? ''));
        $url = (string) ($paket['url'] ?? '');

        if (($daten['name'] ?? null) !== 'kluft' || ! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)
            || ! preg_match('/^[0-9a-f]{64}$/', $sha) || $url === '') {
            throw new UpdateFehler('Die Release-Beschreibung ist unvollständig.');
        }

        // Relative Paket-Adresse gilt neben der manifest.json.
        if (! preg_match('#^https?://#i', $url)) {
            $url = preg_replace('#[^/]*$#', '', $manifestUrl).ltrim($url, '/');
        }
        if (! str_starts_with(mb_strtolower($url), 'https://')) {
            throw new UpdateFehler('Das Paket muss über HTTPS kommen.');
        }

        return new self(
            version: $version,
            revision: isset($daten['revision']) ? (string) $daten['revision'] : null,
            veroeffentlicht: isset($daten['published_at']) ? (string) $daten['published_at'] : null,
            php: (string) ($daten['php'] ?? '8.3'),
            url: $url,
            sha256: $sha,
            groesse: (int) ($paket['size'] ?? 0),
            auto: (bool) ($daten['auto'] ?? true),
            aenderungen: array_values(array_map('strval', array_filter((array) ($daten['changes'] ?? []), 'is_scalar'))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(...$a);
    }
}

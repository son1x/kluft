<?php

namespace App\Services\Jitsi;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Str;

/**
 * Links zur Jitsi-Installation des DV (meet.dpsg-trier.de) nach
 * docs/videokonferenzen.md bzw. der Projektdokumentation Jitsi, Kapitel 8:
 * Teilnahme-Link ohne Token, Moderator-Link mit JWT (HS256), signiert mit
 * dem Secret, das auch Prosody und jitsi-openid nutzen. Gültig bis zum Ende
 * des Tages nach der Konferenz (Europe/Berlin).
 */
class Jitsi
{
    public const ZEITZONE = 'Europe/Berlin';

    private const ZEICHEN = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function __construct(
        private readonly ?string $secret = null,
        private readonly ?string $url = null,
        private readonly ?string $sub = null,
    ) {}

    public static function ausConfig(): self
    {
        return new self(config('services.jitsi.secret'), config('services.jitsi.url'), config('services.jitsi.sub'));
    }

    public function eingerichtet(): bool
    {
        return strlen((string) $this->secret) >= 32 && $this->basis() !== null;
    }

    public function basis(): ?string
    {
        $url = rtrim(trim((string) $this->url), '/');

        return str_starts_with($url, 'https://') ? $url : null;
    }

    public function teilnahmeLink(string $raum): string
    {
        return ($this->basis() ?? 'https://meet.dpsg-trier.de').'/'.$raum;
    }

    /** Null, wenn kein Secret eingerichtet ist. */
    public function moderatorLink(string $raum, int $ablauf, string $name = 'Moderator'): ?string
    {
        if (! $this->eingerichtet()) {
            return null;
        }

        return $this->teilnahmeLink($raum).'?jwt='.$this->token($raum, $ablauf, $name);
    }

    public function token(string $raum, int $ablauf, string $name = 'Moderator', ?int $jetzt = null): string
    {
        $kopf = self::b64u((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $inhalt = self::b64u((string) json_encode([
            'iss' => 'jitsi',
            'aud' => 'jitsi',
            'sub' => $this->sub ?: (string) parse_url((string) $this->basis(), PHP_URL_HOST),
            'room' => $raum,
            'iat' => $jetzt ?? time(),
            'exp' => $ablauf,
            'context' => ['user' => ['name' => $name]],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $signatur = self::b64u(hash_hmac('sha256', $kopf.'.'.$inhalt, (string) $this->secret, true));

        return $kopf.'.'.$inhalt.'.'.$signatur;
    }

    /** Ende des Tages nach der Konferenz, 23:59:59 Uhr Ortszeit, als Unix-Zeit. */
    public static function ablauf(DateTimeInterface $beginn): int
    {
        return (new DateTimeImmutable('@'.$beginn->getTimestamp()))
            ->setTimezone(new DateTimeZone(self::ZEITZONE))
            ->setTime(0, 0, 0)->modify('+1 day')->setTime(23, 59, 59)
            ->getTimestamp();
    }

    /** z. B. „Gruppenstunde Wölflinge“ → gruppenstunde-woelflinge-k3x9q2 */
    public static function raumName(string $titel): string
    {
        $t = strtr(mb_strtolower($titel), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $t = trim((string) preg_replace('/[^a-z0-9]+/', '-', Str::ascii($t)), '-');
        $t = trim(substr($t, 0, 30), '-') ?: 'konferenz';
        $zufall = '';
        for ($i = 0; $i < 6; $i++) {
            $zufall .= self::ZEICHEN[random_int(0, strlen(self::ZEICHEN) - 1)];
        }

        return $t.'-'.$zufall;
    }

    private static function b64u(string $roh): string
    {
        return rtrim(strtr(base64_encode($roh), '+/', '-_'), '=');
    }
}

<?php

namespace App\Services\Mitglieder;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * NaMi 3 auf hitobito-Basis (JSON:API, /api/people). Zugang über einen
 * Service-Token der eigenen Ebene mit „Personen dieser und der
 * darunterliegenden Ebenen“. Geburtsdatum und Austrittsdatum liefert
 * hitobito nur mit Detail-Leserecht; ohne sie entscheidet der Abgleich
 * über Name und E-Mail.
 */
class HitobitoVerzeichnis implements Mitgliederverzeichnis
{
    public function name(): string
    {
        return 'NaMi 3 (hitobito)';
    }

    public function suche(string $vorname, string $nachname): array
    {
        $nummer = (string) config('mitglieder.hitobito.member_number_attribute');
        $felder = array_filter(['first_name', 'last_name', 'birthday', 'email', 'exit_date', $nummer]);

        $body = $this->get([
            'filter[last_name][eq]' => $nachname,
            'filter[first_name][prefix]' => $vorname,
            'fields[people]' => implode(',', $felder),
            'include' => 'layer_group',
            'fields[groups]' => 'name',
            'page[size]' => 50,
        ]);

        $gruppen = collect((array) ($body['included'] ?? []))
            ->filter(fn ($i) => is_array($i) && ($i['type'] ?? null) === 'groups')
            ->mapWithKeys(fn (array $i) => [(string) $i['id'] => (string) ($i['attributes']['name'] ?? '')]);

        return array_values(array_map(function (array $person) use ($gruppen, $nummer): Mitglied {
            $a = (array) ($person['attributes'] ?? []);
            $gruppe = $person['relationships']['layer_group']['data']['id'] ?? null;
            $austritt = filled($a['exit_date'] ?? null) ? Carbon::parse((string) $a['exit_date']) : null;

            return new Mitglied(
                nummer: (string) ($nummer !== '' && filled($a[$nummer] ?? null) ? $a[$nummer] : $person['id']),
                vorname: (string) ($a['first_name'] ?? ''),
                nachname: (string) ($a['last_name'] ?? ''),
                geburtsdatum: filled($a['birthday'] ?? null) ? Carbon::parse((string) $a['birthday']) : null,
                email: filled($a['email'] ?? null) ? (string) $a['email'] : null,
                gruppierung: $gruppe !== null ? ($gruppen[(string) $gruppe] ?? null) : null,
                aktiv: $austritt === null || $austritt->isFuture(),
            );
        }, array_filter((array) ($body['data'] ?? []), 'is_array')));
    }

    public function pruefe(): void
    {
        $this->get(['filter[last_name][eq]' => 'Kluft-Verbindungstest', 'fields[people]' => 'first_name', 'page[size]' => 1]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(array $query): array
    {
        $url = rtrim((string) config('mitglieder.hitobito.url'), '/');
        $token = (string) config('mitglieder.hitobito.token');
        if ($url === '' || $token === '') {
            throw new MitgliederverzeichnisException('hitobito-Zugang fehlt (HITOBITO_URL, HITOBITO_TOKEN).');
        }

        try {
            $response = Http::timeout((int) config('mitglieder.timeout', 20))
                ->withHeaders(['X-TOKEN' => $token, 'Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json'])
                ->get($url.'/api/people', $query);
        } catch (ConnectionException) {
            throw new MitgliederverzeichnisException('hitobito nicht erreichbar.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new MitgliederverzeichnisException('hitobito lehnt den Service-Token ab (HTTP '.$response->status().'). Berechtigungen des Tokens prüfen.');
        }
        $body = $response->json();
        if (! $response->successful() || ! is_array($body)) {
            throw new MitgliederverzeichnisException('hitobito-Abfrage fehlgeschlagen (HTTP '.$response->status().').');
        }

        return $body;
    }
}

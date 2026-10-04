<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Bei Meta angelegte Nachrichtenvorlage (nur sie dürfen außerhalb des
 * 24-Stunden-Fensters verschickt werden).
 *
 * @property string|null $meta_id
 * @property string $name
 * @property string $language
 * @property string $category
 * @property string $purpose
 * @property string $status
 * @property list<array<string, mixed>>|null $components
 * @property Carbon|null $synced_at
 */
class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = ['meta_id', 'name', 'language', 'category', 'purpose', 'status', 'components', 'quality', 'rejected_reason', 'synced_at'];

    protected function casts(): array
    {
        return ['components' => 'array', 'synced_at' => 'datetime'];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUsable(Builder $query, string $purpose = 'newsletter'): Builder
    {
        return $query->where('status', 'APPROVED')->where('purpose', $purpose);
    }

    /** @return array<string, mixed>|null */
    public function component(string $type): ?array
    {
        foreach ($this->components ?? [] as $component) {
            if (strtoupper((string) ($component['type'] ?? '')) === $type) {
                return $component;
            }
        }

        return null;
    }

    public function bodyText(): string
    {
        return (string) ($this->component('BODY')['text'] ?? '');
    }

    /** Anzahl der Platzhalter {{1}}, {{2}} … im Text. */
    public function bodyPlaceholders(): int
    {
        preg_match_all('/\{\{(\d+)\}\}/', $this->bodyText(), $m);

        return $m[1] === [] ? 0 : max(array_map('intval', $m[1]));
    }

    /** URL-Button mit Platzhalter (z. B. https://dpsg-trier.de/{{1}}), sonst null. */
    public function urlButton(): ?array
    {
        foreach ($this->component('BUTTONS')['buttons'] ?? [] as $button) {
            if (($button['type'] ?? '') === 'URL' && str_contains((string) ($button['url'] ?? ''), '{{1}}')) {
                return $button;
            }
        }

        return null;
    }

    /** Vorschau mit eingesetzten Werten. */
    public function render(array $values): string
    {
        return preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => (string) ($values[(int) $m[1] - 1] ?? $m[0]), $this->bodyText()) ?? '';
    }
}

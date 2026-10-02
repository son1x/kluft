<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Thema im Kontaktformular mit seinen Empfänger*innen (F-CMS-09).
 */
class ContactTopic extends Model
{
    protected $fillable = ['label', 'slug', 'email', 'stage', 'section', 'sort', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('sort')->orderBy('label');
    }

    /**
     * Feste Adresse, sonst die Stufenreferent*innen bzw. Ansprechpersonen
     * des Fachbereichs, sonst das Diözesanbüro.
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        if (filled($this->email)) {
            return [$this->email];
        }

        $people = match (true) {
            filled($this->stage) => Person::query()->visible()->where('gremium', 'leitung')->where('stage', $this->stage)->orderBy('sort')->get(),
            filled($this->section) => Person::query()->visible()->where('section', $this->section)->orderBy('sort')->get(),
            default => collect(),
        };

        $emails = $people->map(fn (Person $p) => $p->publicEmail())->filter()->unique()->values()->all();

        return $emails !== [] ? $emails : [self::fallbackEmail()];
    }

    /** Für die Anzeige im Backend: an wen die Nachrichten gerade gehen. */
    public function recipientsLabel(): string
    {
        $recipients = implode(', ', $this->recipients());

        return filled($this->email) || $recipients !== self::fallbackEmail()
            ? $recipients
            : $recipients.' (Diözesanbüro)';
    }

    /** Link aufs Kontaktformular mit vorausgewähltem Thema der Stufe bzw. des Fachbereichs. */
    public static function urlFor(string $column, string $value): ?string
    {
        $slug = static::query()->active()->where($column, $value)->value('slug');

        return $slug ? url('/'.config('dpsg.contact_page')).'?thema='.urlencode($slug).'#kontaktformular' : null;
    }

    public static function fallbackEmail(): string
    {
        return (string) config('dpsg.contact.email');
    }
}
